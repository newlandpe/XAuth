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

use Luthfi\XAuth\exception\AccountLockedException;
use Luthfi\XAuth\exception\AlreadyLoggedInException;
use Luthfi\XAuth\exception\AlreadyRegisteredException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\exception\RegistrationRateLimitException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use Throwable;

class RegisterCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(
        private readonly Main $plugin
    ) {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "register",
            (string)($messages["register_command_description"] ?? "Register your account"),
            (string)($messages["register_command_usage"] ?? "/register <password> <confirm_password>")
        );
        $this->setPermission("xauth.command.register");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (count($args) !== 2) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $password = (string)($args[0] ?? '');
        $confirmPassword = (string)($args[1] ?? '');

        Await::g2c(
            $this->plugin->getRegistrationService()->handleRegistrationRequest($sender, $password, $confirmPassword),
            static function(): void {
                // Success is handled by the service
            },
            function(Throwable $e) use ($sender, $messages): void {
                switch (true) {
                    case $e instanceof AlreadyLoggedInException:
                        $sender->sendMessage((string)($messages["already_logged_in"] ?? "§cYou are already logged in."));
                        break;
                    case $e instanceof AlreadyRegisteredException:
                        $sender->sendMessage((string)($messages["already_registered"] ?? "§cYou are already registered. Please use /login <password> to log in."));
                        break;
                    case $e instanceof AccountLockedException:
                        $sender->sendMessage((string)($messages["account_locked_by_admin"] ?? "§cYour account has been locked by an administrator."));
                        break;
                    case $e instanceof RegistrationRateLimitException:
                        $sender->sendMessage((string)($messages["registration_ip_limit_reached"] ?? "§cYou have reached the maximum number of registrations for your IP address."));
                        break;
                    case $e instanceof PasswordMismatchException:
                        $sender->sendMessage((string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
                        break;
                    case $e instanceof InvalidCommandSyntaxException:
                        $sender->sendMessage($e->getMessage());
                        break;
                    default:
                        $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
                        $this->plugin->getLogger()->error("An unexpected error occurred during command registration: " . $e->getMessage());
                        break;
                }
            }
        );
        return true;
    }
}
