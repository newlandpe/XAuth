<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use SQLite3;

class SqliteProvider implements DataProviderInterface {

    private SQLite3 $db;

    public function __construct(Main $plugin) {
        $this->db = new SQLite3($plugin->getDataFolder() . "players.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS players (name TEXT PRIMARY KEY, password TEXT, ip TEXT, locked INTEGER DEFAULT 0, registered_at INTEGER, registration_ip TEXT, last_login_at INTEGER)");
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("SELECT * FROM players WHERE name = :name");
        if ($stmt === false) return null;
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return null;
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function isPlayerRegistered(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT name FROM players WHERE name = :name");
        if ($stmt === false) return false;
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return false;
        return $result->fetchArray() !== false;
    }

    public function registerPlayer(Player $player, string $hashedPassword): void {
        $name = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        $stmt = $this->db->prepare("INSERT INTO players (name, password, ip, locked, registered_at, registration_ip, last_login_at) VALUES (:name, :password, :ip, :locked, :registered_at, :registration_ip, :last_login_at)");
        if ($stmt === false) return;
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
        if ($stmt === false) return;
        $stmt->bindValue(":last_login_at", time(), SQLITE3_INTEGER);
        $stmt->bindValue(":ip", $ip, SQLITE3_TEXT);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void {
        $name = strtolower($player->getName());
        $stmt = $this->db->prepare("UPDATE players SET password = :password WHERE name = :name");
        if ($stmt === false) return;
        $stmt->bindValue(":password", $newHashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function unregisterPlayer(string $playerName): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("DELETE FROM players WHERE name = :name");
        if ($stmt === false) return;
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function setPlayerLocked(string $playerName, bool $locked): void {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("UPDATE players SET locked = :locked WHERE name = :name");
        if ($stmt === false) return;
        $stmt->bindValue(":locked", (int)$locked, SQLITE3_INTEGER);
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function isPlayerLocked(string $playerName): bool {
        $name = strtolower($playerName);
        $stmt = $this->db->prepare("SELECT locked FROM players WHERE name = :name");
        if ($stmt === false) return false;
        $stmt->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return false;
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($row) && (bool)($row['locked'] ?? false);
    }

    public function close(): void {
        $this->db->close();
    }
}
