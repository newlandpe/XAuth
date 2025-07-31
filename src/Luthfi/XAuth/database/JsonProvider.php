<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use Luthfi\XAuth\Main;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class JsonProvider implements DataProviderInterface {

    private Config $playerData;

    public function __construct(Main $plugin) {
        $this->playerData = new Config($plugin->getDataFolder() . "players.json", Config::JSON);
    }

    public function getPlayer(Player|OfflinePlayer $player): ?array {
        $name = strtolower($player->getName());
        return $this->playerData->exists($name) ? $this->playerData->get($name) : null;
    }

    public function isPlayerRegistered(string $playerName): bool {
        return $this->playerData->exists(strtolower($playerName));
    }

    public function registerPlayer(Player $player, string $hashedPassword): void {
        $name = strtolower($player->getName());
        $this->playerData->set($name, [
            "password" => $hashedPassword,
            "ip" => $player->getNetworkSession()->getIp()
        ]);
        $this->playerData->save();
    }

    public function updatePlayerIp(Player $player): void {
        $name = strtolower($player->getName());
        if ($this->playerData->exists($name)) {
            $data = $this->playerData->get($name);
            $data['ip'] = $player->getNetworkSession()->getIp();
            $data['last_login_at'] = time();
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void {
        $name = strtolower($player->getName());
        if ($this->playerData->exists($name)) {
            $data = $this->playerData->get($name);
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
        if ($this->playerData->exists($name)) {
            $data = $this->playerData->get($name);
            $data['locked'] = $locked;
            $this->playerData->set($name, $data);
            $this->playerData->save();
        }
    }

    public function isPlayerLocked(string $playerName): bool {
        $name = strtolower($playerName);
        return $this->playerData->exists($name) && $this->playerData->get($name)['locked'] === true;
    }

    public function close(): void {
        $this->playerData->save();
    }
}
