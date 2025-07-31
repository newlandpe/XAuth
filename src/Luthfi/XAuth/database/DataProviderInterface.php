<?php

declare(strict_types=1);

namespace Luthfi\XAuth\database;

use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;

interface DataProviderInterface {

    public function getPlayer(Player|OfflinePlayer $player): ?array;

    public function isPlayerRegistered(string $playerName): bool;

    public function registerPlayer(Player $player, string $hashedPassword): void;

    public function updatePlayerIp(Player $player): void;

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): void;

    public function unregisterPlayer(string $playerName): void;

    public function setPlayerLocked(string $playerName, bool $locked): void;

    public function isPlayerLocked(string $playerName): bool;

    public function close(): void;
}
