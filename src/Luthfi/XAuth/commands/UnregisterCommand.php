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

use Luthfi\XAuth\exception\ConfirmationExpiredException;
use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\UnregistrationNotInitiatedException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use Throwable;

class UnregisterCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(
        private readonly Main $plugin
    ) {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "unregister",
            (string)($messages["unregister_command_description"] ?? "Unregister your account."),
            (string)($messages["unregister_command_usage"] ?? "/unregister [confirm <password>]")
        );
        $this->setPermission("xauth.command.unregister");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        $commandSettings = (array)$this->plugin->getConfig()->get("command_settings");
        if (isset($commandSettings['allow_player_self_unregister']) && $commandSettings['allow_player_self_unregister'] === false) {
            $sender->sendMessage((string)($messages["unregister_disabled"] ?? "§cAccount unregistration is disabled on this server."));
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

        if (isset($args[0]) && strtolower($args[0]) === 'confirm') {
            if (!isset($args[1])) {
                $sender->sendMessage((string)($messages["unregister_password_missing"] ?? "§cUsage: /unregister confirm <password>"));
                return false;
            }
            $password = $args[1];

            Await::g2c(
                $this->plugin->getRegistrationService()->confirmUnregistration($sender, $password),
                static function(): void {
                    // Success is handled by the service (player is kicked)
                },
                function(Throwable $e) use ($sender, $messages): void {
                    switch (true) {
                        case $e instanceof UnregistrationNotInitiatedException:
                            $sender->sendMessage((string)($messages["unregister_not_initiated"] ?? "§cYou have not started the unregistration process. Type /unregister first."));
                            break;
                        case $e instanceof ConfirmationExpiredException:
                            $sender->sendMessage((string)($messages["unregister_confirmation_expired"] ?? "§cUnregistration confirmation expired. Please start over."));
                            break;
                        case $e instanceof IncorrectPasswordException:
                            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password."));
                            break;
                        default:
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
                            $this->plugin->getLogger()->error("An unexpected error occurred during unregistration confirmation: " . $e->getMessage());
                            break;
                    }
                }
            );
        } else {
            $this->plugin->getRegistrationService()->initiateUnregistration($sender);
            $sender->sendMessage((string)($messages["unregister_initiate"] ?? "§eAre you sure you want to unregister? This action is irreversible.§r\n§eType §f/unregister confirm <password>§e within 60 seconds to confirm."));
        }
        return true;
    }
}
