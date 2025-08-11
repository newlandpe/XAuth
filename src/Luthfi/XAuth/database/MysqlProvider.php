<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use PDO;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;

class MysqlProvider implements DataProviderInterface {

    private PDO $db;
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $config = $plugin->getConfig()->get('database');
        if (!is_array($config)) {
            $config = [];
        }
        $mysqlConfig = (array)($config['mysql'] ?? []);

        $host = (string)($mysqlConfig['host'] ?? '127.0.0.1');
        $port = (int)($mysqlConfig['port'] ?? 3306);
        $database = (string)($mysqlConfig['database'] ?? 'xauth');
        $user = (string)($mysqlConfig['user'] ?? 'root');
        $password = (string)($mysqlConfig['password'] ?? '');

        $this->db = new PDO("mysql:host=" . $host . ";port=" . $port . ";dbname=" . $database, $user, $password);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("CREATE TABLE IF NOT EXISTS players (
            name VARCHAR(255) PRIMARY KEY,
            password VARCHAR(255),
            ip VARCHAR(255),
            locked BOOLEAN DEFAULT FALSE,
            registered_at INT,
            registration_ip VARCHAR(255),
            last_login_at INT,
            blocked_until INT DEFAULT 0,
            must_change_password BOOLEAN DEFAULT FALSE
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS sessions (
            session_id VARCHAR(255) PRIMARY KEY,
            player_name VARCHAR(255) NOT NULL,
            ip_address VARCHAR(255),
            login_time INT,
            last_activity INT,
            expiration_time INT,
            FOREIGN KEY (player_name) REFERENCES players(name) ON DELETE CASCADE
        )");
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("SELECT * FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    public function isPlayerRegistered(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT name FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    public function registerPlayer(Player $player, string $hashedPassword): void {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        $stmt = $this->db->prepare("INSERT INTO players (name, password, ip, registered_at, registration_ip, last_login_at) VALUES (:name, :password, :ip, :registered_at, :registration_ip, :last_login_at)");
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":password", $hashedPassword);
        $stmt->bindValue(":ip", $ip);
        $stmt->bindValue(":registered_at", time());
        $stmt->bindValue(":registration_ip", $ip);
        $stmt->bindValue(":last_login_at", time());
        $stmt->execute();
    }

    public function updatePlayerIp(Player $player): void {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        $stmt = $this->db->prepare("UPDATE players SET ip = :ip, last_login_at = :last_login_at WHERE name = :name");
        $stmt->bindValue(":last_login_at", time());
        $stmt->bindValue(":ip", $ip);
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("UPDATE players SET password = :password WHERE name = :name");
        $stmt->bindValue(":password", $newHashedPassword);
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function unregisterPlayer(string $playerName): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("DELETE FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function setPlayerLocked(string $playerName, bool $locked): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET locked = :locked WHERE name = :name");
        $stmt->bindValue(":locked", $locked, PDO::PARAM_BOOL);
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function isPlayerLocked(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT locked FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) && (bool)($result['locked'] ?? false);
    }

    public function setBlockedUntil(string $playerName, int $timestamp): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET blocked_until = :timestamp WHERE name = :name");
        $stmt->bindValue(":timestamp", $timestamp, PDO::PARAM_INT);
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function getBlockedUntil(string $playerName): int {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT blocked_until FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? (int)($result['blocked_until'] ?? 0) : 0;
    }

