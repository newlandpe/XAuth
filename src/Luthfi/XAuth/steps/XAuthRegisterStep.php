<?php

declare(strict_types=1);

namespace Luthfi\XAuth\steps;

use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;

class XAuthRegisterStep implements AuthenticationStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'xauth_register';
    }

    public function start(Player $player): void {
        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            $this->skip($player); // Player is already authenticated, so skip this step
            return;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData === null) {
            // Player is not registered, prompt for registration
            $this->plugin->getPlayerStateService()->protectPlayer($player);
            $this->plugin->scheduleKickTask($player);
            $formsEnabled = (bool)($this->plugin->getConfig()->getNested("forms.enabled") ?? true);
            $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
            $player->sendMessage($message);
            if ($formsEnabled) {
                $this->plugin->getFormManager()->sendRegisterForm($player);
            } else {
                $this->plugin->sendTitleMessage($player, "register_prompt");
            }
        } else {
            // Player is already registered, so this step is complete.
            $this->skip($player); // Player is already registered, so skip register step
        }
    }

    public function complete(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $player->sendMessage((string)($messages["register_success"] ?? "§aYou have successfully registered!"));
        $this->plugin->sendTitleMessage($player, "register_success");
        $this->plugin->scheduleClearTitleTask($player, 2 * 20);
        (new PlayerRegisterEvent($player))->call();
    }

    public function skip(Player $player): void {
        // This step is skipped if the player is already registered or authenticated.
        $this->plugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
    }
}
