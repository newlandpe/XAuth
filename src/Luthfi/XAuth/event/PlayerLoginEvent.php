<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

class PlayerLoginEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    public function __construct(Player $player) {
        $this->player = $player;
    }
}
