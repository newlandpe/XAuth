<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerUnregisterEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class UnregisterCommand extends Command implements PluginOwned {

    private Main $plugin;

    /** @var array<string, int> */
    private array $confirmations = [];

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
        $commandSettings = (array)$this->plugin->getConfig()->get("command_settings");
        if (isset($commandSettings['allow_player_self_unregister']) && $commandSettings['allow_player_self_unregister'] === false) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.unregister_disabled") ?? "§cAccount unregistration is disabled on this server."));
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.command_only_in_game") ?? "§cThis command can only be used in-game."));
            return false;
        }

        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($sender)) {
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.not_logged_in") ?? "§cYou are not logged in."));
            return false;
        }

        if (isset($args[0]) && strtolower($args[0]) === 'confirm') {
            if (!isset($this->confirmations[strtolower($sender->getName())])) {
                $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.unregister_not_initiated") ?? "§cYou have not started the unregistration process. Type /unregister first."));
                return false;
            }

            if (time() - $this->confirmations[strtolower($sender->getName())] > 60) {
                unset($this->confirmations[strtolower($sender->getName())]);
                $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.unregister_confirmation_expired") ?? "§cUnregistration confirmation expired. Please start over."));
                return false;
            }

            if (!isset($args[1])) {
                $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.unregister_password_missing") ?? "§cUsage: /unregister confirm <password>"));
                return false;
            }

            $password = $args[1];
            $playerData = $this->plugin->getDataProvider()->getPlayer($sender);

            if ($playerData === null || !$this->plugin->getPasswordHasher()->verify($password, $playerData['password'])) {
                $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.incorrect_password") ?? "§cIncorrect password."));
                return false;
            }

            unset($this->confirmations[strtolower($sender->getName())]);
            $this->plugin->getDataProvider()->unregisterPlayer($sender->getName());
            (new PlayerUnregisterEvent($sender))->call();
            $sender->kick((string)($this->plugin->getCustomMessages()->get("messages.unregister_success_kick") ?? "§aYour account has been successfully unregistered."));
        } else {
            $this->confirmations[strtolower($sender->getName())] = time();
            $sender->sendMessage((string)($this->plugin->getCustomMessages()->get("messages.unregister_initiate") ?? "§eAre you sure you want to unregister? This action is irreversible.§r\n§eType §f/unregister confirm <password>§e within 60 seconds to confirm."));
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
