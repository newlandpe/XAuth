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
        parent::__construct("resetpassword", "Reset your password", "/resetpassword <old_password> <new_password>");
        $this->plugin = $plugin;
        $this->setPermission("xauth.command.resetpassword");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["command_only_in_game"]);
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
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["not_registered"]);
            return false;
        }

        $oldPassword = $args[0];
        $newPassword = $args[1];

        if (!password_verify($oldPassword, $playerData["password"])) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["incorrect_password"]);
            return false;
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);

        (new PlayerChangePasswordEvent($sender))->call();

        $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["change_password_success"]);
        return true;
    }
}