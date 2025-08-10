<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use Luthfi\XAuth\PlayerState;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called after a player's state (inventory, position, etc.) has been saved
 * because they need to authenticate.
 */
class PlayerStateSaveEvent extends PlayerEvent {

    private PlayerState $state;

    public function __construct(Player $player, PlayerState $state) {
        $this->player = $player;
        $this->state = $state;
    }

    /**
     * Returns a read-only copy of the state that was saved.
     */
    public function getState(): PlayerState {
        return clone $this->state;
    }
}
