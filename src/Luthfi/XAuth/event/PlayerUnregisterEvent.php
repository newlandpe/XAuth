<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\Event;
use pocketmine\player\IPlayer;

class PlayerUnregisterEvent extends Event {

    protected IPlayer $player;

    public function __construct(IPlayer $player) {
        $this->player = $player;
    }

    public function getPlayer(): IPlayer {
        return $this->player;
    }
}
