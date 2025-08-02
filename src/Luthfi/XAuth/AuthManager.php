<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use pocketmine\player\Player;

class AuthManager {

    private Main $plugin;

    /** @var array<string, bool> */
    private array $authenticatedPlayers = [];

    /** @var array<string, array{attempts: int, last_attempt_time: int}> */
    private array $loginAttempts = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

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

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 5);
        if ($this->loginAttempts[$name]['attempts'] >= $maxAttempts) {
            $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 10);
            $this->plugin->getDataProvider()->setBlockedUntil($name, time() + ($blockTimeMinutes * 60));
            $this->clearLoginAttempts($player);
        }
    }

    public function isPlayerBlocked(Player $player, int $maxAttempts, int $blockTimeMinutes): bool {
        $blockedUntil = $this->plugin->getDataProvider()->getBlockedUntil($player->getName());
        if ($blockedUntil > time()) {
            return true;
        }

        $name = strtolower($player->getName());
        return isset($this->loginAttempts[$name]) && $this->loginAttempts[$name]['attempts'] >= $maxAttempts;
    }

    public function getRemainingBlockTime(Player $player, int $blockTimeMinutes): int {
        $blockedUntil = $this->plugin->getDataProvider()->getBlockedUntil($player->getName());
        if ($blockedUntil > time()) {
            return (int)ceil(($blockedUntil - time()) / 60);
        }
        return 0;
    }

    public function clearLoginAttempts(Player $player): void {
        unset($this->loginAttempts[strtolower($player->getName())]);
    }

    public function isPlayerBlockedByName(string $name, int $maxAttempts, int $blockTimeMinutes): bool {
        $blockedUntil = $this->plugin->getDataProvider()->getBlockedUntil($name);
        return $blockedUntil > time();
    }

    public function getRemainingBlockTimeByName(string $name, int $blockTimeMinutes): int {
        $blockedUntil = $this->plugin->getDataProvider()->getBlockedUntil($name);
        if ($blockedUntil > time()) {
            return (int)ceil(($blockedUntil - time()) / 60);
        }
        return 0;
    }
}
