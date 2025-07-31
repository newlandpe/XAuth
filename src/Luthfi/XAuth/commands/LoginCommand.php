<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class LoginCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("login", "Login to your account", "/login <password>");
        $this->setPermission("xauth.command.login");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return false;
        }

        $name = strtolower($sender->getName());

        $bruteforceConfig = $this->plugin->getConfig()->get('bruteforce_protection');
        if ($bruteforceConfig['enabled'] && $this->plugin->getAuthManager()->isPlayerBlocked($sender, $bruteforceConfig['max_attempts'], $bruteforceConfig['block_time_minutes'])) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($sender, $bruteforceConfig['block_time_minutes']);
            $sender->sendMessage(str_replace('{minutes}', (string)$remainingMinutes, $this->plugin->getCustomMessages()->get("messages")["login_attempts_exceeded"]));
            return false;
        }

        if (count($args) !== 1) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("login_usage"));
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData === null) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["not_registered"]);
            return false;
        }

        if ($this->plugin->getDataProvider()->isPlayerLocked($sender->getName())) {
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["account_locked_by_admin"]);
            return false;
        }

        $password = $args[0];
        $storedPasswordHash = $playerData["password"];

        if (!password_verify($password, $storedPasswordHash)) {
            $this->plugin->getAuthManager()->incrementLoginAttempts($sender);
            $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["incorrect_password"]);
            return false;
        }

        $this->plugin->getDataProvider()->updatePlayerIp($sender);

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        (new PlayerLoginEvent($sender))->call();

        $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["login_success"]);
        return true;
    }
}
