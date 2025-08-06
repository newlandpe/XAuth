<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class YamlProvider implements DataProviderInterface {

    private Config $playerData;
    private Config $sessionData;
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->playerData = new Config($plugin->getDataFolder() . "players.yml", Config::YAML);
        $this->sessionData = new Config($plugin->getDataFolder() . "sessions.yml", Config::YAML);
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array {
        $name = strtolower($player->getName());
        $data = $this->playerData->get($name);
        return is_array($data) ? $data : null;
    }

    public function isPlayerRegistered(string $playerName): bool {
        return $this->playerData->exists(strtolower($playerName));
    }

    public function registerPlayer(Player $player, string $hashedPassword): void {
        $name = strtolower($player->getName());
        $this->playerData->set($name, [
            "password" => $hashedPassword,
            "ip" => $player->getNetworkSession()->getIp(),
            "registered_at" => time(),
            "registration_ip" => $player->getNetworkSession()->getIp(),
            "last_login_at" => time(),
            "locked" => false,
            "blocked_until" => 0,
            "must_change_password" => false
        ]);
        $this->playerData->save();
    }

    public function updatePlayerIp(Player $player): void {
        $name = strtolower($player->getName());
        $data = $this->playerData->get($name);
        if (is_array($data)) {
            $data['ip'] = $player->getNetworkSession()->getIp();
            $data['last_login_at'] = time();
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void {
        $name = strtolower($player->getName());
        $data = $this->playerData->get($name);
        if (is_array($data)) {
            $data['password'] = $newHashedPassword;
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function unregisterPlayer(string $playerName): void {
        $name = strtolower($playerName);
        if ($this->playerData->exists($name)) {
            $this->playerData->remove($name);
            $this->playerData->save();
        }
    }

    public function setPlayerLocked(string $playerName, bool $locked): void {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        if (is_array($data)) {
            $data['locked'] = $locked;
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function isPlayerLocked(string $playerName): bool {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        return is_array($data) && (bool)($data['locked'] ?? false);
    }

    public function setBlockedUntil(string $playerName, int $timestamp): void {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        if (is_array($data)) {
            $data['blocked_until'] = $timestamp;
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function getBlockedUntil(string $playerName): int {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        return is_array($data) ? (int)($data['blocked_until'] ?? 0) : 0;
    }

    public function setMustChangePassword(string $playerName, bool $required): void {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        if (is_array($data)) {
            $data['must_change_password'] = $required;
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function mustChangePassword(string $playerName): bool {
        $name = strtolower($playerName);
        $data = $this->playerData->get($name);
        return is_array($data) && (bool)($data['must_change_password'] ?? false);
    }

    public function getAllPlayerData(): array {
        return $this->playerData->getAll();
    }

    public function registerPlayerRaw(string $playerName, array $data): void {
        $this->playerData->set(strtolower($playerName), $data);
        $this->playerData->save();
    }

    public function createSession(string $playerName, string $ipAddress, int $lifetimeSeconds): string {
        $playerNameLower = strtolower($playerName);
        $sessions = $this->getSessionsByPlayer($playerNameLower);
        $maxSessions = (int)($this->plugin->getConfig()->getNested('auto-login.max_sessions_per_player') ?? 5);

        if (count($sessions) >= $maxSessions) {
            uasort($sessions, function($a, $b) {
                return ($a['last_activity'] ?? 0) <=> ($b['last_activity'] ?? 0);
            });
            $sessionsToDelete = array_slice($sessions, 0, count($sessions) - $maxSessions + 1, true);
            foreach (array_keys($sessionsToDelete) as $sessionId) {
                $this->deleteSession($sessionId);
            }
        }

        $sessionId = bin2hex(random_bytes(16));
        $loginTime = time();
        $expirationTime = $loginTime + $lifetimeSeconds;

        $this->sessionData->set($sessionId, [
            "player_name" => $playerNameLower,
            "ip_address" => $ipAddress,
            "login_time" => $loginTime,
            "last_activity" => $loginTime,
            "expiration_time" => $expirationTime
        ]);
        $this->sessionData->save();
        return $sessionId;
    }

    public function getSession(string $sessionId): ?array {
        $session = $this->sessionData->get($sessionId);
        if (is_array($session) && ($session['expiration_time'] ?? 0) > time()) {
            return $session;
        }
        return null;
    }

    public function getSessionsByPlayer(string $playerName): array {
        $sessions = [];
        foreach ($this->sessionData->getAll() as $sessionId => $session) {
            if (strtolower($session['player_name'] ?? '') === strtolower($playerName) && ($session['expiration_time'] ?? 0) > time()) {
                $sessions[$sessionId] = $session;
            }
        }
        // Sort by login_time descending
        uasort($sessions, function($a, $b) {
            return ($b['login_time'] ?? 0) <=> ($a['login_time'] ?? 0);
        });
        return $sessions;
    }

    public function deleteSession(string $sessionId): void {
        $this->sessionData->remove($sessionId);
        $this->sessionData->save();
    }

    public function deleteAllSessionsForPlayer(string $playerName): void {
        $sessionsToDelete = [];
        foreach ($this->sessionData->getAll() as $sessionId => $session) {
            if (strtolower($session['player_name'] ?? '') === strtolower($playerName)) {
                $sessionsToDelete[] = $sessionId;
            }
        }
        foreach ($sessionsToDelete as $sessionId) {
            $this->sessionData->remove($sessionId);
        }
        $this->sessionData->save();
    }

    public function updateSessionLastActivity(string $sessionId): void {
        $session = $this->sessionData->get($sessionId);
        if (is_array($session)) {
            $session['last_activity'] = time();
            $this->sessionData->set($sessionId, $session);
            $this->sessionData->save();
        }
    }

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): void {
        $session = $this->sessionData->get($sessionId);
        if (is_array($session)) {
            $session['expiration_time'] = time() + $newLifetimeSeconds;
            $this->sessionData->set($sessionId, $session);
            $this->sessionData->save();
        }
    }

    public function cleanupExpiredSessions(): void {
        $sessionsToKeep = [];
        foreach ($this->sessionData->getAll() as $sessionId => $session) {
            if (($session['expiration_time'] ?? 0) > time()) {
                $sessionsToKeep[$sessionId] = $session;
            }
        }
        $this->sessionData->setAll($sessionsToKeep);
        $this->sessionData->save();
    }

    public function getRegistrationCountByIp(string $ipAddress): int {
        $count = 0;
        foreach ($this->playerData->getAll() as $playerData) {
            if (isset($playerData['registration_ip']) && $playerData['registration_ip'] === $ipAddress) {
                $count++;
            }
        }
        return $count;
    }

    public function close(): void {
        $this->playerData->save();
        $this->sessionData->save();
    }
}
