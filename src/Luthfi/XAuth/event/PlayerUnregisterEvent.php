<?php

/*
 *
 * __  __    _         _   _
 * \ \/ /   / \  _   _| |_| |__
 *  \  /   / _ \| | | | __| '_ \
 *  /  \  / ___ \ |_| | |_| | | |
 * /_/\_\/_/   \_\__,_|\__|_| |_|
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
