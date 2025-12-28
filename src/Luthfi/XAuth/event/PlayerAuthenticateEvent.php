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

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called after a player has been fully authenticated and their state is restored.
 * This event is for notification purposes and cannot be cancelled.
 */
class PlayerAuthenticateEvent extends PlayerEvent {

    private string $loginType;

    public function __construct(Player $player, string $loginType = PlayerPreAuthenticateEvent::LOGIN_TYPE_MANUAL) {
        $this->player = $player;
        $this->loginType = $loginType;
    }

    public function getLoginType(): string {
        return $this->loginType;
    }
}
