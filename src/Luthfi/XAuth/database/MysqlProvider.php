<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use PDO;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;

class MysqlProvider implements DataProviderInterface {

    private PDO $db;

    public function __construct(Main $plugin) {
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
        $this->db->exec("CREATE TABLE IF NOT EXISTS players (name VARCHAR(255) PRIMARY KEY, password VARCHAR(255), ip VARCHAR(255), locked BOOLEAN DEFAULT FALSE, registered_at INT, registration_ip VARCHAR(255), last_login_at INT)");
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

    public function close(): void {
        // PDO does not need to be closed explicitly
    }
}
