<?php

/*
 *
 * __  __    _         _   _
 * \ \/ /   / \  _   _| |_| |__
 *  \  /   / _ \| | | | __| '_ \
 *  /  \  / ___ \ |_| | |_| | | |
 * /_/\_\/_/   \_\__,_|\__|_| |_|
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

namespace Luthfi\XAuth\flow;

use Luthfi\XAuth\event\PlayerPreAuthenticateEvent;
use Luthfi\XAuth\Main;
use Luthfi\XAuth\steps\AuthenticationStep;
use Luthfi\XAuth\steps\FinalizableStep;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

class AuthenticationFlowManager {

    private Main $plugin;

    /** @var array<string, AuthenticationStep> */
    private array $availableAuthenticationSteps = [];

    /** @var array<string, string> */
    private array $playerAuthenticationFlow = []; // playerName => currentStepId (index in ordered steps)

    private array $orderedAuthenticationSteps = [];

    /** @var array<string, AuthenticationContext> */
    private array $playerContexts = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        // Load ordered steps from config here, as Main will delegate this.
        $flowOrder = (array)$this->plugin->getConfig()->get("authentication-flow-order", []);
        if (empty($flowOrder)) {
            $this->plugin->getLogger()->warning("No authentication flow order defined in config.yml. Using default XAuth login/register flow.");
        } else {
            $this->orderedAuthenticationSteps = $flowOrder;
            // Check if the essential auth steps are in the flow, if not, warn the admin
            if (!in_array("xauth_login", $this->orderedAuthenticationSteps) && !in_array("xauth_register", $this->orderedAuthenticationSteps)) {
                $this->plugin->getLogger()->warning("Neither 'xauth_login' nor 'xauth_register' are included in 'authentication-flow-order' in config.yml. Players may not be able to log in or register.");
            }
        }
    }

    /**
     * Registers an authentication step with the flow manager.
     *
     * @param AuthenticationStep $step The authentication step object to register.
     */
    public function registerAuthenticationStep(AuthenticationStep $step): void {
        $stepId = $step->getId();
        if (isset($this->availableAuthenticationSteps[$stepId])) {
            $this->plugin->getLogger()->warning("Authentication step '{$stepId}' is already registered. Overwriting.");
        }
        $this->availableAuthenticationSteps[$stepId] = $step;
        $this->plugin->getLogger()->debug("Authentication step '{$stepId}' registered.");
    }

    /**
     * Starts the authentication flow for a player, or advances to a specific step.
     *
     * @param Player $player
     * @param string|null $startStepId If provided, starts from this step. Otherwise, starts from the beginning.
     */
    public function startAuthenticationFlow(Player $player, ?string $startStepId = null): void {
        $playerName = $player->getName();
        $this->plugin->getLogger()->debug("XAuth: Starting authentication step chain for player {$playerName}.");

        $this->playerContexts[$playerName] = new AuthenticationContext();
        $this->plugin->getPlayerStateService()->protectPlayer($player);

        // If no ordered steps are defined in config, let XAuth handle it normally
        if (empty($this->orderedAuthenticationSteps)) {
            Await::f2c(function() use ($player, $playerName) {
                $this->plugin->getLogger()->debug("No authentication flow order defined. Player '{$playerName}' will proceed with default XAuth flow.");
                // Trigger XAuth's default login/register prompt here if needed
                $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
                $this->plugin->scheduleKickTask($player);
                $formsEnabled = $this->plugin->getConfig()->getNested("forms.enabled", true);
                if ($playerData !== null) {
                    $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["login_prompt"] ?? "");
                    $player->sendMessage($message);
                    if ($formsEnabled) {
                        $this->plugin->getFormManager()->sendLoginForm($player);
                    } else {
                        $this->plugin->sendTitleMessage($player, "login_prompt");
                    }
                } else {
                    $message = (string)(((array)$this->plugin->getCustomMessages()->get("messages"))["register_prompt"] ?? "");
                    $player->sendMessage($message);
                    if ($formsEnabled) {
                        $this->plugin->getFormManager()->sendRegisterForm($player);
                    } else {
                        $this->plugin->sendTitleMessage($player, "register_prompt");
                    }
                }
            });
            return;
        }

        // Determine the starting step index
        $startIndex = 0;
        if ($startStepId !== null) {
            $startIndex = array_search($startStepId, $this->orderedAuthenticationSteps);
            if ($startIndex === false) {
                $this->plugin->getLogger()->error("Attempted to start authentication from unknown step '{$startStepId}' for player '{$playerName}'. Starting from first configured step.");
                $startIndex = 0;
            }
        }

        // Find the first available step from the ordered list
        for ($i = $startIndex; $i < count($this->orderedAuthenticationSteps); $i++) {
            $currentStepId = $this->orderedAuthenticationSteps[$i];
            if (isset($this->availableAuthenticationSteps[$currentStepId])) {
                $this->playerAuthenticationFlow[$playerName] = $i; // Store index
                $this->plugin->getLogger()->debug("XAuth: Launching authentication step '{$currentStepId}' for player {$playerName}.");

                // Call the start method for the registered step object
                $this->availableAuthenticationSteps[$currentStepId]->start($player);
                return;
            } else {
                $this->plugin->getLogger()->debug("Configured authentication step '{$currentStepId}' not registered by any plugin. Skipping for player '{$playerName}'.");
            }
        }

        // If no available steps found in the ordered list
        $this->plugin->getLogger()->warning("No available authentication steps found in the configured flow for player '{$playerName}'. Player will not be authenticated by step manager.");
        // Potentially kick player or allow default XAuth flow if no steps are found
    }

    /**
     * Marks an authentication step as completed for a player and advances to the next step.
     *
     * @param Player $player
     * @param string $completedStepId The ID of the step that was just completed.
     */
    public function completeStep(Player $player, string $completedStepId): void {
        $this->recordStepStatus($player, $completedStepId, 'completed');
        $this->advanceFlow($player, $completedStepId);
    }

    /**
     * Marks an authentication step as skipped for a player and advances to the next step.
     *
     * @param Player $player
     * @param string $skippedStepId The ID of the step that was just skipped.
     */
    public function skipStep(Player $player, string $skippedStepId): void {
        $this->recordStepStatus($player, $skippedStepId, 'skipped');
        $this->advanceFlow($player, $skippedStepId);
    }

    private function recordStepStatus(Player $player, string $stepId, string $status): void {
        $context = $this->getContextForPlayer($player);
        if ($context !== null) {
            $context->setStepStatus($stepId, $status);
            $this->plugin->getLogger()->debug("Recorded step '{$stepId}' as '{$status}' for player '{$player->getName()}'.");
        }
    }

    private function advanceFlow(Player $player, string $currentStepId): void {
        $playerName = $player->getName();

        if (!isset($this->playerAuthenticationFlow[$playerName])) {
            $this->plugin->getLogger()->warning("Player '{$playerName}' completed/skipped step '{$currentStepId}' but is not in an active authentication flow.");
            return;
        }

        $currentStepIndex = array_search($currentStepId, $this->orderedAuthenticationSteps);
        if ($currentStepIndex === false) {
            $this->plugin->getLogger()->error("Completed/skipped step '{$currentStepId}' not found in ordered flow for player '{$playerName}'. Cannot advance flow.");
            return;
        }

        $nextIndex = $currentStepIndex + 1;

        // Find the next available step in the ordered list
        for ($i = $nextIndex; $i < count($this->orderedAuthenticationSteps); $i++) {
            $nextStepId = $this->orderedAuthenticationSteps[$i];
            if (isset($this->availableAuthenticationSteps[$nextStepId])) {
                $this->playerAuthenticationFlow[$playerName] = $i; // Store index
                $this->plugin->getLogger()->debug("Advancing player '{$playerName}' to authentication step '{$nextStepId}'.");

                // Call the start method for the registered step object
                $this->availableAuthenticationSteps[$nextStepId]->start($player);
                return;
            }
            $this->plugin->getLogger()->debug("Configured authentication step '{$nextStepId}' not registered by any plugin. Skipping for player '{$playerName}'.");
        }

        // All steps completed
        $this->plugin->getLogger()->debug("All authentication steps completed for player '{$playerName}'.");
        $this->finalizeFlow($player);
    }

    private function finalizeFlow(Player $player): void {
        $playerName = $player->getName();
        $context = $this->getContextForPlayer($player);

        if ($context === null) {
            $this->plugin->getLogger()->error("Cannot finalize flow for player '{$playerName}': No authentication context found.");
            return;
        }

        $loginType = $context->getLoginType();
        $authEvent = new PlayerPreAuthenticateEvent($player, $loginType);
        $authEvent->call();

        if ($authEvent->isCancelled()) {
            $this->plugin->getPlayerStateService()->restorePlayerState($player);
            $kickMessage = $authEvent->getKickMessage() ?? "Authentication cancelled by another plugin.";
            $player->kick($kickMessage);
            return;
        }

        $this->plugin->getAuthenticationService()->finalizeAuthentication($player, $context);

        foreach ($this->availableAuthenticationSteps as $step) {
            if ($step instanceof FinalizableStep) {
                $step->onFlowComplete($player, $context);
            }
        }

        unset($this->playerAuthenticationFlow[$playerName]);
        unset($this->playerContexts[$playerName]);
    }

    /**
     * Returns the completion status of a specific authentication step for a player.
     *
     * @param Player $player
     * @param string $stepId The ID of the step to check.
     * @return string|null 'completed', 'skipped', or null if the step has not been reached or recorded.
     */
    public function getPlayerAuthenticationStepStatus(Player $player, string $stepId): ?string {
        $context = $this->getContextForPlayer($player);
        if ($context !== null) {
            return $context->wasStepCompleted($stepId) ? 'completed' : 'skipped';
        }
        return null;
    }

    /**
     * @return array<string, AuthenticationStep>
     */
    public function getAuthenticationSteps(): array {
        return $this->availableAuthenticationSteps;
    }

    public function getStep(string $stepId): ?AuthenticationStep {
        return $this->availableAuthenticationSteps[$stepId] ?? null;
    }

    public function getOrderedAuthenticationSteps(): array {
        return $this->orderedAuthenticationSteps;
    }

    public function getContextForPlayer(Player $player): ?AuthenticationContext {
        return $this->playerContexts[strtolower($player->getName())] ?? null;
    }
}
