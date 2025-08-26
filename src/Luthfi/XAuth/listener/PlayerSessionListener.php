<?php

declare(strict_types=1);

namespace Luthfi\XAuth\listener;

use Luthfi\XAuth\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\PlayerListPacket;

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

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $authenticationService = $this->plugin->getAuthenticationService();

        if ($authenticationService->isPlayerAuthenticated($player)) {
            return;
        }

        // If authentication steps are registered, take over the flow
        if (!empty($this->plugin->getAuthenticationSteps()) && !empty($this->plugin->getOrderedAuthenticationSteps())) {
            $this->plugin->startAuthenticationStep($player); // Start the managed authentication flow
            return;
        }

        // Fallback to old behavior if no flow is defined in config
        $this->plugin->getPlayerStateService()->protectPlayer($player);
        $playerData = $this->plugin->getDataProvider()->getPlayer($player);
        $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);

        if ($playerData !== null) {
            // Normal login flow
            $this->plugin->scheduleKickTask($player);
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
        $this->plugin->getAuthenticationService()->handleQuit($event->getPlayer());
    }

    public function onPacketSend(DataPacketSendEvent $event): void {
        $playerListConfig = (array)$this->plugin->getConfig()->get('player_list_visibility', []);
        if ((bool)($playerListConfig['hide'] ?? true) === false) {
            return;
        }

        $packets = $event->getPackets();
        $modifiedPackets = [];
        $hasChanges = false;

        foreach ($packets as $packet) {
            if (!$packet instanceof PlayerListPacket) {
                $modifiedPackets[] = $packet;
                continue;
            }

            if ($packet->type !== PlayerListPacket::TYPE_ADD) {
                $modifiedPackets[] = $packet;
                continue;
            }

            $modifiedEntries = [];

            foreach ($packet->entries as $entry) {
                $playerName = $entry->username;
                $player = $this->plugin->getServer()->getPlayerExact($playerName);

                if ($player === null || !$this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                    $hasChanges = true;
                    continue;
                }

                $modifiedEntries[] = $entry;
            }

            if (empty($modifiedEntries)) {
                $hasChanges = true;
                continue;
            }

            if (count($modifiedEntries) !== count($packet->entries)) {
                $packet->entries = $modifiedEntries;
                $hasChanges = true;
            }

            $modifiedPackets[] = $packet;
        }

        if ($hasChanges) {
            $event->setPackets($modifiedPackets);
        }
    }
}
