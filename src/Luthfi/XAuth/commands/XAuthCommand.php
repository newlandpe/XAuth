<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class XAuthCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("xauth", "XAuth admin commands", "/xauth <subcommand> [args]");
        $this->plugin = $plugin;
        $this->setPermission("xauth.command.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        if (count($args) < 1) {
            $sender->sendMessage($this->getUsage());
            return false;
        }

        $subCommand = strtolower(array_shift($args));

        switch ($subCommand) {
            case "lock":
                if (count($args) !== 1) {
                    $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_lock_usage"]);
                    return false;
                }

                $playerName = $args[0];
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["player_not_registered"]));
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, true);
                $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["xauth_player_locked"]));
                break;
            case "unlock":
                if (count($args) !== 1) {
                    $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_unlock_usage"]);
                    return false;
                }

                $playerName = $args[0];
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["player_not_registered"]));
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, false);
                $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["xauth_player_unlocked"]));
                break;
            case "lookup":
                if (count($args) !== 1) {
                    $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_lookup_usage"]);
                    return false;
                }

                $playerName = $args[0];
                $playerData = $this->plugin->getDataProvider()->getPlayer(Server::getInstance()->getOfflinePlayer($playerName));

                if ($playerData === null) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["player_not_registered"]));
                    return false;
                }

                $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["xauth_player_lookup_header"]));
                $sender->sendMessage(str_replace('{date}', (isset($playerData["registered_at"]) ? date("Y-m-d H:i:s", $playerData["registered_at"]) : "N/A"), $this->plugin->getCustomMessages()->get("messages")["xauth_registered"]));
                $sender->sendMessage(str_replace('{ip}', (isset($playerData["registration_ip"]) ? $playerData["registration_ip"] : "N/A"), $this->plugin->getCustomMessages()->get("messages")["xauth_registration_ip"]));
                $sender->sendMessage(str_replace('{ip}', $playerData["ip"], $this->plugin->getCustomMessages()->get("messages")["xauth_last_login_ip"]));
                $sender->sendMessage(str_replace('{date}', (isset($playerData["last_login_at"]) ? date("Y-m-d H:i:s", $playerData["last_login_at"]) : "N/A"), $this->plugin->getCustomMessages()->get("messages")["xauth_last_login"]));
                $sender->sendMessage(str_replace('{status}', ($this->plugin->getDataProvider()->isPlayerLocked($playerName) ? "Yes" : "No"), $this->plugin->getCustomMessages()->get("messages")["xauth_locked"]));
                $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_lookup_footer"]);
                break;
            case "setpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_setpassword_usage"]);
                    return false;
                }

                $playerName = $args[0];
                $newPassword = $args[1];

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["player_not_registered"]));
                    return false;
                }

                if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
                    $sender->sendMessage($message);
                    return false;
                }

                $player = Server::getInstance()->getOfflinePlayer($playerName);
                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
                $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["set_password_success"]);
                break;
            case "unregister":
                if (count($args) !== 1) {
                    $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_unregister_usage"]);
                    return false;
                }

                $playerName = $args[0];

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, $this->plugin->getCustomMessages()->get("messages")["player_not_registered"]));
                    return false;
                }

                $player = Server::getInstance()->getOfflinePlayer($playerName);
                $this->plugin->getDataProvider()->unregisterPlayer($playerName);
                (new PlayerUnregisterEvent($player))->call();
                $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["unregister_success"]);
                break;
            case "reload":
                $this->plugin->reloadConfig();
                $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_reload_success"]);
                break;
            default:
                $sender->sendMessage($this->plugin->getCustomMessages()->get("messages")["xauth_unknown_subcommand"]);
                break;
        }
        return true;
    }
}
