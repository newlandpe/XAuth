<?php

declare(strict_types=1);

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\Main;
use pocketmine\player\Player;

class SessionService {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function handleSession(Player $player): void {
        $dataProvider = $this->plugin->getDataProvider();
        if ($dataProvider === null) return;

        $autoLoginConfig = (array)$this->plugin->getConfig()->get('auto-login', []);

        $securityLevel = (int)($autoLoginConfig['security_level'] ?? 1);
        $lowerPlayerName = strtolower($player->getName());
        $deviceId = $this->plugin->deviceIds[$lowerPlayerName] ?? null;

        if ($deviceId === null) return;

        $sessions = $dataProvider->getSessionsByPlayer($player->getName());
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
                $dataProvider->refreshSession($existingSessionId, $lifetime);
            }
        } else {
            $maxSessions = (int)($autoLoginConfig["max_sessions_per_player"] ?? 5);
            if ($maxSessions > 0) {
                $currentSessions = $dataProvider->getSessionsByPlayer($player->getName());
                if (count($currentSessions) >= $maxSessions) {
                    uasort($currentSessions, function($a, $b) {
                        return ($a['login_time'] ?? 0) <=> ($b['login_time'] ?? 0);
                    });
                    $sessionsToDeleteCount = count($currentSessions) - $maxSessions + 1;
                    $sessionsToDelete = array_slice(array_keys($currentSessions), 0, $sessionsToDeleteCount);
                    foreach ($sessionsToDelete as $sessionId) {
                        $dataProvider->deleteSession($sessionId);
                    }
                }
            }
            $dataProvider->createSession($player->getName(), $ip, $deviceId, $lifetime);
        }
    }

    public function getSessionsForPlayer(string $playerName): array {
        return $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
    }

    public function terminateSession(string $sessionId): bool {
        $session = $this->plugin->getDataProvider()->getSession($sessionId);
        if ($session === null) {
            return false;
        }

        $this->plugin->getDataProvider()->deleteSession($sessionId);

        $playerName = (string)($session['player_name'] ?? '');
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            $this->plugin->getAuthenticationService()->handleLogout($player);
        }
        return true;
    }

    public function terminateAllSessionsForPlayer(string $playerName): void {
        $this->plugin->getDataProvider()->deleteAllSessionsForPlayer($playerName);

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null && $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            $this->plugin->getAuthenticationService()->handleLogout($player);
        }
    }

    public function cleanupExpiredSessions(): void {
        $this->plugin->getDataProvider()->cleanupExpiredSessions();
    }
}