    public function setMustChangePassword(string $playerName, bool $required): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET must_change_password = :required WHERE name = :name");
        $stmt->bindValue(":required", $required, PDO::PARAM_BOOL);
        $stmt->bindValue(":name", $name);
        $stmt->execute();
    }

    public function mustChangePassword(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT must_change_password FROM players WHERE name = :name");
        $stmt->bindValue(":name", $name);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) && (bool)($result['must_change_password'] ?? false);
    }

    public function getAllPlayerData(): array {
        $stmt = $this->db->query("SELECT * FROM players");
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[strtolower($row['name'])] = $row;
        }
        return $data;
    }

    public function registerPlayerRaw(string $playerName, array $data): void {
        $stmt = $this->db->prepare("INSERT IGNORE INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at, blocked_until, must_change_password) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at, :blocked_until, :must_change_password)");
        $stmt->bindValue(":name", strtolower($playerName));
        $stmt->bindValue(":password", $data['password']);
        $stmt->bindValue(":ip", $data['ip']);
        $stmt->bindValue(":locked", (bool)($data['locked'] ?? false), PDO::PARAM_BOOL);
        $stmt->bindValue(":registered_at", $data['registered_at'], PDO::PARAM_INT);
        $stmt->bindValue(":registration_ip", $data['registration_ip']);
        $stmt->bindValue(":last_login_at", $data['last_login_at'], PDO::PARAM_INT);
        $stmt->bindValue(":blocked_until", $data['blocked_until'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(":must_change_password", (bool)($data['must_change_password'] ?? false), PDO::PARAM_BOOL);
        $stmt->execute();
    }

    public function createSession(string $playerName, string $ipAddress, string $clientId, int $lifetimeSeconds): string {
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

        $stmt = $this->db->prepare("INSERT INTO sessions (session_id, player_name, ip_address, client_id, login_time, last_activity, expiration_time) VALUES (:session_id, :player_name, :ip_address, :client_id, :login_time, :last_activity, :expiration_time)");
        $stmt->bindValue(":session_id", $sessionId);
        $stmt->bindValue(":player_name", $playerNameLower);
        $stmt->bindValue(":ip_address", $ipAddress);
        $stmt->bindValue(":client_id", $clientId);
        $stmt->bindValue(":login_time", $loginTime, PDO::PARAM_INT);
        $stmt->bindValue(":last_activity", $loginTime, PDO::PARAM_INT);
        $stmt->bindValue(":expiration_time", $expirationTime, PDO::PARAM_INT);
        $stmt->execute();
        return $sessionId;
    }

    public function getSession(string $sessionId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE session_id = :session_id AND expiration_time > :current_time");
        $stmt->bindValue(":session_id", $sessionId);
        $stmt->bindValue(":current_time", time(), PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    public function getSessionsByPlayer(string $playerName): array {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE player_name = :player_name AND expiration_time > :current_time ORDER BY login_time DESC");
        $stmt->bindValue(":player_name", strtolower($playerName));
        $stmt->bindValue(":current_time", time(), PDO::PARAM_INT);
        $stmt->execute();
        $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[$row['session_id']] = $row;
        }
        return $sessions;
    }

    public function deleteSession(string $sessionId): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = :session_id");
        $stmt->bindValue(":session_id", $sessionId);
        $stmt->execute();
    }

    public function deleteAllSessionsForPlayer(string $playerName): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE player_name = :player_name");
        $stmt->bindValue(":player_name", strtolower($playerName));
        $stmt->execute();
    }

    public function updateSessionLastActivity(string $sessionId): void {
        $stmt = $this->db->prepare("UPDATE sessions SET last_activity = :current_time WHERE session_id = :session_id");
        $stmt->bindValue(":current_time", time(), PDO::PARAM_INT);
        $stmt->bindValue(":session_id", $sessionId);
        $stmt->execute();
    }

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): void {
        $stmt = $this->db->prepare("UPDATE sessions SET expiration_time = :expiration_time WHERE session_id = :session_id");
        $stmt->bindValue(":expiration_time", time() + $newLifetimeSeconds, PDO::PARAM_INT);
        $stmt->bindValue(":session_id", $sessionId);
        $stmt->execute();
    }

    public function cleanupExpiredSessions(): void {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expiration_time <= :current_time");
        $stmt->bindValue(":current_time", time(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getRegistrationCountByIp(string $ipAddress): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM players WHERE registration_ip = :ip");
        $stmt->bindValue(":ip", $ipAddress);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? (int)($result['count'] ?? 0) : 0;
    }

    public function close(): void {
        // PDO does not need to be closed explicitly
    }
}
