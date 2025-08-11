<?php

declare(strict_types=1);

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\event\PlayerDeauthenticateEvent;
use Luthfi\XAuth\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;

class PlayerSessionListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param PlayerPreLoginEvent $event
     * @priority MONITOR
     */
    public function onPlayerPreLogin(PlayerPreLoginEvent $event): void {
        $ip = $event->getIp();
        $ipLimits = (array)$this->plugin->getConfig()->get('ip_limits');
        $maxJoinsPerIp = (int)($ipLimits['max_joins_per_ip'] ?? 0);

        if ($maxJoinsPerIp > 0) {
            $onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
            $ipCounts = [];
            foreach ($onlinePlayers as $player) {
                $playerIp = $player->getNetworkSession()->getIp();
                if (!isset($ipCounts[$playerIp])) {
                    $ipCounts[$playerIp] = 0;
                }
                $ipCounts[$playerIp]++;
            }

            if (isset($ipCounts[$ip]) && $ipCounts[$ip] >= $maxJoinsPerIp) {
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["ip_join_limit_exceeded"] ?? "Connection limit exceeded for your IP address.");
                $event->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $message);
                return;
            }
        }

        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        $name = $event->getPlayerInfo()->getUsername();

        // Capture DeviceId for later use in onJoin
        $extraData = $event->getPlayerInfo()->getExtraData();
        if(isset($extraData['DeviceId'])){
            $this->plugin->deviceIds[strtolower($name)] = $extraData['DeviceId'];
        }

        if (!(bool)($bruteforceConfig['kick_at_pre_login'] ?? true)) {
            return;
        }

        if ($this->plugin->getDataProvider()->isPlayerLocked($name)) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["account_locked_by_admin"] ?? "");
            $event->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $message);
            return;
        }

        if ((bool)($bruteforceConfig['enabled'] ?? false) && $this->plugin->getDataProvider()->getBlockedUntil($name) > time()) {
            $remainingMinutes = (int)ceil(($this->plugin->getDataProvider()->getBlockedUntil($name) - time()) / 60);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_attempts_exceeded"] ?? "");
            $message = str_replace('{minutes}', (string)$remainingMinutes, $message);
            $event->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $message);
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();
        $playerName = $player->getName();

        if ($authManager->isPlayerAuthenticated($player)) {
            return;
        }

        $this->plugin->protectPlayer($player);

        $playerData = $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData !== null) {
            if ($this->plugin->getDataProvider()->mustChangePassword($playerName)) {
                $this->plugin->startForcePasswordChange($player);
                return;
            }

            $autoLoginConfig = (array)$this->plugin->getConfig()->get("auto-login", []);
            $autoLoginEnabled = (bool)($autoLoginConfig["enabled"] ?? false);
            $securityLevel = (int)($autoLoginConfig["security_level"] ?? 1);

            if ($autoLoginEnabled) {
                $sessions = $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
                $ip = $player->getNetworkSession()->getIp();

                foreach ($sessions as $sessionData) {
                    if (($sessionData['expiration_time'] ?? 0) <= time()) {
                        continue;
                    }

                    $ipMatch = ($sessionData['ip_address'] ?? '') === $ip;
                    $deviceId = $this->plugin->deviceIds[strtolower($playerName)] ?? null;
                    $deviceIdMatch = ($sessionData['device_id'] ?? null) === $deviceId;

                    $loginSuccess = false;
                    if ($securityLevel === 1 && $ipMatch && $deviceIdMatch) {
                        $loginSuccess = true;
                    } elseif ($securityLevel === 0 && $ipMatch) {
                        $loginSuccess = true;
                    }

                    if ($loginSuccess) {
                        $this->plugin->forceLogin($player);
                        return;
                    }
                }
            }

            // Normal login flow if no auto-login occurred
            $this->plugin->scheduleKickTask($player);
            $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_prompt"] ?? "");
            $player->sendMessage($message);
            if ($formsEnabled) {
                $this->plugin->getFormManager()->sendLoginForm($player);
            } else {
                $this->plugin->sendTitleMessage($player, "login_prompt");
            }
        } else {
            // Registration flow
            $this->plugin->scheduleKickTask($player);
            $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
            $player->sendMessage($message);
            if ($formsEnabled) {
                $this->plugin->getFormManager()->sendRegisterForm($player);
            } else {
                $this->plugin->sendTitleMessage($player, "register_prompt");
            }
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $lowerPlayerName = strtolower($event->getPlayer()->getName());
        unset($this->plugin->deviceIds[$lowerPlayerName]); // Clean up in case of crash or unexpected quit
        (new PlayerDeauthenticateEvent($event->getPlayer(), true))->call();
    }
}
