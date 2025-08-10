<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class RegisterCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "register",
            (string)($messages["register_command_description"] ?? "Register your account"),
            (string)($messages["register_command_usage"] ?? "/register <password> <confirm_password>")
        );
        $this->setPermission("xauth.command.register");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if ($this->plugin->getAuthManager()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($messages["already_logged_in"] ?? "§cYou are already logged in."));
            return false;
        }

        $name = strtolower($sender->getName());
        if (count($args) !== 2) {
            $sender->sendMessage((string)($messages["register_usage"] ?? "§cUsage: /register <password> <confirm_password>"));
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData !== null) {
            $sender->sendMessage((string)($messages["already_registered"] ?? "§cYou are already registered. Please use /login <password> to log in."));
            return false;
        }

        $password = (string)($args[0] ?? '');
        $confirmPassword = (string)($args[1] ?? '');

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($password)) !== null) {
            $sender->sendMessage($message);
            return false;
        }

        if ($password !== $confirmPassword) {
            $sender->sendMessage((string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
            return false;
        }

        $passwordHasher = $this->plugin->getPasswordHasher();
        if ($passwordHasher === null) {
            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
            $this->plugin->getLogger()->error("PasswordHasher is not initialized.");
            return false;
        }

        $hashedPassword = $passwordHasher->hashPassword($password);

        $this->plugin->getDataProvider()->registerPlayer($sender, $hashedPassword);

        (new PlayerRegisterEvent($sender))->call();

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        $this->plugin->restorePlayerState($sender);

        $sender->sendMessage((string)($messages["register_success"] ?? "§aYou have successfully registered!"));
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
