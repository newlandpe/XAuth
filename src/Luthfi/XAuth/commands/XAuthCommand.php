<?php

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;

class XAuthCommand extends Command implements PluginOwned {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $messages = (array)$plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "xauth",
            (string)( $messages["xauth_command_description"] ?? "XAuth admin commands" ),
            (string)( $messages["xauth_command_usage"] ?? "/xauth <subcommand> [args]" )
        );
        $this->setPermission("xauth.command.admin");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (count($args) < 1) {
            $sender->sendMessage((string)( $messages["xauth_command_usage"] ?? "§cUsage: /xauth <subcommand> [args]" ));
            return false;
        }

        $subCommand = strtolower((string)array_shift($args));

        switch ($subCommand) {
            case "help":
                $sender->sendMessage((string)( $messages["xauth_help_message"] ?? "--- XAuth Help ---" ));
                break;
            case "lock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)( $messages["xauth_lock_usage"] ?? "§cUsage: /xauth lock <player>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                try {
                    $this->plugin->getAuthenticationService()->lockAccount($playerName);
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["xauth_player_locked"] ?? "§aPlayer {player_name} has been locked." )));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                }
                break;
            case "unlock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)( $messages["xauth_unlock_usage"] ?? "§cUsage: /xauth unlock <player>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                try {
                    $this->plugin->getAuthenticationService()->unlockAccount($playerName);
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["xauth_player_unlocked"] ?? "§aPlayer {player_name} has been unlocked." )));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                }
                break;
            case "lookup":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)( $messages["xauth_lookup_usage"] ?? "§cUsage: /xauth lookup <player>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                $playerData = $this->plugin->getAuthenticationService()->getPlayerLookupData($playerName);

                if ($playerData === null) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                    return false;
                }

                $lookupMessage = str_replace(
                    array_keys($playerData),
                    array_values($playerData),
                    (string)( $messages["xauth_player_lookup_info"] ?? "Player: {player_name}" )
                );
                $sender->sendMessage($lookupMessage);
                break;
            case "setpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage((string)( $messages["xauth_setpassword_usage"] ?? "§cUsage: /xauth setpassword <player> <password>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                $newPassword = (string)( $args[1] ?? '' );
                try {
                    $this->plugin->getAuthenticationService()->setPlayerPassword($playerName, $newPassword);
                    $sender->sendMessage((string)( $messages["set_password_success"] ?? "§aPlayer password has been set successfully." ));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                } catch (InvalidCommandSyntaxException $e) {
                    $sender->sendMessage($e->getMessage());
                }
                break;
            case "unregister":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)( $messages["xauth_unregister_usage"] ?? "§cUsage: /xauth unregister <player>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                try {
                    $this->plugin->getRegistrationService()->unregisterPlayerByAdmin($playerName);
                    $sender->sendMessage((string)( $messages["unregister_success"] ?? "§aPlayer account has been unregistered successfully." ));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                }
                break;
            case "forcepasswordchange":
            case "forcepass":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)( $messages["xauth_forcepasswordchange_usage"] ?? "§cUsage: /xauth forcepasswordchange <player>" ));
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                try {
                    $this->plugin->getAuthenticationService()->forcePasswordChangeByAdmin($playerName);
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["xauth_forcepasswordchange_success"] ?? "§aPlayer {player_name} will be forced to change their password on next login." )));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                }
                break;
            case "migrate-provider":
                if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                    $sender->sendMessage((string)( $messages["command_only_in_console"] ?? "§cThis command can only be used in the console." ));
                    return false;
                }
                if (count($args) !== 2) {
                    $sender->sendMessage((string)( $messages["xauth_migrate_usage"] ?? "§cUsage: /xauth migrate-provider <source_provider> <destination_provider>" ));
                    return false;
                }
                $sourceProviderType = strtolower($args[0]);
                $destinationProviderType = strtolower($args[1]);

                try {
                    $sender->sendMessage(str_replace(['{source_provider}', '{destination_provider}'], [$sourceProviderType, $destinationProviderType], (string)( $messages["xauth_migration_start"] ?? "§eStarting migration from '{source_provider}' to '{destination_provider}'..." )));
                    
                    $results = $this->plugin->getMigrationManager()->migrate($sourceProviderType, $destinationProviderType);

                    $sender->sendMessage(str_replace('{count}', (string)$results['total'], (string)($messages["xauth_migration_found_players"] ?? "§aFound {count} players to migrate.")));
                    $sender->sendMessage((string)( $messages["xauth_migration_complete"] ?? "§aMigration complete!" ));
                    $sender->sendMessage(str_replace('{count}', (string)$results['migrated'], (string)( $messages["xauth_migration_migrated_count"] ?? "§a- Migrated: {count} players" )));
                    $sender->sendMessage(str_replace('{count}', (string)$results['skipped'], (string)( $messages["xauth_migration_skipped_count"] ?? "§e- Skipped (already exist): {count} players" )));

                } catch (\InvalidArgumentException $e) {
                    $sender->sendMessage((string)( $messages["xauth_migration_error_prefix"] ?? "§cError: " ) . $e->getMessage());
                } catch (\Throwable $t) {
                    $sender->sendMessage((string)( $messages["xauth_migration_unexpected_error_prefix"] ?? "§cAn unexpected error occurred during migration: " ) . $t->getMessage());
                }
                break;
            case "status":
                if (count($args) < 1) {
                    $sender->sendMessage((string)( $messages["xauth_status_usage"] ?? "§cUsage: /xauth status <list|end> [player]" ));
                    return false;
                }
                $statusSubCommand = strtolower((string)array_shift($args));
                switch ($statusSubCommand) {
                    case "list":
                        $onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
                        $header = str_replace("{count}", (string)count($onlinePlayers), (string)( $messages["xauth_status_list_header"] ?? "§e--- Online Players ({count}) ---" ));
                        $sender->sendMessage($header);
                        foreach ($onlinePlayers as $player) {
                            $status = $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player) ? (string)( $messages["xauth_status_authenticated"] ?? "§aAuthenticated" ) : (string)( $messages["xauth_status_unauthenticated"] ?? "§eUnauthenticated" );
                            $sender->sendMessage("§f- " . $player->getName() . ": " . $status);
                        }
                        break;
                    case "end":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)( $messages["xauth_status_end_usage"] ?? "§cUsage: /xauth status end <player>" ));
                            return false;
                        }
                        $playerName = (string)( $args[0] ?? '' );
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player === null) {
                            $sender->sendMessage((string)( $messages["xauth_player_not_online"] ?? "§cPlayer not found." ));
                            return false;
                        }
                        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                            $sender->sendMessage((string)( $messages["xauth_player_not_authenticated"] ?? "§cPlayer is not authenticated." ));
                            return false;
                        }
                        $this->plugin->getAuthenticationService()->handleLogout($player);
                        $sender->sendMessage(str_replace("{player_name}", $player->getName(), (string)( $messages["xauth_status_end_success"] ?? "§aSession for player {player_name} has been ended." )));
                        break;
                    default:
                        $sender->sendMessage((string)( $messages["xauth_status_unknown_subcommand"] ?? "§cUnknown status subcommand. Use /xauth status <list|end>" ));
                        break;
                }
                break;
            case "sessions":
                if (count($args) < 1) {
                    $sender->sendMessage((string)( $messages["xauth_sessions_usage"] ?? "§cUsage: /xauth sessions <list|terminate|terminateall|cleanup> [args]" ));
                    return false;
                }
                $sessionSubCommand = strtolower((string)array_shift($args));
                $sessionService = $this->plugin->getSessionService();
                switch ($sessionSubCommand) {
                    case "list":
                        $playerName = (string)( $args[0] ?? ($sender instanceof Player ? $sender->getName() : "") );
                        if (empty($playerName)) {
                            $sender->sendMessage((string)( $messages["xauth_sessions_list_usage"] ?? "§cUsage: /xauth sessions list <player>" ));
                            return false;
                        }
                        $sessions = $sessionService->getSessionsForPlayer($playerName);
                        if (empty($sessions)) {
                            $sender->sendMessage(str_replace("{player_name}", $playerName, (string)( $messages["xauth_sessions_no_sessions"] ?? "§eNo active sessions found for {player_name}." )));
                            return false;
                        }

                        $outputLines = [];
                        $outputLines[] = str_replace(["{player_name}", "{count}"], [$playerName, (string)count($sessions)], (string)( $messages["xauth_sessions_list_header"] ?? "§e--- Sessions for {player_name} ({count}) ---" ));

                        foreach ($sessions as $sessionId => $session) {
                            $ipAddress = (string)( $session['ip_address'] ?? 'N/A' );
                            $loginTime = date("Y-m-d H:i:s", (int)( $session['login_time'] ?? 0 ));
                            $lastActivity = date("Y-m-d H:i:s", (int)( $session['last_activity'] ?? 0 ));
                            $expirationTime = date("Y-m-d H:i:s", (int)( $session['expiration_time'] ?? 0 ));
                            $outputLines[] = str_replace(
                                ['{session_id}', '{ip_address}', '{login_time}', '{last_activity}', '{expiration_time}'],
                                [$sessionId, $ipAddress, $loginTime, $lastActivity, $expirationTime],
                                (string)( $messages['xauth_sessions_list_entry'] ?? "ID: {session_id} | IP: {ip_address} | Login: {login_time} | Last Activity: {last_activity} | Expires: {expiration_time}" )
                            );
                        }
                        $sender->sendMessage(implode("\n", $outputLines));
                        break;
                    case "terminate":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)( $messages["xauth_sessions_terminate_usage"] ?? "§cUsage: /xauth sessions terminate <session_id>" ));
                            return false;
                        }
                        $sessionId = (string)( $args[0] ?? '' );
                        if ($sessionService->terminateSession($sessionId)) {
                            $sender->sendMessage(str_replace("{session_id}", $sessionId, (string)( $messages["xauth_sessions_terminate_success"] ?? "§aSession {session_id} terminated." )));
                        } else {
                            $sender->sendMessage((string)( $messages["xauth_sessions_terminate_not_found"] ?? "§cSession not found or already expired." ));
                        }
                        break;
                    case "terminateall":
                        $playerName = (string)( $args[0] ?? ($sender instanceof Player ? $sender->getName() : "") );
                        if (empty($playerName)) {
                            $sender->sendMessage((string)( $messages["xauth_sessions_terminateall_usage"] ?? "§cUsage: /xauth sessions terminateall <player>" ));
                            return false;
                        }
                        $sessionService->terminateAllSessionsForPlayer($playerName);
                        $sender->sendMessage(str_replace("{player_name}", $playerName, (string)( $messages["xauth_sessions_terminateall_success"] ?? "§aAll sessions for {player_name} terminated." )));
                        break;
                    case "cleanup":
                        if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                            $sender->sendMessage((string)( $messages["command_only_in_console"] ?? "§cThis command can only be used in the console." ));
                            return false;
                        }
                        $sessionService->cleanupExpiredSessions();
                        $sender->sendMessage((string)( $messages["xauth_sessions_cleanup_success"] ?? "§aExpired sessions cleaned up." ));
                        break;
                    default:
                        $sender->sendMessage((string)( $messages["xauth_sessions_unknown_subcommand"] ?? "§cUnknown sessions subcommand. Use /xauth sessions <list|terminate|terminateall|cleanup>" ));
                        break;
                }
                break;
            case "reload":
                $this->plugin->getPluginControlService()->reload();
                $sender->sendMessage((string)( $messages["xauth_reload_success"] ?? "§aXAuth configuration reloaded." ));
                break;
            case "checkpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage("§cUsage: /xauth checkpassword <player> <password>");
                    return false;
                }
                $playerName = (string)( $args[0] ?? '' );
                $password = (string)( $args[1] ?? '' );

                try {
                    $isValid = $this->plugin->getAuthenticationService()->checkPlayerPassword($playerName, $password);
                    $sender->sendMessage("§e--- Password Check for " . $playerName . " ---");
                    $sender->sendMessage("§fPassword to check: §e" . $password);
                    $sender->sendMessage("§fVerification Result: " . ($isValid ? "§aMATCH" : "§cNO MATCH"));
                } catch (NotRegisteredException $e) {
                    $sender->sendMessage(str_replace('{player_name}', $playerName, (string)( $messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                }
                break;
            default:
                $sender->sendMessage((string)( $messages["xauth_unknown_subcommand"] ?? "§cUnknown subcommand. Use /xauth help for a list of commands." ));
                break;
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
