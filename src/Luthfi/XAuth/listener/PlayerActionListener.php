<?php

declare(strict_types=1);

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\event\PlayerAuthActionEvent;
use Luthfi\XAuth\Main;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;

class PlayerActionListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        if ($this->plugin->isForcingPasswordChange($player)) {
            $event->cancel();
            return;
        }
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_MOVE);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onCommand(CommandEvent $event): void {
        $player = $event->getSender();
        if (!$player instanceof Player) {
            return;
        }

        if ($this->plugin->isForcingPasswordChange($player)) {
            $player->sendMessage((string)(((array)$this->plugin->getCustomMessages()->get("messages"))["force_change_password_prompt"] ?? ""));
            $event->cancel();
            return;
        }

        if ($this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            return;
        }

        $commandParts = explode(' ', $event->getCommand());
        $commandMap = $this->plugin->getServer()->getCommandMap();
        $command = $commandMap->getCommand($commandParts[0]);

        if ($command !== null) {
            $allowedCommands = ['login', 'register'];
            if (in_array($command->getName(), $allowedCommands, true)) {
                return;
            }
            foreach ($command->getAliases() as $alias) {
                if (in_array($alias, $allowedCommands, true)) {
                    return;
                }
            }
        }

        $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_COMMAND);
        $authEvent->call();
        if ($authEvent->isCancelled()) {
            return;
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        if (isset($messages["command_not_allowed"])) {
            $player->sendMessage((string)$messages["command_not_allowed"]);
        }
        $event->cancel();
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_CHAT);
            $authEvent->call();
            if ($authEvent->isCancelled()) {
                return;
            }
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            if (isset($messages["chat_not_allowed"])) {
                $player->sendMessage((string)$messages["chat_not_allowed"]);
            }
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_INTERACT);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_DROP_ITEM);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && !$this->plugin->getAuthManager()->isPlayerAuthenticated($entity)) {
            $authEvent = new PlayerAuthActionEvent($entity, PlayerAuthActionEvent::ACTION_DAMAGE);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onEntityItemPickup(EntityItemPickupEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && !$this->plugin->getAuthManager()->isPlayerAuthenticated($entity)) {
            $authEvent = new PlayerAuthActionEvent($entity, PlayerAuthActionEvent::ACTION_PICKUP_ITEM);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_BLOCK_BREAK);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_BLOCK_PLACE);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_ITEM_USE);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }

    public function onInventoryOpen(InventoryOpenEvent $event): void {
        $player = $event->getPlayer();
        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            $authEvent = new PlayerAuthActionEvent($player, PlayerAuthActionEvent::ACTION_INVENTORY_CHANGE);
            $authEvent->call();
            if (!$authEvent->isCancelled()) {
                $event->cancel();
            }
        }
    }
}
