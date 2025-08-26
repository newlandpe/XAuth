<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use SQLite3;

class SqliteProvider implements DataProviderInterface {

    private SQLite3 $db;
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->db = new SQLite3($plugin->getDataFolder() . "players.db");
        $this->db->enableExceptions(true);
        $this->db->exec("PRAGMA foreign_keys = ON");

        $this->db->exec("CREATE TABLE IF NOT EXISTS players (
            name TEXT PRIMARY KEY,
            password TEXT,
            ip TEXT,
            locked INTEGER DEFAULT 0,
            registered_at INTEGER,
            registration_ip TEXT,
            last_login_at INTEGER,
            blocked_until INTEGER DEFAULT 0,
            must_change_password INTEGER DEFAULT 0
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS sessions (
            session_id TEXT PRIMARY KEY,
            player_name TEXT NOT NULL,
            ip_address TEXT,
            device_id TEXT,
            login_time INTEGER,
            last_activity INTEGER,
            expiration_time INTEGER,
            FOREIGN KEY (player_name) REFERENCES players(name) ON DELETE CASCADE
        )");
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("SELECT * FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function isPlayerRegistered(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT name FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    public function registerPlayer(Player $player, string $hashedPassword): void {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        $stmt = $this->db->prepare("INSERT INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at)");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->bindValue(":password", $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(":ip", $ip, SQLITE3_TEXT);
        $stmt->bindValue(":locked", 0, SQLITE3_INTEGER);
        $stmt->bindValue(":registered_at", time(), SQLITE3_INTEGER);
        $stmt->bindValue(":registration_ip", $ip, SQLITE3_TEXT);
        $stmt->bindValue(":last_login_at", time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function updatePlayerIp(Player $player): void {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        $stmt = $this->db->prepare("UPDATE players SET ip = :ip, last_login_at = :last_login_at WHERE name = :name");
        $stmt->bindValue(":last_login_at", time(), SQLITE3_INTEGER);
        $stmt->bindValue(":ip", $ip, SQLITE3_TEXT);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("UPDATE players SET password = :password WHERE name = :name");
        $stmt->bindValue(":password", $newHashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function unregisterPlayer(string $playerName): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("DELETE FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function setPlayerLocked(string $playerName, bool $locked): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET locked = :locked WHERE name = :name");
        $stmt->bindValue(":locked", (int)$locked, SQLITE3_INTEGER);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function isPlayerLocked(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT locked FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) && (bool)($row['locked'] ?? false);
    }

    public function setBlockedUntil(string $playerName, int $timestamp): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET blocked_until = :timestamp WHERE name = :name");
        $stmt->bindValue(":timestamp", $timestamp, SQLITE3_INTEGER);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getBlockedUntil(string $playerName): int {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT blocked_until FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) ? (int)($row['blocked_until'] ?? 0) : 0;
    }

    public function setMustChangePassword(string $playerName, bool $required): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET must_change_password = :required WHERE name = :name");
        $stmt->bindValue(":required", (int)$required, SQLITE3_INTEGER);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function mustChangePassword(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT must_change_password FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) && (bool)($row['must_change_password'] ?? false);
    }

    public function getAllPlayerData(): array {
        $result = $this->db->query("SELECT * FROM players");
        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[strtolower($row['name'])] = $row;
        }
        return $data;
    }

    public function registerPlayerRaw(string $playerName, array $data): void {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at, blocked_until, must_change_password) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at, :blocked_until, :must_change_password)");
        $stmt->bindValue(":name", strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(":password", $data['password'], SQLITE3_TEXT);
        $stmt->bindValue(":ip", $data['ip'], SQLITE3_TEXT);
        $stmt->bindValue(":locked", (int)($data['locked'] ?? false), SQLITE3_INTEGER);
        $stmt->bindValue(":registered_at", $data['registered_at'], SQLITE3_INTEGER);
        $stmt->bindValue(":registration_ip", $data['registration_ip'], SQLITE3_TEXT);
        $stmt->bindValue(":last_login_at", $data['last_login_at'], SQLITE3_INTEGER);
        $stmt->bindValue(":blocked_until", $data['blocked_until'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(":must_change_password", (int)($data['must_change_password'] ?? false), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function createSession(string $playerName, string $ipAddress, string $deviceId, int $lifetimeSeconds): string {
        $sessionId = bin2hex(random_bytes(16)); // Generate a random 32-char hex string
        $loginTime = time();
        $expirationTime = $loginTime + $lifetimeSeconds;

        $stmt = $this->db->prepare("INSERT INTO sessions (session_id, player_name, ip_address, device_id, login_time, last_activity, expiration_time) VALUES (:session_id, :player_name, :ip_address, :device_id, :login_time, :last_activity, :expiration_time)");
        $stmt->bindValue(":session_id", $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(":player_name", strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(":ip_address", $ipAddress, SQLITE3_TEXT);
        $stmt->bindValue(":device_id", $deviceId, SQLITE3_TEXT);
        $stmt->bindValue(":login_time", $loginTime, SQLITE3_INTEGER);
        $stmt->bindValue(":last_activity", $loginTime, SQLITE3_INTEGER);
        $stmt->bindValue(":expiration_time", $expirationTime, SQLITE3_INTEGER);
        $stmt->execute();
        return $sessionId;
    }

    public function getSession(string $sessionId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE session_id = :session_id AND expiration_time > :current_time");
        $stmt->bindValue(":session_id", $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(":current_time", time(), SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getSessionsByPlayer(string $playerName): array {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE player_name = :player_name AND expiration_time > :current_time ORDER BY login_time DESC");
        $stmt->bindValue(":player_name", strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(":current_time", time(), SQLITE3_INTEGER);
        $result = $stmt->execute();
        $sessions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $sessions[$row['session_id']] = $row;
        }
        return $sessions;
    }

    public function deleteSession(string $sessionId): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = :session_id");
        $stmt->bindValue(":session_id", $sessionId, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function deleteAllSessionsForPlayer(string $playerName): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE player_name = :player_name");
        $stmt->bindValue(":player_name", strtolower($playerName), SQLITE3_TEXT);
        $stmt->execute();
    }

    public function updateSessionLastActivity(string $sessionId): void {
        $stmt = $this->db->prepare("UPDATE sessions SET last_activity = :current_time WHERE session_id = :session_id");
        $stmt->bindValue(":current_time", time(), SQLITE3_INTEGER);
        $stmt->bindValue(":session_id", $sessionId, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): void {
        $stmt = $this->db->prepare("UPDATE sessions SET expiration_time = :expiration_time WHERE session_id = :session_id");
        $stmt->bindValue(":expiration_time", time() + $newLifetimeSeconds, SQLITE3_INTEGER);
        $stmt->bindValue(":session_id", $sessionId, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function cleanupExpiredSessions(): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expiration_time <= :current_time");
        $stmt->bindValue(":current_time", time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function getRegistrationCountByIp(string $ipAddress): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM players WHERE registration_ip = :ip");
        $stmt->bindValue(":ip", $ipAddress, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) ? (int)($row['count'] ?? 0) : 0;
    }

    public function close(): void {
        $this->db->close();
    }
}
