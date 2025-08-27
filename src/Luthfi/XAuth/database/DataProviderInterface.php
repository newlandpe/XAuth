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

use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

interface DataProviderInterface {

    public function getPlayer(Player|OfflinePlayer $player): Await;

    public function isPlayerRegistered(string $playerName): Await;

    public function registerPlayer(Player $player, string $hashedPassword): Await;

    public function updatePlayerIp(Player $player): Await;

    public function changePassword(Player|OfflinePlayer $player, string $newHashedPassword): Await;

    public function unregisterPlayer(string $playerName): Await;

    public function setPlayerLocked(string $playerName, bool $locked): Await;

    public function isPlayerLocked(string $playerName): Await;

    public function setBlockedUntil(string $playerName, int $timestamp): Await;

    public function getBlockedUntil(string $playerName): Await;

    public function setMustChangePassword(string $playerName, bool $required): Await;

    public function mustChangePassword(string $playerName): Await;

    public function getAllPlayerData(): Await;

    public function registerPlayerRaw(string $playerName, array $data): Await;

    public function createSession(string $playerName, string $ipAddress, string $deviceId, int $lifetimeSeconds): Await;

    public function getSession(string $sessionId): Await;

    public function getSessionsByPlayer(string $playerName): Await;

    public function deleteSession(string $sessionId): Await;

    public function deleteAllSessionsForPlayer(string $playerName): Await;

    public function updateSessionLastActivity(string $sessionId): Await;

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): Await;

    public function cleanupExpiredSessions(): Await;

    public function getRegistrationCountByIp(string $ipAddress): Await;

    public function close(): void;
}
