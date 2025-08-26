<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called after a player has been fully authenticated and their state is restored.
 * This event is for notification purposes and cannot be cancelled.
 */
class PlayerAuthenticateEvent extends PlayerEvent {

    public function __construct(Player $player) {
        $this->player = $player;
    }
}
