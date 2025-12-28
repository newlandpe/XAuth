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

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\Main;
use pocketmine\utils\Config;

class PluginControlService {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function reload(): void {
        $main = $this->plugin;
        $config = $main->getConfig();

        $oldData = [
            'in_world_visibility' => (array)$config->get('in_world_visibility', []),
            'player_list_visibility' => (array)$config->get('player_list_visibility', []),
            'apply_blindness' => (bool)$config->get('apply_blindness', true)
        ];

        $config->reload();
        $main->getCustomMessages()->reload();

        $newData = [
            'in_world_visibility' => (array)$config->get('in_world_visibility', []),
            'player_list_visibility' => (array)$config->get('player_list_visibility', []),
            'apply_blindness' => (bool)$config->get('apply_blindness', true)
        ];

        if ($oldData !== $newData) {
            foreach ($main->getServer()->getOnlinePlayers() as $player) {
                $main->getPlayerVisibilityService()->updatePlayerVisibility($player);
            }
        }
    }
}
