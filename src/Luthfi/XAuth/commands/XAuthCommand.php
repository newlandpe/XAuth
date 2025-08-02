<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\database\DataProviderFactory;
use Luthfi\XAuth\event\PlayerUnregisterEvent;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

class XAuthCommand extends Command {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "xauth",
            (string)($messages["xauth_command_description"] ?? "XAuth admin commands"),
            (string)($messages["xauth_command_usage"] ?? "/xauth <subcommand> [args]")
        );
        $this->plugin = $plugin;
        $this->setPermission("xauth.command.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (count($args) < 1) {
            $sender->sendMessage((string)($messages["xauth_command_usage"] ?? "§cUsage: /xauth <subcommand> [args]"));
            return false;
        }

        $subCommand = strtolower((string)array_shift($args));

        switch ($subCommand) {
            case "help":
                $sender->sendMessage((string)($messages["xauth_help_message"] ?? "--- XAuth Help ---"));
                break;
            case "lock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_lock_usage"] ?? "§cUsage: /xauth lock <player>"));
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, true);
                $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_player_locked"] ?? "§aPlayer {player_name} has been locked.")));
                break;
            case "unlock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_unlock_usage"] ?? "§cUsage: /xauth unlock <player>"));
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
                    return false;
                }

                $this->plugin->getDataProvider()->setPlayerLocked($playerName, false);
                $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_player_unlocked"] ?? "§aPlayer {player_name} has been unlocked.")));
                break;
            case "lookup":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_lookup_usage"] ?? "§cUsage: /xauth lookup <player>"));
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
                $playerData = $this->plugin->getDataProvider()->getPlayer($offlinePlayer);

                if ($playerData === null) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
                    return false;
                }

                $registrationIp = (isset($playerData["registration_ip"]) ? (string)$playerData["registration_ip"] : "N/A");
                $lastLoginIp = "N/A";
                $lastLoginTime = "N/A";

                $autoLoginEnabled = (bool)($this->plugin->getConfig()->getNested("auto-login.enabled") ?? false);

                if ($autoLoginEnabled) {
                    $sessions = $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
                    if (!empty($sessions)) {
                        // getSessionsByPlayer already sorts by login_time DESC
                        $lastLoginIp = (string)($sessions[0]['ip_address'] ?? "N/A");
                        $lastLoginTime = (isset($sessions[0]["login_time"]) ? date("Y-m-d H:i:s", (int)$sessions[0]["login_time"]) : "N/A");
                    }
                } else {
                    // Fallback to player data if auto-login (and thus sessions) is disabled
                    $lastLoginIp = (string)($playerData["ip"] ?? "N/A");
                    $lastLoginTime = (isset($playerData["last_login_at"]) ? date("Y-m-d H:i:s", (int)$playerData["last_login_at"]) : "N/A");
                }

                $lookupMessage = str_replace(
                    ['{player_name}', '{registered_at}', '{registration_ip}', '{last_login_ip}', '{last_login_at}', '{locked_status}'],
                    [
                        $playerName,
                        (isset($playerData["registered_at"]) ? date("Y-m-d H:i:s", (int)$playerData["registered_at"]) : "N/A"),
                        $registrationIp,
                        $lastLoginIp,
                        $lastLoginTime,
                        ($this->plugin->getDataProvider()->isPlayerLocked($playerName) ? "Yes" : "No")
                    ],
                    (string)($messages["xauth_player_lookup_info"] ?? "Player: {player_name}")
                );
                $sender->sendMessage($lookupMessage);
                break;
            case "setpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage((string)($messages["xauth_setpassword_usage"] ?? "§cUsage: /xauth setpassword <player> <password>"));
                    return false;
                }

                $playerName = (string)($args[0] ?? '');
                $newPassword = (string)($args[1] ?? '');

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
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
                $sender->sendMessage((string)($messages["set_password_success"] ?? "§aPlayer password has been set successfully."));
                break;
            case "unregister":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_unregister_usage"] ?? "§cUsage: /xauth unregister <player>"));
                    return false;
                }

                $playerName = (string)($args[0] ?? '');

                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
                    return false;
                }

                $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
                $this->plugin->getDataProvider()->unregisterPlayer($playerName);
                (new PlayerUnregisterEvent($offlinePlayer))->call();
                $sender->sendMessage((string)($messages["unregister_success"] ?? "§aPlayer account has been unregistered successfully."));
                break;
            case "forcepasswordchange":
            case "forcepass":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_forcepasswordchange_usage"] ?? "§cUsage: /xauth forcepasswordchange <player>"));
                    return false;
                }
                $playerName = (string)($args[0] ?? '');
                if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered.")));
                    return false;
                }

                $this->plugin->getDataProvider()->setMustChangePassword($playerName, true);

                $player = $this->plugin->getServer()->getPlayerExact($playerName);
                $forceImmediate = (bool)$this->plugin->getConfig()->getNested("command_settings.force_change_immediate", true);

                if ($player !== null && $forceImmediate) {
                    $this->plugin->startForcePasswordChange($player);
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_forcepasswordchange_now"] ?? "§aPlayer {player_name} is now being forced to change their password.")));
                } else {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_forcepasswordchange_success"] ?? "§aPlayer {player_name} will be forced to change their password on next login.")));
                }
                break;
            case "migrate-provider":
                if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                    $sender->sendMessage((string)($messages["command_only_in_console"] ?? "§cThis command can only be used in the console."));
                    return false;
                }
                if (count($args) !== 2) {
                    $sender->sendMessage((string)($messages["xauth_migrate_usage"] ?? "§cUsage: /xauth migrate-provider <source_provider> <destination_provider>"));
                    return false;
                }
                $sourceProviderType = strtolower($args[0]);
                $destinationProviderType = strtolower($args[1]);

                try {
                    $sender->sendMessage(str_replace(['{source_provider}', '{destination_provider}'], [$sourceProviderType, $destinationProviderType], (string)($messages["xauth_migration_start"] ?? "§eStarting migration from '{source_provider}' to '{destination_provider}'...")));
                    $sourceProvider = DataProviderFactory::createProvider($this->plugin, $sourceProviderType);
                    $destinationProvider = DataProviderFactory::createProvider($this->plugin, $destinationProviderType);

                    $sender->sendMessage((string)($messages["xauth_migration_fetching_data"] ?? "§eFetching all player data from source..."));
                    $allPlayerData = $sourceProvider->getAllPlayerData();
                    $totalPlayers = count($allPlayerData);
                    $sender->sendMessage(str_replace('{count}', (string)$totalPlayers, (string)($messages["xauth_migration_found_players"] ?? "§aFound {count} players to migrate.")));

                    $migratedCount = 0;
                    $skippedCount = 0;

                    foreach ($allPlayerData as $playerName => $playerData) {
                        if ($destinationProvider->isPlayerRegistered($playerName)) {
                            $skippedCount++;
                            continue;
                        }
                        $destinationProvider->registerPlayerRaw($playerName, $playerData);
                        $migratedCount++;
                    }

                    $sender->sendMessage((string)($messages["xauth_migration_complete"] ?? "§aMigration complete!"));
                    $sender->sendMessage(str_replace('{count}', (string)$migratedCount, (string)($messages["xauth_migration_migrated_count"] ?? "§a- Migrated: {count} players")));
                    $sender->sendMessage(str_replace('{count}', (string)$skippedCount, (string)($messages["xauth_migration_skipped_count"] ?? "§e- Skipped (already exist): {count} players")));

                    $sourceProvider->close();
                    $destinationProvider->close();
                } catch (\InvalidArgumentException $e) {
                    $sender->sendMessage((string)($messages["xauth_migration_error_prefix"] ?? "§cError: ") . $e->getMessage());
                } catch (\Throwable $t) {
                    $sender->sendMessage((string)($messages["xauth_migration_unexpected_error_prefix"] ?? "§cAn unexpected error occurred during migration: ") . $t->getMessage());
                }
                break;
            case "status":
                if (count($args) < 1) {
                    $sender->sendMessage((string)($messages["xauth_status_usage"] ?? "§cUsage: /xauth status <list|end> [player]"));
                    return false;
                }
                $statusSubCommand = strtolower((string)array_shift($args));
                switch ($statusSubCommand) {
                    case "list":
                        $onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
                        $header = str_replace("{count}", (string)count($onlinePlayers), (string)($messages["xauth_status_list_header"] ?? "§e--- Online Players ({count}) ---"));
                        $sender->sendMessage($header);
                        foreach ($onlinePlayers as $player) {
                            $status = $this->plugin->getAuthManager()->isPlayerAuthenticated($player) ? (string)($messages["xauth_status_authenticated"] ?? "§aAuthenticated") : (string)($messages["xauth_status_unauthenticated"] ?? "§eUnauthenticated");
                            $sender->sendMessage("§f- " . $player->getName() . ": " . $status);
                        }
                        break;
                    case "end":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)($messages["xauth_status_end_usage"] ?? "§cUsage: /xauth status end <player>"));
                            return false;
                        }
                        $playerName = (string)($args[0] ?? '');
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player === null) {
                            $sender->sendMessage((string)($messages["xauth_player_not_online"] ?? "§cPlayer not found."));
                            return false;
                        }
                        if (!$this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
                            $sender->sendMessage((string)($messages["xauth_player_not_authenticated"] ?? "§cPlayer is not authenticated."));
                            return false;
                        }
                        $this->plugin->getAuthManager()->deauthenticatePlayer($player);
                        $this->plugin->scheduleKickTask($player);
                        $player->sendMessage((string)($messages["session_ended_by_admin"] ?? "§eYour session has been ended by an administrator. Please log in again."));
                        $sender->sendMessage(str_replace("{player_name}", $player->getName(), (string)($messages["xauth_status_end_success"] ?? "§aSession for player {player_name} has been ended.")));
                        break;
                    default:
                        $sender->sendMessage((string)($messages["xauth_status_unknown_subcommand"] ?? "§cUnknown status subcommand. Use /xauth status <list|end>"));
                        break;
                }
                break;
            case "sessions":
                if (count($args) < 1) {
                    $sender->sendMessage((string)($messages["xauth_sessions_usage"] ?? "§cUsage: /xauth sessions <list|terminate|terminateall|cleanup> [args]"));
                    return false;
                }
                $sessionSubCommand = strtolower((string)array_shift($args));
                switch ($sessionSubCommand) {
                    case "list":
                        $playerName = (string)($args[0] ?? ($sender instanceof Player ? $sender->getName() : ""));
                        if (empty($playerName)) {
                            $sender->sendMessage((string)($messages["xauth_sessions_list_usage"] ?? "§cUsage: /xauth sessions list <player>"));
                            return false;
                        }
                        $sessions = $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
                        if (empty($sessions)) {
                            $sender->sendMessage(str_replace("{player_name}", $playerName, (string)($messages["xauth_sessions_no_sessions"] ?? "§eNo active sessions found for {player_name}.")));
                            return false;
                        }
                        $sender->sendMessage(str_replace(["{player_name}", "{count}"], [$playerName, (string)count($sessions)], (string)($messages["xauth_sessions_list_header"] ?? "§e--- Sessions for {player_name} ({count}) ---")));
                        foreach ($sessions as $session) {
                            $sessionId = (string)($session['session_id'] ?? 'N/A');
                            $ipAddress = (string)($session['ip_address'] ?? 'N/A');
                            $loginTime = date("Y-m-d H:i:s", (int)($session['login_time'] ?? 0));
                            $lastActivity = date("Y-m-d H:i:s", (int)($session['last_activity'] ?? 0));
                            $expirationTime = date("Y-m-d H:i:s", (int)($session['expiration_time'] ?? 0));
                            $sender->sendMessage("§fID: §7" . $sessionId . "§f | IP: §7" . $ipAddress . "§f | Login: §7" . $loginTime . "§f | Last Activity: §7" . $lastActivity . "§f | Expires: §7" . $expirationTime);
                        }
                        break;
                    case "terminate":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)($messages["xauth_sessions_terminate_usage"] ?? "§cUsage: /xauth sessions terminate <session_id>"));
                            return false;
                        }
                        $sessionId = (string)($args[0] ?? '');
                        $session = $this->plugin->getDataProvider()->getSession($sessionId);
                        if ($session === null) {
                            $sender->sendMessage((string)($messages["xauth_sessions_terminate_not_found"] ?? "§cSession not found or already expired."));
                            return false;
                        }
                        $this->plugin->getDataProvider()->deleteSession($sessionId);
                        $sender->sendMessage(str_replace("{session_id}", $sessionId, (string)($messages["xauth_sessions_terminate_success"] ?? "§aSession {session_id} terminated.")));
                        break;
                    case "terminateall":
                        $playerName = (string)($args[0] ?? ($sender instanceof Player ? $sender->getName() : ""));
                        if (empty($playerName)) {
                            $sender->sendMessage((string)($messages["xauth_sessions_terminateall_usage"] ?? "§cUsage: /xauth sessions terminateall <player>"));
                            return false;
                        }
                        $this->plugin->getDataProvider()->deleteAllSessionsForPlayer($playerName);
                        $sender->sendMessage(str_replace("{player_name}", $playerName, (string)($messages["xauth_sessions_terminateall_success"] ?? "§aAll sessions for {player_name} terminated.")));
                        break;
                    case "cleanup":
                        if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                            $sender->sendMessage((string)($messages["command_only_in_console"] ?? "§cThis command can only be used in the console."));
                            return false;
                        }
                        $this->plugin->getDataProvider()->cleanupExpiredSessions();
                        $sender->sendMessage((string)($messages["xauth_sessions_cleanup_success"] ?? "§aExpired sessions cleaned up."));
                        break;
                    default:
                        $sender->sendMessage((string)($messages["xauth_sessions_unknown_subcommand"] ?? "§cUnknown sessions subcommand. Use /xauth sessions <list|terminate|terminateall|cleanup>"));
                        break;
                }
                break;
            case "reload":
                $this->plugin->reloadConfig();
                $sender->sendMessage((string)($messages["xauth_reload_success"] ?? "§aXAuth configuration reloaded."));
                break;
            default:
                $sender->sendMessage((string)($messages["xauth_unknown_subcommand"] ?? "§cUnknown subcommand. Use /xauth help for a list of commands."));
                break;
        }
        return true;
    }
}
