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

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;

abstract class AbstractDataProvider implements DataProviderInterface {

    protected DataConnector $connector;
    protected Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $config = $plugin->getConfig()->get('database');
        if (!is_array($config)) {
            $config = [];
        }

        $this->connector = libasynql::create(
            $plugin,
            $config,
            $this->getSqlMap(),
            $this->plugin->getConfig()->getNested('database.log_queries', false)
        );
    }

    public function initialize(): Await {
        return Await::f2c(function () {
            try {
                // yield from $this->connector->asyncGeneric('xauth.init');
                // $this->plugin->getLogger()->debug("Database tables initialized.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to initialize database tables: " . $error->getMessage());
                throw $error;
            }
            yield from $this->init();
        });
    }

    abstract protected function init(): Await;

    abstract protected function getSqlMap(): array;

    public function getPlayer(Player|OfflinePlayer $player): Await {
        $name = strtolower($player->getName());
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.get', ['name' => $name]);
                return count($rows) > 0 ? $rows[0] : null;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get player data for {$name}: " . $error->getMessage());
                return null;
            }
        });
    }

    public function isPlayerRegistered(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.is_registered', ['name' => $name]);
                return count($rows) > 0;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to check if player {$name} is registered: " . $error->getMessage());
                return false;
            }
        });
    }

    public function registerPlayer(Player $player, string $hashedPassword): Await {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        return Await::f2c(function () use ($name, $hashedPassword, $ip) {
            try {
                yield from $this->connector->asyncInsert('xauth.players.register', [
                    'name' => $name,
                    'password' => $hashedPassword,
                    'ip' => $ip,
                    'registered_at' => time(),
                    'registration_ip' => $ip,
                    'last_login_at' => time()
                ]);
                $this->plugin->getLogger()->debug("Player {$name} registered successfully.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to register player {$name}: " . $error->getMessage());
            }
        });
    }

    public function updatePlayerIp(Player $player): Await {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        return Await::f2c(function () use ($name, $ip) {
            try {
                yield from $this->connector->asyncChange('xauth.players.update_ip', [
                    'name' => $name,
                    'ip' => $ip,
                    'last_login_at' => time()
                ]);
                $this->plugin->getLogger()->debug("Player {$name} IP updated successfully.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to update player {$name} IP: " . $error->getMessage());
            }
        });
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): Await {
        $name = strtolower($player->getName());
        return Await::f2c(function () use ($name, $newHashedPassword) {
            try {
                yield from $this->connector->asyncChange('xauth.players.change_password', [
                    'name' => $name,
                    'password' => $newHashedPassword
                ]);
                $this->plugin->getLogger()->debug("Player {$name} password changed successfully.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to change player {$name} password: " . $error->getMessage());
            }
        });
    }

    public function unregisterPlayer(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                yield from $this->connector->asyncChange('xauth.players.unregister', [
                    'name' => $name
                ]);
                $this->plugin->getLogger()->debug("Player {$name} unregistered successfully.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to unregister player {$name}: " . $error->getMessage());
            }
        });
    }

    public function setPlayerLocked(string $playerName, bool $locked): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name, $locked) {
            try {
                yield from $this->connector->asyncChange('xauth.players.set_locked', [
                    'name' => $name,
                    'locked' => (int)$locked
                ]);
                $this->plugin->getLogger()->debug("Player {$name} locked status set to " . ($locked ? 'true' : 'false') . ".");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to set player {$name} locked status: " . $error->getMessage());
            }
        });
    }

    public function isPlayerLocked(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.is_locked', ['name' => $name]);
                return count($rows) > 0 ? (bool)($rows[0]['locked'] ?? false) : false;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get player {$name} locked status: " . $error->getMessage());
                return false;
            }
        });
    }

    public function setBlockedUntil(string $playerName, int $timestamp): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name, $timestamp) {
            try {
                yield from $this->connector->asyncChange('xauth.players.set_blocked_until', [
                    'name' => $name,
                    'timestamp' => $timestamp
                ]);
                $this->plugin->getLogger()->debug("Player {$name} blocked until {$timestamp}.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to set player {$name} blocked until: " . $error->getMessage());
            }
        });
    }

    public function getBlockedUntil(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.get_blocked_until', ['name' => $name]);
                return count($rows) > 0 ? (int)($rows[0]['blocked_until'] ?? 0) : 0;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get player {$name} blocked until: " . $error->getMessage());
                return 0;
            }
        });
    }

    public function setMustChangePassword(string $playerName, bool $required): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name, $required) {
            try {
                yield from $this->connector->asyncChange('xauth.players.set_must_change_password', [
                    'name' => $name,
                    'required' => (int)$required
                ]);
                $this->plugin->getLogger()->debug("Player {$name} must change password set to " . ($required ? 'true' : 'false') . ".");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to set player {$name} must change password: " . $error->getMessage());
            }
        });
    }

    public function mustChangePassword(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.must_change_password', ['name' => $name]);
                return count($rows) > 0 ? (bool)($rows[0]['must_change_password'] ?? false) : false;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get player {$name} must change password status: " . $error->getMessage());
                return false;
            }
        });
    }

    public function getAllPlayerData(): Await {
        return Await::f2c(function () {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.get_all_data');
                $data = [];
                foreach ($rows as $row) {
                    $data[strtolower($row['name'])] = $row;
                }
                return $data;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get all player data: " . $error->getMessage());
                return [];
            }
        });
    }

    public function registerPlayerRaw(string $playerName, array $data): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name, $data) {
            try {
                yield from $this->connector->asyncInsert('xauth.players.register_raw', [
                    'name' => $name,
                    'password' => $data['password'],
                    'ip' => $data['ip'],
                    'locked' => (int)($data['locked'] ?? false),
                    'registered_at' => $data['registered_at'],
                    'registration_ip' => $data['registration_ip'],
                    'last_login_at' => $data['last_login_at'],
                    'blocked_until' => $data['blocked_until'] ?? 0,
                    'must_change_password' => (int)($data['must_change_password'] ?? false)
                ]);
                $this->plugin->getLogger()->debug("Raw player {$name} registered successfully.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to register raw player {$name}: " . $error->getMessage());
            }
        });
    }

    public function createSession(string $playerName, string $ipAddress, string $deviceId, int $lifetimeSeconds): Await {
        $sessionId = bin2hex(random_bytes(16));
        $loginTime = time();
        $expirationTime = $loginTime + $lifetimeSeconds;

        return Await::f2c(function () use ($sessionId, $playerName, $ipAddress, $deviceId, $loginTime, $expirationTime) {
            try {
                yield from $this->connector->asyncInsert('xauth.sessions.create', [
                    'session_id' => $sessionId,
                    'player_name' => strtolower($playerName),
                    'ip_address' => $ipAddress,
                    'device_id' => $deviceId,
                    'login_time' => $loginTime,
                    'last_activity' => $loginTime,
                    'expiration_time' => $expirationTime
                ]);
                $this->plugin->getLogger()->debug("Session {$sessionId} created for player {$playerName}.");
                return $sessionId;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to create session for player {$playerName}: " . $error->getMessage());
                return null;
            }
        });
    }

    public function getSession(string $sessionId): Await {
        return Await::f2c(function () use ($sessionId) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.sessions.get', [
                    'session_id' => $sessionId,
                    'current_time' => time()
                ]);
                return count($rows) > 0 ? $rows[0] : null;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get session {$sessionId}: " . $error->getMessage());
                return null;
            }
        });
    }

    public function getSessionsByPlayer(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.sessions.get_by_player', [
                    'player_name' => $name,
                    'current_time' => time()
                ]);
                $sessions = [];
                foreach ($rows as $row) {
                    $sessions[$row['session_id']] = $row;
                }
                return $sessions;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get sessions for player {$name}: " . $error->getMessage());
                return [];
            }
        });
    }

    public function deleteSession(string $sessionId): Await {
        return Await::f2c(function () use ($sessionId) {
            try {
                yield from $this->connector->asyncChange('xauth.sessions.delete', [
                    'session_id' => $sessionId
                ]);
                $this->plugin->getLogger()->debug("Session {$sessionId} deleted.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to delete session {$sessionId}: " . $error->getMessage());
            }
        });
    }

    public function deleteAllSessionsForPlayer(string $playerName): Await {
        $name = strtolower($playerName);
        return Await::f2c(function () use ($name) {
            try {
                yield from $this->connector->asyncChange('xauth.sessions.delete_all_for_player', [
                    'player_name' => $name
                ]);
                $this->plugin->getLogger()->debug("All sessions deleted for player {$name}.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to delete all sessions for player {$name}: " . $error->getMessage());
            }
        });
    }

    public function updateSessionLastActivity(string $sessionId): Await {
        return Await::f2c(function () use ($sessionId) {
            try {
                yield from $this->connector->asyncChange('xauth.sessions.update_last_activity', [
                    'session_id' => $sessionId,
                    'current_time' => time()
                ]);
                $this->plugin->getLogger()->debug("Session {$sessionId} last activity updated.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to update session {$sessionId} last activity: " . $error->getMessage());
            }
        });
    }

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): Await {
        return Await::f2c(function () use ($sessionId, $newLifetimeSeconds) {
            try {
                yield from $this->connector->asyncChange('xauth.sessions.refresh', [
                    'session_id' => $sessionId,
                    'expiration_time' => time() + $newLifetimeSeconds
                ]);
                $this->plugin->getLogger()->debug("Session {$sessionId} refreshed.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to refresh session {$sessionId}: " . $error->getMessage());
            }
        });
    }

    public function cleanupExpiredSessions(): Await {
        return Await::f2c(function () {
            try {
                yield from $this->connector->asyncChange('xauth.sessions.cleanup_expired', [
                    'current_time' => time()
                ]);
                $this->plugin->getLogger()->debug("Expired sessions cleaned up.");
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to clean up expired sessions: " . $error->getMessage());
            }
        });
    }

    public function getRegistrationCountByIp(string $ipAddress): Await {
        return Await::f2c(function () use ($ipAddress) {
            try {
                $rows = yield from $this->connector->asyncSelect('xauth.players.get_registration_count_by_ip', [
                    'ip' => $ipAddress
                ]);
                return count($rows) > 0 ? (int)($rows[0]['count'] ?? 0) : 0;
            } catch (SqlError $error) {
                $this->plugin->getLogger()->error("Failed to get registration count for IP {$ipAddress}: " . $error->getMessage());
                return 0;
            }
        });
    }

    public function close(): void {
        $this->connector->close();
    }
}
