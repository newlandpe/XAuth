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

        if (!password_verify($password, $storedPasswordHash)) {
            $this->plugin->getAuthManager()->incrementLoginAttempts($sender);
            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password. Please try again."));
            return false;
        }

        if (password_needs_rehash($storedPasswordHash, PASSWORD_BCRYPT)) {
            $newHashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->plugin->getDataProvider()->changePassword($sender, $newHashedPassword);
        }

        $this->plugin->cancelKickTask($sender);

        $this->plugin->getAuthManager()->authenticatePlayer($sender);

        $event = new PlayerLoginEvent($sender);
        $event->call();

        if ($event->isAuthenticationDelayed() || $event->isCancelled()) {
            $this->plugin->getAuthManager()->deauthenticatePlayer($sender);
            return true;
        }

        $autoLoginEnabled = (bool)($this->plugin->getConfig()->getNested('auto-login.enabled') ?? false);

        if ($autoLoginEnabled) {
            $sessions = $this->plugin->getDataProvider()->getSessionsByPlayer($sender->getName());
            $ip = $sender->getNetworkSession()->getIp();
            $existingSessionId = null;

            foreach ($sessions as $sessionId => $sessionData) {
                if (($sessionData['ip_address'] ?? '') === $ip) {
                    $existingSessionId = $sessionId;
                    break;
                }
            }

            $lifetime = (int)($this->plugin->getConfig()->getNested('auto-login.lifetime_seconds') ?? 2592000);

            if ($existingSessionId !== null) {
                $refreshSession = (bool)($this->plugin->getConfig()->getNested('auto-login.refresh_session_on_login') ?? true);
                if ($refreshSession) {
                    $this->plugin->getDataProvider()->refreshSession($existingSessionId, $lifetime);
                }
            } else {
                $this->plugin->getDataProvider()->createSession($sender->getName(), $ip, $lifetime);
            }
        }

        $this->plugin->getDataProvider()->updatePlayerIp($sender);

        $sender->sendMessage((string)($messages["login_success"] ?? "§aYou have successfully logged in!"));
        return true;
    }
}
