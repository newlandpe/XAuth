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
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            if (isset($messages["command_only_in_game"])) {
                $sender->sendMessage((string)$messages["command_only_in_game"]);
            }
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
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            if (isset($messages["not_registered"])) {
                $sender->sendMessage((string)$messages["not_registered"]);
            }
            return false;
        }

        $oldPassword = (string)($args[0] ?? '');
        $newPassword = (string)($args[1] ?? '');

        if (!password_verify($oldPassword, (string)($playerData["password"] ?? ''))) {
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            if (isset($messages["incorrect_password"])) {
                $sender->sendMessage((string)$messages["incorrect_password"]);
            }
            return false;
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);

        (new PlayerChangePasswordEvent($sender))->call();

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        if (isset($messages["change_password_success"])) {
            $sender->sendMessage((string)$messages["change_password_success"]);
        }
        return true;
    }
}