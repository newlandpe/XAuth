<?php

declare(strict_types=1);

namespace Luthfi\XAuth\service;

use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\event\PlayerUnregisterEvent;
use Luthfi\XAuth\exception\AccountLockedException;
use Luthfi\XAuth\exception\AlreadyLoggedInException;
use Luthfi\XAuth\exception\AlreadyRegisteredException;
use Luthfi\XAuth\exception\ConfirmationExpiredException;
use Luthfi\XAuth\exception\IncorrectPasswordException;
use Luthfi\XAuth\exception\PasswordMismatchException;
use Luthfi\XAuth\exception\RegistrationRateLimitException;
use Luthfi\XAuth\exception\UnregistrationNotInitiatedException;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use pocketmine\command\utils\InvalidCommandSyntaxException;

class RegistrationService {

    private Main $plugin;

    /** @var array<string, int> */
    private array $confirmations = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @throws AlreadyLoggedInException
     * @throws AlreadyRegisteredException
     * @throws AccountLockedException
     * @throws RegistrationRateLimitException
     * @throws PasswordMismatchException
     * @throws InvalidCommandSyntaxException
     */
    public function handleRegistrationRequest(Player $player, string $password, string $confirmPassword): void {
        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            throw new AlreadyLoggedInException();
        }

        if ($this->plugin->getDataProvider()->isPlayerRegistered($player->getName())) {
            throw new AlreadyRegisteredException();
        }

        if ($this->plugin->getDataProvider()->isPlayerLocked($player->getName())) {
            throw new AccountLockedException();
        }

        $ipAddress = $player->getNetworkSession()->getIp();
        $maxRegistrations = (int)($this->plugin->getConfig()->getNested("registration.max_per_ip") ?? 0);
        if ($maxRegistrations > 0 && $this->plugin->getDataProvider()->getRegistrationCountByIp($ipAddress) >= $maxRegistrations) {
            throw new RegistrationRateLimitException();
        }

        if (($message = $this->plugin->getPasswordValidator()->validatePassword($password)) !== null) {
            throw new InvalidCommandSyntaxException($message);
        }

        if ($password !== $confirmPassword) {
            throw new PasswordMismatchException();
        }

        $this->plugin->cancelKickTask($player);
        $hashedPassword = $this->plugin->getPasswordHasher()->hashPassword($password);
        $this->plugin->getDataProvider()->registerPlayer($player, $hashedPassword);

        (new PlayerRegisterEvent($player))->call();
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, 'xauth_register');
    }

    

    public function initiateUnregistration(Player $player): void {
        $this->confirmations[strtolower($player->getName())] = time();
    }

    /**
     * @throws UnregistrationNotInitiatedException
     * @throws ConfirmationExpiredException
     * @throws IncorrectPasswordException
     */
    public function confirmUnregistration(Player $player, string $password): void {
        $lowerName = strtolower($player->getName());

        if (!isset($this->confirmations[$lowerName])) {
            throw new UnregistrationNotInitiatedException();
        }

        if (time() - $this->confirmations[$lowerName] > 60) {
            unset($this->confirmations[$lowerName]);
            throw new ConfirmationExpiredException();
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($player);

        if ($playerData === null || !$this->plugin->getPasswordHasher()->verifyPassword($password, (string)($playerData['password'] ?? ''))) {
            throw new IncorrectPasswordException();
        }

        unset($this->confirmations[$lowerName]);
        $this->plugin->getDataProvider()->unregisterPlayer($player->getName());
        (new PlayerUnregisterEvent($player))->call();

        $kickMessage = (string)($this->plugin->getCustomMessages()->get("messages.unregister_success_kick") ?? "§aYour account has been successfully unregistered.");
        $player->kick($kickMessage);
    }

    public function unregisterPlayerByAdmin(string $playerName): void {
        if (!$this->plugin->getDataProvider()->isPlayerRegistered($playerName)) {
            throw new NotRegisteredException();
        }

        $offlinePlayer = $this->plugin->getServer()->getOfflinePlayer($playerName);
        $this->plugin->getDataProvider()->unregisterPlayer($playerName);
        (new PlayerUnregisterEvent($offlinePlayer))->call();

        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player !== null) {
            $this->plugin->getAuthenticationService()->handleLogout($player);
            $player->sendMessage((string)(($this->plugin->getCustomMessages()->get("messages"))["account_unregistered_by_admin"] ?? "§eYour account has been unregistered by an administrator. Please register again."));
        }
    }
}
