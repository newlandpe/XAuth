<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when a player successfully registers an account.
 */
class PlayerRegisterEvent extends PlayerEvent {

    public function __construct(Player $player) {
        $this->player = $player;
    }
}
