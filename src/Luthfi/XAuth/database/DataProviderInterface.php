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

    public function setBlockedUntil(string $playerName, int $timestamp): void;

    public function getBlockedUntil(string $playerName): int;

    public function setMustChangePassword(string $playerName, bool $required): void;

    public function mustChangePassword(string $playerName): bool;

    public function getAllPlayerData(): array;

    public function registerPlayerRaw(string $playerName, array $data): void;

    public function createSession(string $playerName, string $ipAddress, string $clientId, int $lifetimeSeconds): string;

    public function getSession(string $sessionId): ?array;

    public function getSessionsByPlayer(string $playerName): array;

    public function deleteSession(string $sessionId): void;

    public function deleteAllSessionsForPlayer(string $playerName): void;

    public function updateSessionLastActivity(string $sessionId): void;

    public function refreshSession(string $sessionId, int $newLifetimeSeconds): void;

    public function cleanupExpiredSessions(): void;

    public function getRegistrationCountByIp(string $ipAddress): int;

    public function close(): void;
}
