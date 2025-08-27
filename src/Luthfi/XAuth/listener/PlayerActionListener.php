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

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\event\PlayerAuthActionEvent;
use Luthfi\XAuth\Main;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;

class PlayerActionListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    private function handleAction(Player $player, string $actionType, Cancellable $event): void {
        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            return;
        }

        $restrictions = (array)$this->plugin->getConfig()->get("restrictions");

        $configKeyMap = [
            PlayerAuthActionEvent::ACTION_MOVE => 'allow_movement',
            PlayerAuthActionEvent::ACTION_CHAT => 'allow_chat',
            PlayerAuthActionEvent::ACTION_BLOCK_BREAK => 'allow_block_breaking',
            PlayerAuthActionEvent::ACTION_BLOCK_PLACE => 'allow_block_placing',
            PlayerAuthActionEvent::ACTION_INTERACT => 'allow_block_interaction',
            PlayerAuthActionEvent::ACTION_ITEM_USE => 'allow_item_use',
            PlayerAuthActionEvent::ACTION_DROP_ITEM => 'allow_item_dropping',
            PlayerAuthActionEvent::ACTION_PICKUP_ITEM => 'allow_item_pickup',
            PlayerAuthActionEvent::ACTION_INVENTORY_CHANGE => 'allow_inventory_open',
            PlayerAuthActionEvent::ACTION_INVENTORY_TRANSACTION => 'allow_inventory_transaction',
            PlayerAuthActionEvent::ACTION_CRAFT => 'allow_crafting',
            PlayerAuthActionEvent::ACTION_DAMAGE_RECEIVE => 'allow_damage_receive',
            PlayerAuthActionEvent::ACTION_DAMAGE_DEAL => 'allow_damage_deal',
        ];

        $configKey = $configKeyMap[$actionType] ?? null;

        if ($configKey === null) {
            $this->plugin->getLogger()->warning("Unknown action type or no direct config mapping for: " . $actionType);
            return;
        }

        $allowAction = (bool)($restrictions[$configKey] ?? true); // Default to true if not set in config

        if ($this->plugin->getAuthenticationService()->isForcingPasswordChange($player)) {
            if (!$allowAction) {
                $event->cancel();
                return;
            }
            return;
        }

        if (!$allowAction) {
            $event->cancel();
            return;
        }

        $authEvent = new PlayerAuthActionEvent($player, $actionType);
        $authEvent->call();

        if ($authEvent->isCancelled()) {
            $event->cancel();
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_MOVE, $event);
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();

        $this->handleAction($player, PlayerAuthActionEvent::ACTION_CHAT, $event);
        if ($event->isCancelled()) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["chat_not_allowed"] ?? "");
            if (!empty($message)) {
                $player->sendMessage($message);
            }
        }
    }

    public function onCommand(CommandEvent $event): void {
        $player = $event->getSender();
        if (!$player instanceof Player) {
            return;
        }

        if ($this->plugin->getAuthenticationService()->isForcingPasswordChange($player)) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["force_change_password_prompt"] ?? "");
            if (!empty($message)) {
                $player->sendMessage($message);
            }
            $event->cancel();
            return;
        }

        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            return;
        }

        $restrictions = (array)$this->plugin->getConfig()->get("restrictions");
        $command = strtolower(explode(' ', $event->getCommand())[0]);
        $allowedCommands = array_map('strtolower', (array)($restrictions['allowed_commands'] ?? []));

        if (in_array($command, $allowedCommands, true)) {
            return;
        }

        $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_COMMAND);
        $authEvent->call();
        if ($authEvent->isCancelled()) {
            return; // Allow other plugins to handle it
        }

        $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["command_not_allowed"] ?? "");
        if (!empty($message)) {
            $player->sendMessage($message);
        }
        $event->cancel();
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_INTERACT, $event);
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_DROP_ITEM, $event);
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $victim = $event->getEntity();
        if ($victim instanceof Player) {
            $this->handleAction($victim, PlayerAuthActionEvent::ACTION_DAMAGE_RECEIVE, $event);
            if ($event->isCancelled()) {
                return;
            }
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof Player) {
                $this->handleAction($damager, PlayerAuthActionEvent::ACTION_DAMAGE_DEAL, $event);
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_BLOCK_BREAK, $event);
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_BLOCK_PLACE, $event);
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_ITEM_USE, $event);
    }

    public function onEntityItemPickup(EntityItemPickupEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $this->handleAction($entity, PlayerAuthActionEvent::ACTION_PICKUP_ITEM, $event);
        }
    }

    public function onInventoryOpen(InventoryOpenEvent $event): void {
        $this->handleAction($event->getPlayer(), PlayerAuthActionEvent::ACTION_INVENTORY_CHANGE, $event);
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $player = $event->getTransaction()->getSource();
        if ($player instanceof Player) {
            $this->handleAction($player, PlayerAuthActionEvent::ACTION_INVENTORY_TRANSACTION, $event);
        }
    }

    public function onCraftItem(CraftItemEvent $event): void {
        $player = $event->getPlayer();
        $this->handleAction($player, PlayerAuthActionEvent::ACTION_CRAFT, $event);
    }
}
