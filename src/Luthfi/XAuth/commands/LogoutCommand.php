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

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use Throwable;

class LogoutCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(
        private readonly Main $plugin
    ) {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "logout",
            (string)($messages["logout_command_description"] ?? "Logout from your account"),
            (string)($messages["logout_command_usage"] ?? "/logout")
        );
        $this->setPermission("xauth.command.logout");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $commandSettings = (array)$this->plugin->getConfig()->get("command_settings");
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (isset($commandSettings['allow_logout_command']) && $commandSettings['allow_logout_command'] === false) {
            $sender->sendMessage((string)($messages["logout_disabled"] ?? "§cThe /logout command is disabled on this server."));
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($messages["not_logged_in"] ?? "§cYou are not logged in."));
            return false;
        }

        Await::g2c(
            $this->plugin->getAuthenticationService()->handleLogout($sender),
            function() use ($sender, $messages): void {
                $sender->sendMessage((string)($messages["logout_success"] ?? "§aYou have been logged out."));
            },
            function(Throwable $e) use ($sender, $messages): void {
                $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
                $this->plugin->getLogger()->error("An unexpected error occurred during logout: " . $e->getMessage());
            }
        );

        return true;
    }
}
