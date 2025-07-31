<?php

declare(strict_types=1);

namespace Luthfi\XAuth;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;

class EventListener implements Listener {

    private AuthManager $authManager;
    private Main $plugin;

    public function __construct(Main $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        if (!$this->authManager->isPlayerAuthenticated($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onCommand(CommandEvent $event): void {
        $player = $event->getSender();
        if (!$player instanceof Player) {
            return;
        }
        $command = strtolower(explode(' ', $event->getCommand())[0]);
        $allowedCommands = ['/login', '/register'];

        if (!$this->authManager->isPlayerAuthenticated($player) && !in_array($command, $allowedCommands, true)) {
            $player->sendMessage($this->plugin->getCustomMessages()->get("messages")["command_not_allowed"]);
            $event->cancel();
        }
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        if (!$this->authManager->isPlayerAuthenticated($event->getPlayer())) {
            $event->getPlayer()->sendMessage($this->plugin->getCustomMessages()->get("messages")["chat_not_allowed"]);
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        if (!$this->authManager->isPlayerAuthenticated($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        if (!$this->authManager->isPlayerAuthenticated($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && !$this->authManager->isPlayerAuthenticated($entity)) {
            $event->cancel();
        }
    }
}
