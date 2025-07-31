<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use Luthfi\XAuth\event\PlayerRegisterEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class RegisterCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("register", "Register your account", "/register <password> <confirmpassword>");
        $this->setPermission("xauth.command.register");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["command_only_in_game"])) {
                $sender->sendMessage((string)$messages["command_only_in_game"]);
            }
            return false;
        }

        $name = strtolower($sender->getName());
        if (count($args) !== 2) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["register_usage"])) {
                $sender->sendMessage((string)$messages["register_usage"]);
            }
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData !== null) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["already_registered"])) {
                $sender->sendMessage((string)$messages["already_registered"]);
            }
            return false;
        }

        $password = (string)($args[0] ?? '');
        $confirmPassword = (string)($args[1] ?? '');

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($password)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        if ($password !== $confirmPassword) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["password_mismatch"])) {
                $sender->sendMessage((string)$messages["password_mismatch"]);
            }
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->plugin->getDataProvider()->registerPlayer($sender, $hashedPassword);

        (new PlayerRegisterEvent($sender))->call();

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        $messages = $this->plugin->getCustomMessages()->get("messages");
        if (is_array($messages) && isset($messages["register_success"])) {
            $sender->sendMessage((string)$messages["register_success"]);
        }
        return true;
    }
}
