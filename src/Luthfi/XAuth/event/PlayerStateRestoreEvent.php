<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use Luthfi\XAuth\PlayerState;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class PlayerStateRestoreEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    private PlayerState $state;

    public function __construct(Player $player, PlayerState $state) {
        $this->player = $player;
        $this->state = $state;
    }

    /**
     * Returns the state that is about to be restored.
     * You can modify this object, but it's not recommended.
     */
    public function getState(): PlayerState {
        return $this->state;
    }
}
