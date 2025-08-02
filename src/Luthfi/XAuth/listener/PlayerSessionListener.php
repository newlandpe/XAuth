<?php

declare(strict_types=1);

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\event\PlayerLoginEvent;
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

    public function onPlayerPreLogin(PlayerPreLoginEvent $event): void {
        $bruteforceConfig = (array)$this->plugin->getConfig()->get('bruteforce_protection');
        if (! (bool)($bruteforceConfig['kick_at_pre_login'] ?? true)) {
            return;
        }

        $name = $event->getPlayerInfo()->getUsername();

        if ($this->plugin->getDataProvider()->isPlayerLocked($name)) {
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["account_locked_by_admin"] ?? "");
            $event->setKickReason(PlayerPreLoginEvent::KICK_REASON_PLUGIN, $message);
            return;
        }

        $enabled = (bool)($bruteforceConfig['enabled'] ?? false);
        $maxAttempts = (int)($bruteforceConfig['max_attempts'] ?? 0);
        $blockTimeMinutes = (int)($bruteforceConfig['block_time_minutes'] ?? 10);

        if ($enabled && $this->plugin->getDataProvider()->getBlockedUntil($name) > time()) {
            $remainingMinutes = (int)ceil(($this->plugin->getDataProvider()->getBlockedUntil($name) - time()) / 60);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_attempts_exceeded"] ?? "");
            $message = str_replace('{minutes}', (string)$remainingMinutes, $message);
            $event->setKickReason(PlayerPreLoginEvent::KICK_REASON_PLUGIN, $message);
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getAuthManager()->isPlayerAuthenticated($player)) {
            return;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($player);

        if ($playerData !== null) {
            $autoLoginEnabled = (bool)($this->plugin->getConfig()->getNested("auto-login.enabled") ?? false);

            if ($autoLoginEnabled) {
                $currentIp = $player->getNetworkSession()->getIp();
                $foundValidAutoLogin = false;

                // Try persistent sessions first
                $playerSessions = $this->plugin->getDataProvider()->getSessionsByPlayer($player->getName());
                foreach ($playerSessions as $sessionId => $sessionData) {
                    if (($sessionData['ip_address'] ?? '') === $currentIp && ($sessionData['expiration_time'] ?? 0) > time()) {
                        $this->plugin->getAuthManager()->authenticatePlayer($player);
                        $this->plugin->getDataProvider()->updateSessionLastActivity($sessionId);

                        $refreshSession = (bool)($this->plugin->getConfig()->getNested('auto-login.refresh_session_on_login') ?? true);
                        if ($refreshSession) {
                            $newLifetime = (int)($this->plugin->getConfig()->getNested('auto-login.lifetime_seconds') ?? 2592000);
                            $this->plugin->getDataProvider()->refreshSession($sessionId, $newLifetime);
                        }

                        $loginEvent = new PlayerLoginEvent($player, true);
                        $loginEvent->call();

                        if ($loginEvent->isAuthenticationDelayed() || $loginEvent->isCancelled()) {
                            $this->plugin->getAuthManager()->deauthenticatePlayer($player);
                            return;
                        }

                        if ($this->plugin->getDataProvider()->mustChangePassword($player->getName())) {
                            $this->plugin->startForcePasswordChange($player);
                            return;
                        }

                        $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_success"] ?? "");
                        $player->sendMessage($message);
                        $this->plugin->sendTitleMessage($player, "login_success");
                        $this->plugin->clearTitleTask($player);
                        $foundValidAutoLogin = true;
                        break;
                    }
                }

                if ($foundValidAutoLogin) {
                    return;
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
        $player = $event->getPlayer();
        $this->plugin->cancelKickTask($player);
        if($this->plugin->getAuthManager()->isPlayerAuthenticated($player)){
            $this->plugin->getAuthManager()->deauthenticatePlayer($player);
        }
        $this->plugin->clearTitleTask($player);
    }
}
