<?php

/*
 *
 *  _          _   _     __  __  ____ _      __  __    _         _   _
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author LuthMC
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace Luthfi\XAuth\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

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
