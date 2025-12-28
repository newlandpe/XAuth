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

use Luthfi\XAuth\event\PlayerRegisterEvent;
use Luthfi\XAuth\flow\AuthenticationContext;
use Luthfi\XAuth\Main;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

class XAuthRegisterStep implements AuthenticationStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'xauth_register';
    }

    public function start(Player $player): void {
        Await::f2c(function () use ($player) {
            if ($this->plugin->getAuthenticationService()->isPlayerAuthenticated($player)) {
                $this->skip($player);
                return;
            }

            $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
            if ($playerData === null) {
                $this->plugin->getPlayerStateService()->protectPlayer($player);
                $this->plugin->scheduleKickTask($player);
                $formsEnabled = $this->plugin->getConfig()->getNested("forms.enabled", true);
                $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->plugin->getFormManager()->sendRegisterForm($player);
                } else {
                    $this->plugin->getTitleManager()->sendTitle($player, "register_prompt", null, true);
                }
            } else {
                $this->skip($player);
            }
        });
    }

    public function complete(Player $player): void {
        $messages = (array)$this->plugin->getCustomMessages()->get("messages");
        $player->sendMessage((string)($messages["register_success"] ?? "§aYou have successfully registered!"));
        $this->plugin->getTitleManager()->sendTitle($player, "register_success", 2 * 20);
        (new PlayerRegisterEvent($player))->call();
    }

    public function skip(Player $player): void {
        $this->plugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
    }
}
