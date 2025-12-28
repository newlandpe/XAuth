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
 * Called when a player successfully authenticates (e.g. by password).
 * This event can be cancelled by other plugins (e.g. 2FA).
 */
class PlayerPreAuthenticateEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    public const LOGIN_TYPE_MANUAL = 'manual';
    public const LOGIN_TYPE_AUTO = 'auto';
    public const LOGIN_TYPE_REGISTRATION = 'registration';

    private string $loginType;
    private ?string $kickMessage = null;

    public function __construct(Player $player, string $loginType = self::LOGIN_TYPE_MANUAL) {
        $this->player = $player;
        $this->loginType = $loginType;
    }

    public function getLoginType(): string {
        return $this->loginType;
    }

    public function disallow(?string $kickMessage = null): void {
        $this->kickMessage = $kickMessage;
        $this->cancel();
    }

    public function getKickMessage(): ?string {
        return $this->kickMessage;
    }
}
