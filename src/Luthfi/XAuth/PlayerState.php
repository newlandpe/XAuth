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

namespace Luthfi\XAuth;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;

class PlayerState {

    private array $inventoryContents;
    private array $armorInventoryContents;
    private array $offHandInventoryContents;
    private array $effects;
    private float $health;
    private float $food;
    private int $xpLevel;
    private float $xpProgress;
    private GameMode $gamemode;
    private Position $position;
    private bool $canFly;
    private bool $isFlying;
    private Main $plugin;

    public function __construct(Player $player, Main $plugin) {
        $this->plugin = $plugin;
        $this->inventoryContents = $player->getInventory()->getContents();
        $this->armorInventoryContents = $player->getArmorInventory()->getContents();
        $this->offHandInventoryContents = $player->getOffHandInventory()->getContents();
        $this->effects = [];
        foreach ($player->getEffects()->all() as $effect) {
            $this->effects[] = $effect;
        }
        $this->health = $player->getHealth();
        $this->food = $player->getHungerManager()->getFood();
        $this->xpLevel = $player->getXpManager()->getXpLevel();
        $this->xpProgress = $player->getXpManager()->getXpProgress();
        $this->gamemode = $player->getGamemode();
        $this->position = $player->getPosition();
        $this->canFly = $player->getAllowFlight();
        $this->isFlying = $player->isFlying();
    }

    public function restore(Player $player): void {
        if ((bool)($this->plugin->getConfig()->get('apply_blindness') ?? true)) {
            $player->getEffects()->remove(VanillaEffects::BLINDNESS());
        }

        $inWorldConfig = (array)($this->plugin->getConfig()->get('in_world_visibility') ?? []);

        if (strtolower((string)($inWorldConfig['mode'] ?? 'packets')) === 'effect') {
            $player->getEffects()->remove(VanillaEffects::INVISIBILITY());
        }

        $player->getInventory()->setContents($this->inventoryContents);
        $player->getArmorInventory()->setContents($this->armorInventoryContents);
        $player->getOffHandInventory()->setContents($this->offHandInventoryContents);
        $player->getEffects()->clear();
        foreach ($this->effects as $effect) {
            $player->getEffects()->add($effect);
        }
        $player->setHealth($this->health);
        $player->getHungerManager()->setFood($this->food);
        $player->getXpManager()->setXpLevel($this->xpLevel);
        $player->getXpManager()->setXpProgress($this->xpProgress);
        $player->setGamemode($this->gamemode);
        $player->setAllowFlight($this->canFly);
        $player->setFlying($this->isFlying);

        $protectionConfig = (array)($this->plugin->getConfig()->get('protection') ?? []);
        $teleportConfig = (array)($protectionConfig['teleport'] ?? []);

        if ((bool)($teleportConfig['return_to_original_position'] ?? true)) {
            $player->teleport($this->position);
        }
    }
}
