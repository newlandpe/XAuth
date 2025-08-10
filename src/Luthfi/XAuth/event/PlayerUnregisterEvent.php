<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\Event;
use pocketmine\player\IPlayer;

/**
 * Called when a player unregisters their account.
 * Note: This event uses IPlayer, so the player may be offline when this is called.
 */
class PlayerUnregisterEvent extends Event {

    protected IPlayer $player;

    public function __construct(IPlayer $player) {
        $this->player = $player;
    }

    public function getPlayer(): IPlayer {
        return $this->player;
    }
}
