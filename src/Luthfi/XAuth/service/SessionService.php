<?php

/*
 *
 * __  __    _         _   _
 * \ \/ /   / \  _   _| |_| |__
 *  \  /   / _ \| | | | __| '_ \
 *  /  \  / ___ \ |_| | |_| | | |
 * /_/\_\/_/   \_\__,_|\__|_| |_|
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author LuthMC
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

class SessionService {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function handleSession(Player $player): Await {
        return Await::f2c(function () use ($player) {
            $dataProvider = $this->plugin->getDataProvider();
            if ($dataProvider === null) return; // No yield from here, as it's a check

            $config = (array)$this->plugin->getConfig()->get('auto-login', []);
            $securityLevel = (int)($config['security_level'] ?? 1);
            $lifetime = (int)($config['lifetime_seconds'] ?? 2592000);
            $refreshSession = (bool)($config['refresh_session_on_login'] ?? true);
            $maxSessions = (int)($config['max_sessions_per_player'] ?? 5);

            $lowerName = strtolower($player->getName());
            $deviceId = $this->plugin->deviceIds[$lowerName] ?? null;
            if ($deviceId === null) return;

            $ip = $player->getNetworkSession()->getIp();
            $sessions = yield from $dataProvider->getSessionsByPlayer($player->getName());

            $existingSessionId = null;
            foreach ($sessions as $sessionId => $s) {
                $ipMatch = ($s['ip_address'] ?? '') === $ip;
                $deviceIdMatch = ($s['device_id'] ?? null) === $deviceId;
                if (($securityLevel === 1 && $ipMatch && $deviceIdMatch) || ($securityLevel === 0 && $ipMatch)) {
                    $existingSessionId = $sessionId;
                    break;
                }
            }

            if ($existingSessionId !== null) {
                if ($refreshSession) {
                    yield from $dataProvider->refreshSession($existingSessionId, $lifetime);
                }
                return;
            }

            if ($maxSessions > 0) {
                if (count($sessions) >= $maxSessions) {
                    uasort($sessions, fn($a, $b) => ($a['login_time'] ?? 0) <=> ($b['login_time'] ?? 0));
                    $sessionsToDeleteCount = count($sessions) - $maxSessions + 1;
                    foreach (array_slice(array_keys($sessions), 0, $sessionsToDeleteCount) as $sid) {
                        yield from $dataProvider->deleteSession($sid);
                    }
                }
            }

            yield from $dataProvider->createSession($player->getName(), $ip, $deviceId, $lifetime);
        });
    }

    public function getSessionsForPlayer(string $playerName): Await {
        return Await::f2c(fn() => yield from $this->plugin->getDataProvider()->getSessionsByPlayer($playerName));
    }

    public function terminateSession(string $sessionId): Await {
        return Await::f2c(function () use ($sessionId) {
            $session = yield from $this->plugin->getDataProvider()->getSession($sessionId);
            if ($session === null) return false;

            yield from $this->plugin->getDataProvider()->deleteSession($sessionId);

            $player = $this->plugin->getServer()->getPlayerExact((string)($session['player_name'] ?? ''));
            if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                yield from $this->plugin->getAuthenticationService()->handleLogout($player);
            }
            return true;
        });
    }

    public function terminateAllSessionsForPlayer(string $playerName): Await {
        return Await::f2c(function () use ($playerName) {
            yield from $this->plugin->getDataProvider()->deleteAllSessionsForPlayer($playerName);
            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                yield from $this->plugin->getAuthenticationService()->handleLogout($player);
            }
        });
    }

    public function cleanupExpiredSessions(): Await {
        return Await::f2c(fn() => yield from $this->plugin->getDataProvider()->cleanupExpiredSessions());
    }
}
