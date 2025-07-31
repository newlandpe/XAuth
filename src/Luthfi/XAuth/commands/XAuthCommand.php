<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\event\PlayerUnregisterEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

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
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            if (isset($messages["xauth_usage"])) {
                $sender->sendMessage((string)$messages["xauth_usage"]);
            }
            return false;
        }

        $subCommand = strtolower((string)array_shift($args));

        switch ($subCommand) {
            case "help":
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["xauth_usage"])) {
                    $sender->sendMessage((string)$messages["xauth_usage"]);
                }
                break;
            case "lock":
                if (count($args) !== 1) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["xauth_lock_usage"])) {
                        $sender->sendMessage((string)$messages["xauth_lock_usage"]);
                    }
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["player_not_registered"])) {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["player_not_registered"]));
                    }
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, true);
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["xauth_player_locked"])) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["xauth_player_locked"]));
                }
                break;
            case "unlock":
                if (count($args) !== 1) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["xauth_unlock_usage"])) {
                        $sender->sendMessage((string)$messages["xauth_unlock_usage"]);
                    }
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["player_not_registered"])) {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["player_not_registered"]));
                    }
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, false);
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["xauth_player_unlocked"])) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["xauth_player_unlocked"]));
                }
                break;
            case "lookup":
                if (count($args) !== 1) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["xauth_lookup_usage"])) {
                        $sender->sendMessage((string)$messages["xauth_lookup_usage"]);
                    }
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
                $playerData = $this->plugin->getDataProvider()->getPlayer($offlinePlayer);

                if ($playerData === null) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["player_not_registered"])) {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["player_not_registered"]));
                    }
                    return false;
                }

                $messages = (array)$this->plugin->getCustomMessages()->get("messages");                if (isset($messages["xauth_player_lookup_header"])) {                    $lookupMessage = str_replace('{player_name}', $playerName, (string)$messages["xauth_player_lookup_header"]) . "\n";                    $lookupMessage .= str_replace('{date}', (isset($playerData["registered_at"]) ? date("Y-m-d H:i:s", (int)$playerData["registered_at"]) : "N/A"), (string)($messages["xauth_registered"] ?? '')) . "\n";                    $lookupMessage .= str_replace('{ip}', (isset($playerData["registration_ip"]) ? (string)$playerData["registration_ip"] : "N/A"), (string)($messages["xauth_registration_ip"] ?? '')) . "\n";                    $lookupMessage .= str_replace('{ip}', (string)($playerData["ip"] ?? "N/A"), (string)($messages["xauth_last_login_ip"] ?? '')) . "\n";                    $lookupMessage .= str_replace('{date}', (isset($playerData["last_login_at"]) ? date("Y-m-d H:i:s", (int)$playerData["last_login_at"]) : "N/A"), (string)($messages["xauth_last_login"] ?? '')) . "\n";                    $lookupMessage .= str_replace('{status}', ($this->plugin->getDataProvider()->isPlayerLocked($playerName) ? "Yes" : "No"), (string)($messages["xauth_locked"] ?? '')) . "\n";                    $lookupMessage .= (string)($messages["xauth_lookup_footer"] ?? '');                    $sender->sendMessage($lookupMessage);                }
                break;
            case "setpassword":
                if (count($args) !== 2) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["xauth_setpassword_usage"])) {
                        $sender->sendMessage((string)$messages["xauth_setpassword_usage"]);
                    }
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                $newPassword = (string)($args[1] ?? '');

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["player_not_registered"])) {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["player_not_registered"]));
                    }
                    return false;
                }

                $passwordValidator = $this->plugin->getPasswordValidator();
                if ($passwordValidator === null) {
                    return false; // Should not happen
                }
                if (($message = $passwordValidator->validatePassword($newPassword)) !== null) {
                    $sender->sendMessage($message);
                    return false;
                }

                $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $this->plugin->getDataProvider()->changePassword($offlinePlayer, $newHashedPassword);
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["set_password_success"])) {
                    $sender->sendMessage((string)$messages["set_password_success"]);
                }
                break;
            case "unregister":
                if (count($args) !== 1) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["xauth_unregister_usage"])) {
                        $sender->sendMessage((string)$messages["xauth_unregister_usage"]);
                    }
                    return false;
                }

                $playerName = (string)($args[0] ?? '');

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                    if (isset($messages["player_not_registered"])) {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)$messages["player_not_registered"]));
                    }
                    return false;
                }

                $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
                $this->plugin->getDataProvider()->unregisterPlayer($playerName);
                (new PlayerUnregisterEvent($offlinePlayer))->call();
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["unregister_success"])) {
                    $sender->sendMessage((string)$messages["unregister_success"]);
                }
                break;
            case "reload":
                $this->plugin->reloadConfig();
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["xauth_reload_success"])) {
                    $sender->sendMessage((string)$messages["xauth_reload_success"]);
                }
                break;
            default:
                $messages = (array)$this->plugin->getCustomMessages()->get("messages");
                if (isset($messages["xauth_unknown_subcommand"])) {
                    $sender->sendMessage((string)$messages["xauth_unknown_subcommand"]);
                }
                break;
        }
        return true;
    }
}
