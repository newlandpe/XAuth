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

use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use Throwable;

class ResetPasswordCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(
        private readonly Main $plugin
    ) {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "resetpassword",
            (string)($messages["resetpassword_command_description"] ?? "Reset your password"),
            (string)($messages["resetpassword_command_usage"] ?? "/resetpassword <old_password> <new_password> <confirm_password>")
        );
        $this->setPermission("xauth.command.resetpassword");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        $formManager = $this->plugin->getFormManager();
        if ($formManager !== null && empty($args)) {
            $formManager->sendChangePasswordForm($sender);
            return true;
        }

        if (count($args) !== 3) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $oldPassword = (string)($args[0] ?? '');
        $newPassword = (string)($args[1] ?? '');
        $confirmNewPassword = (string)($args[2] ?? '');

        Await::g2c(
            $this->plugin->getAuthenticationService()->handleChangePasswordRequest($sender, $oldPassword, $newPassword, $confirmNewPassword),
            function() use ($sender, $messages): void {
                $sender->sendMessage((string)($messages["change_password_success"] ?? "§aYour password has been changed successfully."));
            },
            function(Throwable $e) use ($sender, $messages): void {
                switch (true) {
                    case $e instanceof IncorrectPasswordException:
                        $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password."));
                        break;
                    case $e instanceof PasswordMismatchException:
                        $sender->sendMessage((string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
                        break;
                    case $e instanceof InvalidCommandSyntaxException:
                        $sender->sendMessage($e->getMessage());
                        break;
                    case $e instanceof NotRegisteredException:
                        $sender->sendMessage((string)($messages["not_registered"] ?? "§cYou are not registered."));
                        break;
                    default:
                        $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
                        $this->plugin->getLogger()->error("An unexpected error occurred during password reset command: " . $e->getMessage());
                        break;
                }
            }
        );
        return true;
    }
}
