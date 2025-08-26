<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class ResetPasswordCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "resetpassword",
            (string)($messages["resetpassword_command_description"] ?? "Reset your password"),
            (string)($messages["resetpassword_command_usage"] ?? "/resetpassword <old_password> <new_password> <confirm_password>")
        );
        $this->setPermission("xauth.command.resetpassword");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        $formManager = $this->plugin->getFormManager();
        if ($formManager !== null && empty($args)) {
            $formManager->sendChangePasswordForm($sender);
            return true;
        }

        if (count($args) !== 3) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $oldPassword = (string)($args[0] ?? '');
        $newPassword = (string)($args[1] ?? '');
        $confirmNewPassword = (string)($args[2] ?? '');

        try {
            $this->plugin->getAuthenticationService()->handleChangePasswordRequest($sender, $oldPassword, $newPassword, $confirmNewPassword);
            $sender->sendMessage((string)($messages["change_password_success"] ?? "§aYour password has been changed successfully."));
        } catch (IncorrectPasswordException $e) {
            $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password."));
        } catch (PasswordMismatchException $e) {
            $sender->sendMessage((string)($messages["password_mismatch"] ?? "§cPasswords do not match."));
        } catch (InvalidCommandSyntaxException $e) {
            $sender->sendMessage($e->getMessage());
        } catch (NotRegisteredException $e) {
            $sender->sendMessage((string)($messages["not_registered"] ?? "§cYou are not registered."));
        } catch (\Exception $e) {
            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
            $this->plugin->getLogger()->error("An unexpected error occurred during password reset command: " . $e->getMessage());
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
