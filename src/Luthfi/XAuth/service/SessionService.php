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

    public function handleSession(Player $player): \Generator {
        return Await::f2c(function () use ($player) {
            $dataProvider = $this->plugin->getDataProvider();
            if ($dataProvider === null) return; // No yield from here, as it's a check

            $autoLoginConfig = (array)$this->plugin->getConfig()->get('auto-login', []);

            $securityLevel = (int)($autoLoginConfig['security_level'] ?? 1);
            $lowerPlayerName = strtolower($player->getName());
            $deviceId = $this->plugin->deviceIds[$lowerPlayerName] ?? null;

            if ($deviceId === null) return;

            $sessions = yield from $dataProvider->getSessionsByPlayer($player->getName());
            $ip = $player->getNetworkSession()->getIp();
            $lifetime = (int)($autoLoginConfig['lifetime_seconds'] ?? 2592000);
            $refreshSession = (bool)($autoLoginConfig['refresh_session_on_login'] ?? true);

            $existingSessionId = null;
            foreach ($sessions as $sessionId => $sessionData) {
                $ipMatch = ($sessionData['ip_address'] ?? '') === $ip;
                $deviceIdMatch = ($sessionData['device_id'] ?? null) === $deviceId;

                if (($securityLevel === 1 && $ipMatch && $deviceIdMatch) || ($securityLevel === 0 && $ipMatch)) {
                    $existingSessionId = $sessionId;
                    break;
                }
            }

            if ($existingSessionId !== null) {
                if ($refreshSession) {
                    yield from $dataProvider->refreshSession($existingSessionId, $lifetime);
                }
            } else {
                $maxSessions = (int)($autoLoginConfig["max_sessions_per_player"] ?? 5);
                if ($maxSessions > 0) {
                    $currentSessions = yield from $dataProvider->getSessionsByPlayer($player->getName());
                    if (count($currentSessions) >= $maxSessions) {
                        uasort($currentSessions, function($a, $b) {
                            return ($a['login_time'] ?? 0) <=> ($b['login_time'] ?? 0);
                        });
                        $sessionsToDeleteCount = count($currentSessions) - $maxSessions + 1;
                        $sessionsToDelete = array_slice(array_keys($currentSessions), 0, $sessionsToDeleteCount);
                        foreach ($sessionsToDelete as $sessionId) {
                            yield from $dataProvider->deleteSession($sessionId);
                        }
                    }
                }
                yield from $dataProvider->createSession($player->getName(), $ip, $deviceId, $lifetime);
            }
        });
    }

    public function getSessionsForPlayer(string $playerName): \Generator {
        return Await::f2c(function () use ($playerName) {
            return yield from $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
        });
    }

    public function terminateSession(string $sessionId): \Generator {
        return Await::f2c(function () use ($sessionId) {
            $session = yield from $this->plugin->getDataProvider()->getSession($sessionId);
            if ($session === null) {
                return false;
            }

            yield from $this->plugin->getDataProvider()->deleteSession($sessionId);

            $playerName = (string)($session['player_name'] ?? '');
            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                yield from $this->plugin->getAuthenticationService()->handleLogout($player);
            }
            return true;
        });
    }

    public function terminateAllSessionsForPlayer(string $playerName): \Generator {
        return Await::f2c(function () use ($playerName) {
            yield from $this->plugin->getDataProvider()->deleteAllSessionsForPlayer($playerName);

            $player = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                yield from $this->plugin->getAuthenticationService()->handleLogout($player);
            }
        });
    }

    public function cleanupExpiredSessions(): \Generator {
        return Await::f2c(function () {
            yield from $this->plugin->getDataProvider()->cleanupExpiredSessions();
        });
    }
}
