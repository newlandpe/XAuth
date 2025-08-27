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

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\event\PlayerStateRestoreEvent;
use Luthfi\XAuth\event\PlayerStateSaveEvent;
use Luthfi\XAuth\Main;
use Luthfi\XAuth\PlayerState;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;

class PlayerStateService {

    private Main $plugin;
    private PlayerVisibilityService $visibilityService;

    /** @var array<string, PlayerState> */
    private array $protectedStates = [];

    public function __construct(Main $plugin, PlayerVisibilityService $visibilityService) {
        $this->plugin = $plugin;
        $this->visibilityService = $visibilityService;
    }

    public function protectPlayer(Player $player): void {
        $this->savePlayerState($player);

        $config = $this->plugin->getConfig();
        $protectionConfig = (array)$config->get('protection');

        if ((bool)(($protectionConfig['force_survival'] ?? true))) {
            $player->setGamemode(GameMode::SURVIVAL());
        }

        $teleportConfig = (array)($protectionConfig['teleport'] ?? []);
        if ((bool)($teleportConfig['enabled'] ?? false)) {
            $worldName = (string)(($teleportConfig['world'] ?? $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()));
            if ($world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName)) {
                $coords = (array)($teleportConfig['coords'] ?? []);
                $x = (float)(($coords['x'] ?? $world->getSafeSpawn()->getX()));
                $y = (float)(($coords['y'] ?? $world->getSafeSpawn()->getY()));
                $z = (float)(($coords['z'] ?? $world->getSafeSpawn()->getZ()));
                $player->teleport(new Position($x, $y, $z, $world));
            }
        }

        if ((bool)(($protectionConfig['protect_player_state'] ?? true))) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();
            $player->getEffects()->clear();
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
            $player->getXpManager()->setXpLevel(0);
            $player->getXpManager()->setXpProgress(0.0);
        }

        if ((bool)$this->plugin->getConfig()->get('apply_blindness', true)) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 2147483647, 0, false));
        }

        $this->visibilityService->updatePlayerVisibility($player);
    }

    public function savePlayerState(Player $player): void {
        $state = new PlayerState($player, $this->plugin);
        $this->protectedStates[strtolower($player->getName())] = $state;
        (new PlayerStateSaveEvent($player, $state))->call();
    }

    public function restorePlayerState(Player $player): void {
        $name = strtolower($player->getName());
        if (isset($this->protectedStates[$name])) {
            $event = new PlayerStateRestoreEvent($player, $this->protectedStates[$name]);
            $event->call();
            if ($event->isCancelled()) {
                unset($this->protectedStates[$name]);
                return;
            }
            $this->protectedStates[$name]->restore($player);
            unset($this->protectedStates[$name]);
        }
    }

    public function removePlayerState(Player $player): void {
        unset($this->protectedStates[strtolower($player->getName())]);
    }
}
