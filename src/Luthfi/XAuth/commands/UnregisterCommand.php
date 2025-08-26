<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\ConfirmationExpiredException;
use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\UnregistrationNotInitiatedException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class UnregisterCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "unregister",
            (string)($messages["unregister_command_description"] ?? "Unregister your account."),
            (string)($messages["unregister_command_usage"] ?? "/unregister [confirm <password>]")
        );
        $this->setPermission("xauth.command.unregister");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        $commandSettings = (array)$this->plugin->getConfig()->get("command_settings");
        if (isset($commandSettings['allow_player_self_unregister']) && $commandSettings['allow_player_self_unregister'] === false) {
            $sender->sendMessage((string)($messages["unregister_disabled"] ?? "§cAccount unregistration is disabled on this server."));
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($messages["command_only_in_game"] ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($messages["not_logged_in"] ?? "§cYou are not logged in."));
            return false;
        }

        $registrationService = $this->plugin->getRegistrationService();

        if (isset($args[0]) && strtolower($args[0]) === 'confirm') {
            if (!isset($args[1])) {
                $sender->sendMessage((string)($messages["unregister_password_missing"] ?? "§cUsage: /unregister confirm <password>"));
                return false;
            }
            $password = $args[1];

            try {
                $registrationService->confirmUnregistration($sender, $password);
            } catch (UnregistrationNotInitiatedException $e) {
                $sender->sendMessage((string)($messages["unregister_not_initiated"] ?? "§cYou have not started the unregistration process. Type /unregister first."));
            } catch (ConfirmationExpiredException $e) {
                $sender->sendMessage((string)($messages["unregister_confirmation_expired"] ?? "§cUnregistration confirmation expired. Please start over."));
            } catch (IncorrectPasswordException $e) {
                $sender->sendMessage((string)($messages["incorrect_password"] ?? "§cIncorrect password."));
            } catch (\Exception $e) {
                $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred. Please try again."));
                $this->plugin->getLogger()->error("An unexpected error occurred during unregistration confirmation: " . $e->getMessage());
            }
        } else {
            $registrationService->initiateUnregistration($sender);
            $sender->sendMessage((string)($messages["unregister_initiate"] ?? "§eAre you sure you want to unregister? This action is irreversible.§r\n§eType §f/unregister confirm <password>§e within 60 seconds to confirm."));
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
