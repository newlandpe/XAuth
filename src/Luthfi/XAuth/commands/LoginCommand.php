<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\AccountLockedException;
use Luthfi\XAuth\exception\AlreadyLoggedInException;
use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\exception\PlayerBlockedException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class LoginCommand extends Command implements PluginOwned {

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
        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.command_only_in_game") ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (count($args) !== 1) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.login_usage") ?? "§cUsage: /login <password>"));
            return false;
        }

        $password = $args[0];
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        try {
            $this->plugin->getAuthenticationService()->handleLoginRequest($sender, $password);
        } catch (AlreadyLoggedInException $e) {
            $sender->sendMessage((string)($messages["already_logged_in"] ?? "§cYou are already logged in."));
        } catch (PlayerBlockedException $e) {
            $message = (string)($messages["login_attempts_exceeded"] ?? "§cYou have exceeded the number of login attempts. Please try again in {minutes} minutes.");
            $message = str_replace('{minutes}', (string)$e->getRemainingMinutes(), $message);
            $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
            $kickOnBlock = (bool)($bruteforceConfig['kick_on_block'] ?? true);
            if ($kickOnBlock) {
                $sender->kick($message);
            } else {
                $sender->sendMessage($message);
            }
        } catch (NotRegisteredException $e) {
            $sender->sendMessage((string)($messages["not_registered"] ?? "§cYou are not registered. Please use /register <password> to register."));
        } catch (AccountLockedException $e) {
            $sender->sendMessage((string)($messages["account_locked_by_admin"] ?? "§cYour account has been locked by an administrator."));
        } catch (IncorrectPasswordException $e) {
            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password. Please try again."));
        } catch (\Exception $e) {
            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
            $this->plugin->getLogger()->error("An unexpected error occurred during login: " . $e->getMessage());
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
