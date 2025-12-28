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

namespace Luthfi\XAuth\service;

use Generator;
use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use Luthfi\XAuth\event\PlayerAuthenticationFailedEvent;
use Luthfi\XAuth\event\PlayerChangePasswordEvent;
use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use Luthfi\XAuth\event\PlayerPreAuthenticateEvent;
use Luthfi\XAuth\exception\AccountLockedException;
use Luthfi\XAuth\exception\AlreadyLoggedInException;
use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\NotRegisteredException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\exception\PlayerBlockedException;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use RuntimeException;

class AuthenticationService {

    private Main $plugin;

    /** @var array<string, bool> */
    private array $authenticatedPlayers = [];

    /** @var array<string, array{attempts: int, last_attempt_time: int}> */
    private array $loginAttempts = [];

    /** @var array<string, bool> */
    private array $forcePasswordChange = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function finalizeAuthentication(Player $player, AuthenticationContext $context): Generator {
        $this->plugin->getTitleManager()->clearTitle($player);
        $this->plugin->cancelKickTask($player);
        yield from $this->plugin->getDataProvider()->updatePlayerIp($player);
        $this->authenticatePlayer($player);

        if ((bool)$this->plugin->getConfig()->getNested('auto-login.enabled', false)) {
            yield from $this->plugin->getSessionService()->handleSession($player);
        }

        $this->plugin->getPlayerStateService()->restorePlayerState($player);
        $this->plugin->getPlayerVisibilityService()->updatePlayerVisibility($player);

        $loginType = $context->getLoginType();
        if ($loginType === PlayerPreAuthenticateEvent::LOGIN_TYPE_MANUAL) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_success"] ?? "You have successfully logged in!");
            $player->sendMessage($message);
            $this->plugin->getTitleManager()->sendTitle($player, "login_success", 2 * 20);
        } elseif ($loginType === PlayerPreAuthenticateEvent::LOGIN_TYPE_REGISTRATION) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_success"] ?? "You have successfully registered!");
            $player->sendMessage($message);
            $this->plugin->getTitleManager()->sendTitle($player, "register_success", 2 * 20);
        }

        (new PlayerAuthenticateEvent($player, $loginType))->call();
    }

    public function authenticatePlayer(Player $player): void {
        $this->authenticatedPlayers[strtolower($player->getName())] = true;
        $this->clearLoginAttempts($player);
    }

    public function deauthenticatePlayer(Player $player): void {
        unset($this->authenticatedPlayers[strtolower($player->getName())]);
    }

    public function isPlayerAuthenticated(Player $player): bool {
        return isset($this->authenticatedPlayers[strtolower($player->getName())]);
    }

    public function getAuthenticatedPlayers(): array {
        return array_keys($this->authenticatedPlayers);
    }

    public function incrementLoginAttempts(Player $player): Generator {
        $name = strtolower($player->getName());
        if (!isset($this->loginAttempts[$name])) {
            $this->loginAttempts[$name] = ['attempts' => 0, 'last_attempt_time' => 0];
        }
        $this->loginAttempts[$name]['attempts']++;
        $this->loginAttempts[$name]['last_attempt_time'] = time();

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 5);
        if ($this->loginAttempts[$name]['attempts'] >= $maxAttempts) {
            $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 10);
            yield from $this->plugin->getDataProvider()->setBlockedUntil($name, time() + ($blockTimeMinutes * 60));
            $this->clearLoginAttempts($player);
        }
    }

    public function isPlayerBlocked(Player $player, int $maxAttempts, int $blockTimeMinutes): Generator {
        $blockedUntil = yield from $this->plugin->getDataProvider()->getBlockedUntil($player->getName());
        if ($blockedUntil > time()) {
            return true;
        }

        $name = strtolower($player->getName());
        return isset($this->loginAttempts[$name]) && $this->loginAttempts[$name]['attempts'] >= $maxAttempts;
    }

    public function getRemainingBlockTime(Player $player, int $blockTimeMinutes): Generator {
        $blockedUntil = yield from $this->plugin->getDataProvider()->getBlockedUntil($player->getName());
        if ($blockedUntil > time()) {
            return (int)ceil(($blockedUntil - time()) / 60);
        }
        return 0;
    }

    public function clearLoginAttempts(Player $player): void {
        unset($this->loginAttempts[strtolower($player->getName())]);
    }

    public function isPlayerBlockedByName(string $name, int $maxAttempts, int $blockTimeMinutes): Generator {
        $blockedUntil = yield from $this->plugin->getDataProvider()->getBlockedUntil($name);
        return $blockedUntil > time();
    }

    public function getRemainingBlockTimeByName(string $name, int $blockTimeMinutes): Generator {
        $blockedUntil = yield from $this->plugin->getDataProvider()->getBlockedUntil($name);
        if ($blockedUntil > time()) {
            return (int)ceil(($blockedUntil - time()) / 60);
        }
        return 0;
    }

    public function handleLoginRequest(Player $player, string $password): Generator {
        if ($this->isPlayerAuthenticated($player)) {
            throw new AlreadyLoggedInException();
        }

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 0);

        if ($enabled && (yield from $this->isPlayerBlocked($player, $maxAttempts, $blockTimeMinutes))) {
            $remainingMinutes = yield from $this->getRemainingBlockTime($player, $blockTimeMinutes);
            throw new PlayerBlockedException($remainingMinutes);
        }

        $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData === null) {
            throw new NotRegisteredException();
        }

        if (yield from $this->plugin->getDataProvider()->isPlayerLocked($player->getName())) {
            throw new AccountLockedException();
        }

        $storedPasswordHash = (string)($playerData["password"] ?? '');
        $passwordHasher = $this->plugin->getPasswordHasher();

        if (!$passwordHasher->verifyPassword($password, $storedPasswordHash)) {
            yield from $this->incrementLoginAttempts($player);

            $failedAttempts = $this->loginAttempts[strtolower($player->getName())]['attempts'] ?? 1;
            $event = new PlayerAuthenticationFailedEvent($player, $failedAttempts);
            $event->call();

            if ($event->isCancelled()) {
                return;
            }

            throw new IncorrectPasswordException();
        }

        if ($passwordHasher->needsRehash($storedPasswordHash)) {
            $newHashedPassword = $passwordHasher->hashPassword($password);
            yield from $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
        }

        $context = $this->plugin->getAuthenticationFlowManager()->ensureContextExists($player);
        if ($context === null) {
            $this->plugin->getLogger()->critical("ensureContextExists returned null for player " . $player->getName());
        }
        $context->setLoginType(PlayerPreAuthenticateEvent::LOGIN_TYPE_MANUAL);
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, 'xauth_login');
    }

    public function handleChangePasswordRequest(Player $player, string $oldPassword, string $newPassword, string $confirmNewPassword): Generator {
        $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData === null) {
            throw new NotRegisteredException();
        }

        $passwordHasher = $this->plugin->getPasswordHasher();
        $currentHashedPassword = (string)($playerData["password"] ?? '');

        if (!$passwordHasher->verifyPassword($oldPassword, $currentHashedPassword)) {
            throw new IncorrectPasswordException();
        }

        if ($passwordHasher->needsRehash($currentHashedPassword)) {
            $currentHashedPassword = $passwordHasher->hashPassword($oldPassword);
            yield from $this->plugin->getDataProvider()->changePassword($player, $currentHashedPassword);
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            throw new InvalidCommandSyntaxException($message);
        }

        if ($newPassword !== $confirmNewPassword) {
            throw new PasswordMismatchException();
        }

        $newHashedPassword = $passwordHasher->hashPassword($newPassword);
        yield from $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
        (new PlayerChangePasswordEvent($player))->call();
    }

    public function handleChangePasswordRequestByName(string $username, string $oldPassword, string $newPassword, string $confirmNewPassword): Generator {
        $offlinePlayer = Server::getInstance()->getOfflinePlayer($username);
        $playerData = yield from $this->plugin->getDataProvider()->getPlayer($offlinePlayer);
        if ($playerData === null) {
            throw new NotRegisteredException();
        }

        $passwordHasher = $this->plugin->getPasswordHasher();
        $currentHashedPassword = (string)($playerData["password"] ?? '');

        if (!$passwordHasher->verifyPassword($oldPassword, $currentHashedPassword)) {
            throw new IncorrectPasswordException();
        }

        if ($passwordHasher->needsRehash($currentHashedPassword)) {
            $currentHashedPassword = $passwordHasher->hashPassword($oldPassword);
            yield from $this->plugin->getDataProvider()->changePassword($offlinePlayer, $currentHashedPassword);
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            throw new InvalidCommandSyntaxException($message);
        }

        if ($newPassword !== $confirmNewPassword) {
            throw new PasswordMismatchException();
        }

        $newHashedPassword = $this->plugin->getPasswordHasher()->hashPassword($newPassword);
        yield from $this->plugin->getDataProvider()->changePassword($offlinePlayer, $newHashedPassword);

        // We don't call PlayerChangePasswordEvent because the player is offline.
    }

    public function handleForceChangePasswordRequest(Player $player, string $newPassword, string $confirmNewPassword): Generator {
        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            throw new InvalidCommandSyntaxException($message);
        }

        if ($newPassword !== $confirmNewPassword) {
            throw new PasswordMismatchException();
        }

        $passwordHasher = $this->plugin->getPasswordHasher();
        $newHashedPassword = $passwordHasher->hashPassword($newPassword);
        yield from $this->plugin->getDataProvider()->changePassword($player, $newHashedPassword);
        yield from $this->plugin->getDataProvider()->setMustChangePassword($player->getName(), false);
        $this->stopForcePasswordChange($player);

        (new PlayerChangePasswordEvent($player))->call();
    }

    public function handleLogout(Player $player): Generator {
        $this->plugin->cancelKickTask($player);
        $this->plugin->getTitleManager()->clearTitle($player);

        $this->deauthenticatePlayer($player);

        $this->plugin->getPlayerStateService()->protectPlayer($player);
        $this->plugin->scheduleKickTask($player);

        $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData !== null) {
            $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_prompt"] ?? "");
            $player->sendMessage($message);
            if ($formsEnabled) {
                $this->plugin->getFormManager()->sendLoginForm($player);
            } else {
                $this->plugin->getTitleManager()->sendTitle($player, "login_prompt", null, true);
            }
        } else {
            $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
            $player->sendMessage($message);
            if ($formsEnabled) {
                $this->plugin->getFormManager()->sendRegisterForm($player);
            } else {
                $this->plugin->getTitleManager()->sendTitle($player, "register_prompt", null, true);
            }
        }

        (new PlayerDeauthenticateEvent($player, false))->call();
    }

    public function handleQuit(Player $player): void {
        $this->plugin->cancelKickTask($player);
        $this->plugin->getTitleManager()->clearTitle($player);

        $this->deauthenticatePlayer($player);

        $this->plugin->getPlayerStateService()->restorePlayerState($player);

        (new PlayerDeauthenticateEvent($player, true))->call();
    }

    public function startForcePasswordChange(Player $player): void {
        $this->forcePasswordChange[strtolower($player->getName())] = true;
        $this->plugin->getFormManager()->sendForceChangePasswordForm($player);
    }

    public function stopForcePasswordChange(Player $player): void {
        unset($this->forcePasswordChange[strtolower($player->getName())]);
    }

    public function isForcingPasswordChange(Player $player): bool {
        return isset($this->forcePasswordChange[strtolower($player->getName())]);
    }

    public function forcePasswordChangeByAdmin(string $playerName): Generator {
        if (!(yield from $this->plugin->getDataProvider()->isPlayerRegistered($playerName))) {
            throw new NotRegisteredException();
        }

        yield from $this->plugin->getDataProvider()->setMustChangePassword($playerName, true);

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        $forceImmediate = (bool)$this->plugin->getConfig()->getNested("command_settings.force_change_immediate", true);

        if ($player !== null && $forceImmediate) {
            $this->startForcePasswordChange($player);
        }
    }

    public function lockAccount(string $playerName): Generator {
        if (!(yield from $this->plugin->getDataProvider()->isPlayerRegistered($playerName))) {
            throw new NotRegisteredException();
        }
        yield from $this->plugin->getDataProvider()->setPlayerLocked($playerName, true);
    }

    public function unlockAccount(string $playerName): Generator {
        if (!(yield from $this->plugin->getDataProvider()->isPlayerRegistered($playerName))) {
            throw new NotRegisteredException();
        }
        yield from $this->plugin->getDataProvider()->setPlayerLocked($playerName, false);
    }

    public function setPlayerPassword(string $playerName, string $newPassword): Generator {
        if (!(yield from $this->plugin->getDataProvider()->isPlayerRegistered($playerName))) {
            throw new NotRegisteredException();
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($newPassword)) !== null) {
            throw new InvalidCommandSyntaxException($message);
        }

        $passwordHasher = $this->plugin->getPasswordHasher();
        $newHashedPassword = $passwordHasher->hashPassword($newPassword);
        $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
        yield from $this->plugin->getDataProvider()->changePassword($offlinePlayer, $newHashedPassword);
    }

    public function checkPlayerPassword(string $playerName, string $password): Generator {
        $playerData = yield from $this->plugin->getDataProvider()->getPlayer(Server::getInstance()->getOfflinePlayer($playerName));
        if ($playerData === null) {
            throw new NotRegisteredException();
        }

        $storedHash = (string)($playerData["password"] ?? '');
        return $this->plugin->getPasswordHasher()->verifyPassword($password, $storedHash);
    }

    public function getPlayerLookupData(string $playerName): Generator {
        $offlinePlayer = Server::getInstance()->getOfflinePlayer($playerName);
        $playerData = yield from $this->plugin->getDataProvider()->getPlayer($offlinePlayer);

        if ($playerData === null) {
            return null;
        }

        $lastLoginIp = "N/A";
        $lastLoginTime = "N/A";

        $autoLoginEnabled = (bool)($this->plugin->getConfig()->getNested("auto-login.enabled") ?? false);
        if ($autoLoginEnabled) {
            $sessions = yield from $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
            if (!empty($sessions)) {
                $latestSession = current($sessions);
                $lastLoginIp = (string)($latestSession['ip_address'] ?? "N/A");
                $lastLoginTime = (isset($latestSession["login_time"])) ? date("Y-m-d H:i:s", (int)$latestSession["login_time"]) : "N/A";
            }
        } else {
            $lastLoginIp = (string)($playerData["ip"] ?? "N/A");
            $lastLoginTime = (isset($playerData["last_login_at"]) ? date("Y-m-d H:i:s", (int)$playerData["last_login_at"]) : "N/A");
        }

        $isPlayerLocked = yield from $this->plugin->getDataProvider()->isPlayerLocked($playerName);

        return [
            'player_name' => $playerName,
            'registered_at' => (isset($playerData["registered_at"]) ? date("Y-m-d H:i:s", (int)$playerData["registered_at"]) : "N/A"),
            'registration_ip' => (isset($playerData["registration_ip"]) ? (string)$playerData["registration_ip"] : "N/A"),
            'last_login_ip' => $lastLoginIp,
            'last_login_at' => $lastLoginTime,
            'locked_status' => ($isPlayerLocked ? "Yes" : "No")
        ];
    }
}
