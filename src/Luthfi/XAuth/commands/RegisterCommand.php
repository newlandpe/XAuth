<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
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
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["command_only_in_game"]);
            return false;
        }

        $name = strtolower($sender->getName());
        if (count($args) !== 2) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("register_usage"));
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData !== null) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["already_registered"]);
            return false;
        }

        $password = $args[0];
        $confirmPassword = $args[1];

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($password)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        if ($password !== $confirmPassword) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["password_mismatch"]);
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->plugin->getDataProvider()->registerPlayer($sender, $hashedPassword);

        (new PlayerRegisterEvent($sender))->call();

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["register_success"]);
        return true;
    }
}
