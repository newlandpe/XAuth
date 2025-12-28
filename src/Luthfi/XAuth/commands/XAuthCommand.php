<?php

/*
 *
 *  _          _   _     __  __  ____ _      __  __    _         _   _
 * | |   _   _| |_| |__ |  \/  |/ ___( )___  \ \/ /   / \  _   _| |_| |__
 * | |  | | | | __| '_ \| |\/| | |   |// __|  \  /   / _ \| | | | __| '_ \
 * | |__| |_| | |_| | | | |  | | |___  \__ \  /  \  / ___ \ |_| | |_| | | |
 * |_____\__,_|\__|_| |_|_|  |_|\____| |___/ /_/\_\/_/   \_\__,_|\__|_| |_|
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author LuthMC
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace Luthfi\XAuth\commands;

use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use Throwable;

class XAuthCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(
        private readonly Main $plugin
    ) {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        parent::__construct(
            "xauth",
            (string)($messages["xauth_command_description"] ?? "XAuth admin commands" ),
            (string)($messages["xauth_command_usage"] ?? "/xauth <subcommand> [args]" )
        );
        $this->setPermission("xauth.command.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        $messages = (array)$this->plugin->getCustomMessages()->get("messages");

        if (count($args) < 1) {
            $sender->sendMessage((string)($messages["xauth_command_usage"] ?? "§cUsage: /xauth <subcommand> [args]" ));
            return false;
        }

        $subCommand = strtolower((string)array_shift($args));

        switch ($subCommand) {
            case "help":
                $sender->sendMessage((string)($messages["xauth_help_message"] ?? "--- XAuth Help ---" ));
                break;
            case "lock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_lock_usage"] ?? "§cUsage: /xauth lock <player>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->lockAccount($playerName),
                    function() use ($sender, $playerName, $messages): void {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_player_locked"] ?? "§aPlayer {player_name} has been locked." )));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            case "unlock":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_unlock_usage"] ?? "§cUsage: /xauth unlock <player>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->unlockAccount($playerName),
                    function() use ($sender, $playerName, $messages): void {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_player_unlocked"] ?? "§aPlayer {player_name} has been unlocked." )));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            case "lookup":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_lookup_usage"] ?? "§cUsage: /xauth lookup <player>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->getPlayerLookupData($playerName),
                    function(?array $playerData) use ($sender, $playerName, $messages): void {
                        if ($playerData === null) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                            return;
                        }
                        $lookupMessage = str_replace(array_keys($playerData), array_values($playerData), (string)($messages["xauth_player_lookup_info"] ?? "Player: {player_name}" ));
                        $sender->sendMessage($lookupMessage);
                    },
                    fn() => $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ))
                );
                break;
            case "setpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage((string)($messages["xauth_setpassword_usage"] ?? "§cUsage: /xauth setpassword <player> <password>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                $newPassword = (string)($args[1] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->setPlayerPassword($playerName, $newPassword),
                    function() use ($sender, $messages): void {
                        $sender->sendMessage((string)($messages["set_password_success"] ?? "§aPlayer password has been set successfully." ));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } elseif ($e instanceof InvalidCommandSyntaxException) {
                            $sender->sendMessage($e->getMessage());
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            case "unregister":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_unregister_usage"] ?? "§cUsage: /xauth unregister <player>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                Await::g2c(
                    $this->plugin->getRegistrationService()->unregisterPlayerByAdmin($playerName),
                    function() use ($sender, $messages): void {
                        $sender->sendMessage((string)($messages["unregister_success"] ?? "§aPlayer account has been unregistered successfully." ));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            case "forcepasswordchange":
            case "forcepass":
                if (count($args) !== 1) {
                    $sender->sendMessage((string)($messages["xauth_forcepasswordchange_usage"] ?? "§cUsage: /xauth forcepasswordchange <player>" ));
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->forcePasswordChangeByAdmin($playerName),
                    function() use ($sender, $playerName, $messages): void {
                        $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["xauth_forcepasswordchange_success"] ?? "§aPlayer {player_name} will be forced to change their password on next login." )));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            case "migrate-provider":
                if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                    $sender->sendMessage((string)($messages["command_only_in_console"] ?? "§cThis command can only be used in the console." ));
                    return false;
                }
                if (count($args) !== 2) {
                    $sender->sendMessage((string)($messages["xauth_migrate_usage"] ?? "§cUsage: /xauth migrate-provider <source_provider> <destination_provider>" ));
                    return false;
                }
                $sourceProviderType = strtolower($args[0]);
                $destinationProviderType = strtolower($args[1]);

                try {
                    $this->plugin->getMigrationManager()->migrate($sourceProviderType, $destinationProviderType);
                } catch (Throwable $error) {
                    $sender->sendMessage((string)($messages["xauth_migration_unexpected_error_prefix"] ?? "§cAn unexpected error occurred during migration: " ) . $error->getMessage());
                }
                break;
            case "status":
                if (count($args) < 1) {
                    $sender->sendMessage((string)($messages["xauth_status_usage"] ?? "§cUsage: /xauth status <list|end> [player]" ));
                    return false;
                }
                $statusSubCommand = strtolower((string)array_shift($args));
                switch ($statusSubCommand) {
                    case "list":
                        $onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
                        $header = str_replace("{count}", (string)count($onlinePlayers), (string)($messages["xauth_status_list_header"] ?? "§e--- Online Players ({count}) ---" ));
                        $sender->sendMessage($header);
                        foreach ($onlinePlayers as $player) {
                            $status = $this->plugin->getAuthenticationService()->isPlayerAuthenticated($player) ? (string)($messages["xauth_status_authenticated"] ?? "§aAuthenticated" ) : (string)($messages["xauth_status_unauthenticated"] ?? "§eUnauthenticated" );
                            $sender->sendMessage("§f- " . $player->getName() . ": " . $status);
                        }
                        break;
                    case "end":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)($messages["xauth_status_end_usage"] ?? "§cUsage: /xauth status end <player>" ));
                            return false;
                        }
                        $playerName = (string)($args[0] ?? '' );
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player === null) {
                            $sender->sendMessage((string)($messages["xauth_player_not_online"] ?? "§cPlayer not found." ));
                            return false;
                        }
                        if (!$this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                            $sender->sendMessage((string)($messages["xauth_player_not_authenticated"] ?? "§cPlayer is not authenticated." ));
                            return false;
                        }
                        Await::g2c(
                            $this->plugin->getAuthenticationService()->handleLogout($player),
                            fn() => $sender->sendMessage(str_replace("{player_name}", $player->getName(), (string)($messages["xauth_status_end_success"] ?? "§aSession for player {player_name} has been ended." )))
                        );
                        break;
                    default:
                        $sender->sendMessage((string)($messages["xauth_status_unknown_subcommand"] ?? "§cUnknown status subcommand. Use /xauth status <list|end>" ));
                        break;
                }
                break;
            case "sessions":
                if (count($args) < 1) {
                    $sender->sendMessage((string)($messages["xauth_sessions_usage"] ?? "§cUsage: /xauth sessions <list|terminate|terminateall|cleanup> [args]" ));
                    return false;
                }
                $sessionSubCommand = strtolower((string)array_shift($args));
                $sessionService = $this->plugin->getSessionService();
                switch ($sessionSubCommand) {
                    case "list":
                        $playerName = (string)($args[0] ?? ($sender instanceof Player ? $sender->getName() : "") );
                        if (empty($playerName)) {
                            $sender->sendMessage((string)($messages["xauth_sessions_list_usage"] ?? "§cUsage: /xauth sessions list <player>" ));
                            return false;
                        }
                        Await::g2c(
                            $sessionService->getSessionsForPlayer($playerName),
                            function(array $sessions) use ($sender, $playerName, $messages): void {
                                if (empty($sessions)) {
                                    $sender->sendMessage(str_replace("{player_name}", $playerName, (string)($messages["xauth_sessions_no_sessions"] ?? "§eNo active sessions found for {player_name}." )));
                                    return;
                                }
                                $outputLines = [str_replace(["{player_name}", "{count}"], [$playerName, (string)count($sessions)], (string)($messages["xauth_sessions_list_header"] ?? "§e--- Sessions for {player_name} ({count}) ---" ))];
                                foreach ($sessions as $sessionId => $session) {
                                    $outputLines[] = str_replace(
                                        ['{session_id}', '{ip_address}', '{login_time}', '{last_activity}', '{expiration_time}'],
                                        [$sessionId, (string)($session['ip_address'] ?? 'N/A' ), date("Y-m-d H:i:s", (int)($session['login_time'] ?? 0 )), date("Y-m-d H:i:s", (int)($session['last_activity'] ?? 0 )), date("Y-m-d H:i:s", (int)($session['expiration_time'] ?? 0 ))],
                                        (string)($messages['xauth_sessions_list_entry'] ?? "ID: {session_id} | IP: {ip_address} | Login: {login_time} | Last Activity: {last_activity} | Expires: {expiration_time}" )
                                    );
                                }
                                $sender->sendMessage(implode("\n", $outputLines));
                            }
                        );
                        break;
                    case "terminate":
                        if (count($args) !== 1) {
                            $sender->sendMessage((string)($messages["xauth_sessions_terminate_usage"] ?? "§cUsage: /xauth sessions terminate <session_id>" ));
                            return false;
                        }
                        $sessionId = (string)($args[0] ?? '' );
                        Await::g2c(
                            $sessionService->terminateSession($sessionId),
                            function(bool $terminated) use ($sender, $sessionId, $messages): void {
                                if ($terminated) {
                                    $sender->sendMessage(str_replace("{session_id}", $sessionId, (string)($messages["xauth_sessions_terminate_success"] ?? "§aSession {session_id} terminated." )));
                                } else {
                                    $sender->sendMessage((string)($messages["xauth_sessions_terminate_not_found"] ?? "§cSession not found or already expired." ));
                                }
                            }
                        );
                        break;
                    case "terminateall":
                        $playerName = (string)($args[0] ?? ($sender instanceof Player ? $sender->getName() : "") );
                        if (empty($playerName)) {
                            $sender->sendMessage((string)($messages["xauth_sessions_terminateall_usage"] ?? "§cUsage: /xauth sessions terminateall <player>" ));
                            return false;
                        }
                        Await::g2c(
                            $sessionService->terminateAllSessionsForPlayer($playerName),
                            fn() => $sender->sendMessage(str_replace("{player_name}", $playerName, (string)($messages["xauth_sessions_terminateall_success"] ?? "§aAll sessions for {player_name} terminated." )))
                        );
                        break;
                    case "cleanup":
                        if (!$sender instanceof \pocketmine\console\ConsoleCommandSender) {
                            $sender->sendMessage((string)($messages["command_only_in_console"] ?? "§cThis command can only be used in the console." ));
                            return false;
                        }
                        Await::g2c(
                            $sessionService->cleanupExpiredSessions(),
                            fn() => $sender->sendMessage((string)($messages["xauth_sessions_cleanup_success"] ?? "§aExpired sessions cleaned up." ))
                        );
                        break;
                    default:
                        $sender->sendMessage((string)($messages["xauth_sessions_unknown_subcommand"] ?? "§cUnknown sessions subcommand. Use /xauth sessions <list|terminate|terminateall|cleanup>" ));
                        break;
                }
                break;
            case "reload":
                $this->plugin->getPluginControlService()->reload();
                $sender->sendMessage((string)($messages["xauth_reload_success"] ?? "§aXAuth configuration reloaded." ));
                break;
            case "checkpassword":
                if (count($args) !== 2) {
                    $sender->sendMessage("§cUsage: /xauth checkpassword <player> <password>");
                    return false;
                }
                $playerName = (string)($args[0] ?? '' );
                $password = (string)($args[1] ?? '' );
                Await::g2c(
                    $this->plugin->getAuthenticationService()->checkPlayerPassword($playerName, $password),
                    function(bool $isValid) use ($sender, $playerName, $password): void {
                        $sender->sendMessage("§e--- Password Check for " . $playerName . " ---");
                        $sender->sendMessage("§fPassword to check: §e" . $password);
                        $sender->sendMessage("§fVerification Result: " . ($isValid ? "§aMATCH" : "§cNO MATCH"));
                    },
                    function(Throwable $e) use ($sender, $playerName, $messages): void {
                        if ($e instanceof NotRegisteredException) {
                            $sender->sendMessage(str_replace('{player_name}', $playerName, (string)($messages["player_not_registered"] ?? "§cPlayer {player_name} is not registered." )));
                        } else {
                            $sender->sendMessage((string)($messages["unexpected_error"] ?? "§cAn unexpected error occurred." ));
                        }
                    }
                );
                break;
            default:
                $sender->sendMessage((string)($messages["xauth_unknown_subcommand"] ?? "§cUnknown subcommand. Use /xauth help for a list of commands." ));
                break;
        }
        return true;
    }
}
