<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class LogoutCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "logout",
            (string)($messages["logout_command_description"] ?? "Logout from your account"),
            (string)($messages["logout_command_usage"] ?? "/logout")
        );
        $this->setPermission("xauth.command.logout");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($messages["not_logged_in"] ?? "§cYou are not logged in."));
            return false;
        }

        $event = new PlayerDeauthenticateEvent($sender);
        $event->call();

        $sender->sendMessage((string)($messages["logout_success"] ?? "§aYou have been logged out."));
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
