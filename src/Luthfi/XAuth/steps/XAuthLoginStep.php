<?php

declare(strict_types=1);

namespace Luthfi\XAuth\steps;

use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;

class XAuthLoginStep implements AuthenticationStep, FinalizableStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'xauth_login';
    }

    public function start(Player $player): void {
        if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
            $this->complete($player); // Player is already authenticated, so complete this step
            return;
        }

        $playerData = $this->plugin->getDataProvider()->getPlayer($player);
        if ($playerData !== null) {
            // Player is registered, prompt for login
            $this->plugin->getPlayerStateService()->protectPlayer($player);
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
            // Player is not registered, skip to the next step (register)
            $this->skip($player); 
        }
    }

    public function complete(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
    }

    public function skip(Player $player): void {
        // This step is skipped if the player is not registered (needs to register instead)
        // or if they are already authenticated.
        $this->plugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
    }

    public function onFlowComplete(Player $player, AuthenticationContext $context): void {
        if ($context->wasStepCompleted($this->getId())) {
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            $player->sendMessage((string)($messages["login_success"] ?? ""));
            $this->plugin->sendTitleMessage($player, "login_success");
            $this->plugin->scheduleClearTitleTask($player, 2 * 20);
        }
    }
}
