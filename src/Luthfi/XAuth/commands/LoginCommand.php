<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use Luthfi\XAuth\event\PlayerLoginEvent;
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
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["command_only_in_game"])) {
                $sender->sendMessage((string)$messages["command_only_in_game"]);
            }
            return false;
        }

        $name = strtolower($sender->getName());

        $bruteforceConfig = $this->plugin->getConfig()->get('bruteforce_protection');
        if (!is_array($bruteforceConfig)) {
            $bruteforceConfig = [];
        }

        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($enabled && $this->plugin->getAuthManager()->isPlayerBlocked($sender, $maxAttempts, $blockTimeMinutes)) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($sender, $blockTimeMinutes);
            $message = (string)($this->plugin->getCustomMessages()->get("messages")["login_attempts_exceeded"] ?? "");
            $sender->sendMessage(str_replace('{minutes}', (string)$remainingMinutes, $message));
            return false;
        }

        if (count($args) !== 1) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["login_usage"])) {
                $sender->sendMessage((string)$messages["login_usage"]);
            }
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData === null) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["not_registered"])) {
                $sender->sendMessage((string)$messages["not_registered"]);
            }
            return false;
        }

        if ($this->plugin->getDataProvider()->isPlayerLocked($sender->getName())) {
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["account_locked_by_admin"])) {
                $sender->sendMessage((string)$messages["account_locked_by_admin"]);
            }
            return false;
        }

        $password = (string)($args[0] ?? '');
        $storedPasswordHash = (string)($playerData["password"] ?? '');

        if (!password_verify($password, $storedPasswordHash)) {
            $this->plugin->getAuthManager()->incrementLoginAttempts($sender);
            $messages = $this->plugin->getCustomMessages()->get("messages");
            if (is_array($messages) && isset($messages["incorrect_password"])) {
                $sender->sendMessage((string)$messages["incorrect_password"]);
            }
            return false;
        }

        $this->plugin->getDataProvider()->updatePlayerIp($sender);

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        (new PlayerLoginEvent($sender))->call();

        $messages = $this->plugin->getCustomMessages()->get("messages");
        if (is_array($messages) && isset($messages["login_success"])) {
            $sender->sendMessage((string)$messages["login_success"]);
        }
        return true;
    }
}
