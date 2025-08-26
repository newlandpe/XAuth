<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\AccountLockedException;
use Luthfi\XAuth\exception\AlreadyLoggedInException;
use Luthfi\XAuth\exception\AlreadyRegisteredException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\exception\RegistrationRateLimitException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
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

        if (count($args) !== 2) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $password = (string)($args[0] ?? '');
        $confirmPassword = (string)($args[1] ?? '');

        try {
            $registrationService = $this->plugin->getRegistrationService();
            $registrationService->handleRegistrationRequest($sender, $password, $confirmPassword);
        } catch (AlreadyLoggedInException $e) {
            $sender->sendMessage((string)($messages["already_logged_in"] ?? "§cYou are already logged in."));
        } catch (AlreadyRegisteredException $e) {
            $sender->sendMessage((string)($messages["already_registered"] ?? "§cYou are already registered. Please use /login <password> to log in."));
        } catch (AccountLockedException $e) {
            $sender->sendMessage((string)($messages["account_locked_by_admin"] ?? "§cYour account has been locked by an administrator."));
        } catch (RegistrationRateLimitException $e) {
            $sender->sendMessage((string)($messages["registration_ip_limit_reached"] ?? "§cYou have reached the maximum number of registrations for your IP address."));
        } catch (PasswordMismatchException $e) {
            $sender->sendMessage((string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
        } catch (InvalidCommandSyntaxException $e) {
            // This exception is used to pass validation messages from PasswordValidator
            $sender->sendMessage($e->getMessage());
        } catch (\Exception $e) {
            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
            $this->plugin->getLogger()->error("An unexpected error occurred during command registration: " . $e->getMessage());
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
