<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use pocketmine\player\Player;

class AuthManager {

    /** @var array<string, bool> */
    private array $authenticatedPlayers = [];

    /** @var array<string, array{attempts: int, last_attempt_time: int}> */
    private array $loginAttempts = [];

    public function authenticatePlayer(Player $player): void {
        $this->authenticatedPlayers[strtolower($player->getName())] = true;
        $this->clearLoginAttempts($player);
    }

    public function deauthenticatePlayer(Player $player): void {
        unset($this->authenticatedPlayers[strtolower($player->getName())]);
    }

    public function isPlayerAuthenticated(Player $player): bool {
        return isset($this->authenticatedPlayers[strtolower($player->getName())]);
    }

    public function incrementLoginAttempts(Player $player): void {
        $name = strtolower($player->getName());
        if (!isset($this->loginAttempts[$name])) {
            $this->loginAttempts[$name] = ['attempts' => 0, 'last_attempt_time' => 0];
        }
        $this->loginAttempts[$name]['attempts']++;
        $this->loginAttempts[$name]['last_attempt_time'] = time();
    }

    public function isPlayerBlocked(Player $player, int $maxAttempts, int $blockTimeMinutes): bool {
        $name = strtolower($player->getName());
        if (!isset($this->loginAttempts[$name]) || $this->loginAttempts[$name]['attempts'] < $maxAttempts) {
            return false;
        }

        $timeSinceLastAttempt = time() - $this->loginAttempts[$name]['last_attempt_time'];
        if ($timeSinceLastAttempt < ($blockTimeMinutes * 60)) {
            return true;
        }

        $this->clearLoginAttempts($player);
        return false;
    }

    public function getRemainingBlockTime(Player $player, int $blockTimeMinutes): int {
        $name = strtolower($player->getName());
        if (!isset($this->loginAttempts[$name])) {
            return 0;
        }

        $timePassed = time() - $this->loginAttempts[$name]['last_attempt_time'];
        $remainingTime = ($blockTimeMinutes * 60) - $timePassed;

        return max(0, (int)ceil($remainingTime / 60));
    }

    public function clearLoginAttempts(Player $player): void {
        unset($this->loginAttempts[strtolower($player->getName())]);
    }
}
