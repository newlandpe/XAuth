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

namespace Luthfi\XAuth\steps;

use Luthfi\XAuth\event\PlayerAuthenticateEvent;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

class XAuthLoginStep implements AuthenticationStep, FinalizableStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'xauth_login';
    }

    public function start(Player $player): void {
        Await::f2c(function () use ($player) {
            if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                $this->skip($player);
                return;
            }

            $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
            if ($playerData !== null) {
                $this->plugin->getPlayerStateService()->protectPlayer($player);
                $this->plugin->scheduleKickTask($player);
                $formsEnabled = $this->plugin->getConfig()->getNested("forms.enabled", true);
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->plugin->getFormManager()->sendLoginForm($player);
                } else {
                    $this->plugin->getTitleManager()->sendTitle($player, "login_prompt", null, true);
                }
            } else {
                $this->skip($player); 
            }
        });
    }

    public function complete(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
    }

    public function skip(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
    }

    public function onFlowComplete(Player $player, AuthenticationContext $context): void {
        if ($context->wasStepCompleted($this->getId())) {
            $messages = (array)$this->plugin->getCustomMessages()->get("messages");
            $player->sendMessage((string)($messages["login_success"] ?? ""));
            $this->plugin->getTitleManager()->sendTitle($player, "login_success", 2 * 20);
        }
    }
}
