<?php

declare(strict_types=1);

namespace Luthfi\XAuth\steps;

use Luthfi\XAuth\event\PlayerPreAuthenticateEvent;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;

class AutoLoginStep implements AuthenticationStep, FinalizableStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'auto_login';
    }

    public function start(Player $player): void {
        $playerName = $player->getName();
        $autoLoginConfig = (array)$this->plugin->getConfig()->get("auto-login", []);

        if ((bool)($autoLoginConfig["enabled"] ?? false)) {
            $sessions = $this->plugin->getDataProvider()->getSessionsByPlayer($playerName);
            $ip = $player->getNetworkSession()->getIp();
            $securityLevel = (int)($autoLoginConfig["security_level"] ?? 1);

            foreach ($sessions as $sessionData) {
                if (($sessionData['expiration_time'] ?? 0) <= time()) {
                    continue;
                }

                $ipMatch = ($sessionData['ip_address'] ?? '') === $ip;
                $deviceId = $this->plugin->deviceIds[strtolower($playerName)] ?? null;
                $deviceIdMatch = ($sessionData['device_id'] ?? null) === $deviceId;

                if (($securityLevel === 1 && $ipMatch && $deviceIdMatch) || ($securityLevel === 0 && $ipMatch)) {
                    $this->plugin->getAuthenticationFlowManager()->getContextForPlayer($player)->setLoginType(PlayerPreAuthenticateEvent::LOGIN_TYPE_AUTO);
                    $this->complete($player);
                    return;
                }
            }
        }

        // If auto-login is disabled or fails, just skip to the next step.
        $this->skip($player);
    }

    public function complete(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
    }

    public function skip(Player $player): void {
        // This step is skipped if auto-login is disabled or fails.
        $this->plugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
    }

    public function onFlowComplete(Player $player, AuthenticationContext $context): void {
        if ($context->wasStepCompleted($this->getId())) {
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            $player->sendMessage((string)($messages["auto_login_success"] ?? "§aYou have been automatically logged in."));
            $this->plugin->sendTitleMessage($player, "auto_login_success");
            $this->plugin->scheduleClearTitleTask($player, 2 * 20);
        }
    }
}
