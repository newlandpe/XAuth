<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when a player is deauthenticated (logged out).
 * This can happen via the /logout command or when the player quits the server.
 */
class PlayerDeauthenticateEvent extends PlayerEvent {

    private bool $isQuit;

    public function __construct(Player $player, bool $isQuit = false) {
        $this->player = $player;
        $this->isQuit = $isQuit;
    }

    public function isQuit(): bool {
        return $this->isQuit;
    }
}
