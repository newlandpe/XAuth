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

namespace Luthfi\XAuth\tasks;

use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class SendTitleTask extends Task {

    private Main $plugin;
    private Player $player;
    private string $title;
    private string $subtitle;

    public function __construct(Main $plugin, Player $player, string $title, string $subtitle) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    public function onRun(): void {
        if ($this->player->isOnline()) {
            $this->player->sendTitle($this->title, $this->subtitle, 10, 40, 10);
        }
    }
}
