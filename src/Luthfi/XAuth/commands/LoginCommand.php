<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerLoginEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class LoginCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "login",
            (string)($messages["login_command_description"] ?? "Login to your account"),
            (string)($messages["login_command_usage"] ?? "/login <password>")
        );
        $this->setPermission("xauth.command.login");
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

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');

        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($enabled && $this->plugin->getAuthManager()->isPlayerBlocked($sender, $maxAttempts, $blockTimeMinutes)) {
            $remainingMinutes = $this->plugin->getAuthManager()->getRemainingBlockTime($sender, $blockTimeMinutes);
            $message = (string)($messages["login_attempts_exceeded"] ?? "§cYou have exceeded the number of login attempts. Please try again in {minutes} minutes.");
            $message = str_replace('{minutes}', (string)$remainingMinutes, $message);
            $kickOnBlock = (bool)($bruteforceConfig['kick_on_block'] ?? true);
            if ($kickOnBlock) {
                $sender->kick($message);
            } else {
                $sender->sendMessage($message);
            }
            return false;
        }

        if (count($args) !== 1) {
            $sender->sendMessage((string)($messages["login_usage"] ?? "§cUsage: /login <password>"));
            return false;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

        if ($playerData === null) {
            $sender->sendMessage((string)($messages["not_registered"] ?? "§cYou are not registered. Please use /register <password> to register."));
            return false;
        }

        if ($this->plugin->getDataProvider()->isPlayerLocked($sender->getName())) {
            $sender->sendMessage((string)($messages["account_locked_by_admin"] ?? "§cYour account has been locked by an administrator."));
            return false;
        }

        $password = (string)($args[0] ?? '');
        $storedPasswordHash = (string)($playerData["password"] ?? '');

        $passwordHasher = $this->plugin->getPasswordHasher();
        if ($passwordHasher === null) {
            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
            $this->plugin->getLogger()->error("PasswordHasher is not initialized.");
            return false;
        }

        if (!$passwordHasher->verifyPassword($password, $storedPasswordHash)) {
            $this->plugin->getAuthManager()->incrementLoginAttempts($sender);
            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password. Please try again."));
            return false;
        }

        if ($passwordHasher->needsRehash($storedPasswordHash)) {
            $newHashedPassword = $passwordHasher->hashPassword($password);
            $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);
        }

        $this->plugin->cancelKickTask($sender);

        $event = new PlayerLoginEvent($sender);
        $event->call();

        if ($event->isCancelled()) {
            return false;
        }

        if ($event->isAuthenticationDelayed()) {
            return true;
        }

        $this->plugin->forceLogin($sender);
        return true;
    }
}
