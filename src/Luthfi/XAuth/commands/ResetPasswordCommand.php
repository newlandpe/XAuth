<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerChangePasswordEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ResetPasswordCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "resetpassword",
            (string)($messages["resetpassword_command_description"] ?? "Reset your password"),
            (string)($messages["resetpassword_command_usage"] ?? "/resetpassword <old_password> <new_password>")
        );
        $this->plugin = $plugin;
        $this->setPermission("xauth.command.resetpassword");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        $formManager = $this->plugin->getFormManager();
        if ($formManager !== null) {
            $formManager->sendChangePasswordForm($sender);
            return true;
        }

        if (count($args) !== 2) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);
        if ($playerData === null) {
            $sender->sendMessage((string)($messages["not_registered"] ?? "§cYou are not registered."));
            return false;
        }

        $oldPassword = (string)($args[0] ?? '');
        $newPassword = (string)($args[1] ?? '');

        if (!password_verify($oldPassword, (string)($playerData["password"] ?? ''))) {
            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password."));
            return false;
        }

        if (password_needs_rehash((string)($playerData["password"] ?? ''), PASSWORD_BCRYPT)) {
            $newHashedPassword = password_hash($oldPassword, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);

        (new PlayerChangePasswordEvent($sender))->call();

        $sender->sendMessage((string)($messages["change_password_success"] ?? "§aYour password has been changed successfully."));
        return true;
    }
}
