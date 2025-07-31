<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class YamlProvider implements DataProviderInterface {

    private Config $playerData;

    public function __construct(Main $plugin) {
        $this->playerData = new Config($plugin->getDataFolder() . "players.yml", Config::YAML);
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array<string, mixed> {
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
            "locked" => false
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

    public function close(): void {
        $this->playerData->save();
    }
}
