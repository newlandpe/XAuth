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

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class LogoutCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "logout",
            (string)($messages["logout_command_description"] ?? "Logout from your account"),
            (string)($messages["logout_command_usage"] ?? "/logout")
        );
        $this->setPermission("xauth.command.logout");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $commandSettings = (array)$this->plugin->getConfig()->get("command_settings");
        if (isset($commandSettings['allow_logout_command']) && $commandSettings['allow_logout_command'] === false) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.logout_disabled") ?? "§cThe /logout command is disabled on this server."));
            return false;
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($messages["not_logged_in"] ?? "§cYou are not logged in."));
            return false;
        }

        $this->plugin->getAuthenticationService()->handleLogout($sender);

        $sender->sendMessage((string)($messages["logout_success"] ?? "§aYou have been logged out."));
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
