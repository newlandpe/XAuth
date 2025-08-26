<?php

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

/**
 * Called when a player fails to authenticate due to an incorrect password.
 */
class PlayerAuthenticationFailedEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    /** @var int */
    private int $failedAttempts;

    public function __construct(Player $player, int $failedAttempts) {
        $this->player = $player;
        $this->failedAttempts = $failedAttempts;
    }

    /**
     * Returns the total number of failed login attempts for this player
     * in the current session.
     */
    public function getFailedAttempts(): int {
        return $this->failedAttempts;
    }
}
