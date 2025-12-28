<?php

/*
 * 
 *  _          _   _     __  __  ____ _      __  __    _         _   _
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
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

use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;

interface DataProviderInterface {

    public function initialize(): \Generator;

    public function getPlayer(Player|OfflinePlayer $player): \Generator;

    public function isPlayerRegistered(string $playerName): \Generator;

    public function registerPlayer(Player $player, string $hashedPassword): \Generator;

    public function updatePlayerIp(Player $player): \Generator;

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): \Generator;

    public function unregisterPlayer(string $playerName): \Generator;

    public function setPlayerLocked(string $playerName, bool $locked): \Generator;

    public function isPlayerLocked(string $playerName): \Generator;

    public function setBlockedUntil(string $playerName, int $timestamp): \Generator;

    public function getBlockedUntil(string $playerName): \Generator;

    public function setMustChangePassword(string $playerName, bool $required): \Generator;

    public function mustChangePassword(string $playerName): \Generator;

    public function getTotalPlayerCount(): \Generator;

    public function getPlayerDataPaged(int $limit, int $offset): \Generator;

    public function getAllPlayerData(): \Generator;

    public function registerPlayerRaw(string $playerName, array $data): \Generator;

    public function createSession(string $playerName, string $ipAddress, string $deviceId, int $lifetimeSeconds): \Generator;

    public function getSession(string $sessionId): \Generator;

    public function getSessionsByPlayer(string $playerName): \Generator;

    public function deleteSession(string $sessionId): \Generator;

    public function deleteAllSessionsForPlayer(string $playerName): \Generator;

    public function updateSessionLastActivity(string $sessionId): \Generator;

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): \Generator;

    public function cleanupExpiredSessions(): \Generator;

    public function getRegistrationCountByIp(string $ipAddress): \Generator;

    public function close(): void;
}