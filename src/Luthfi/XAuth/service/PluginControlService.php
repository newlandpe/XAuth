<?php

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
