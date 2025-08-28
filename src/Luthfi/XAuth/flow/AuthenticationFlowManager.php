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

<?php

declare(strict_types=1);

class AuthenticationFlowManager {

    private Main $plugin;

    /** @var array<string, AuthenticationStep> */
    private array $availableAuthenticationSteps = [];

    /** @var array<string, int> */
    private array $playerAuthenticationFlow = []; // playerName (lowercase) => currentStepIndex

    /** @var non-empty-string[] */
    private array $orderedAuthenticationSteps = [];

    /** @var array<string, AuthenticationContext> */
    private array $playerContexts = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadFlowFromConfig();
    }

    private function loadFlowFromConfig(): void {
        $flowOrder = $this->plugin->getConfig()->get("authentication-flow-order", []);
        if (!is_array($flowOrder) || empty($flowOrder)) {
            $this->plugin->getLogger()->warning("No authentication flow order defined in config.yml. Using default XAuth login/register flow.");
            return;
        }
        
        $this->orderedAuthenticationSteps = array_values(array_filter($flowOrder, 'is_string'));

        if (!in_array("xauth_login", $this->orderedAuthenticationSteps) && !in_array("xauth_register", $this->orderedAuthenticationSteps)) {
            $this->plugin->getLogger()->warning("Neither 'xauth_login' nor 'xauth_register' are included in 'authentication-flow-order' in config.yml. Players may not be able to log in or register.");
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
        $playerName = strtolower($player->getName());
        $this->plugin->getLogger()->debug("XAuth: Starting authentication step chain for player {$player->getName()}.");

        $this->playerContexts[$playerName] = new AuthenticationContext();
        $this->plugin->getPlayerStateService()->protectPlayer($player);

        if (empty($this->orderedAuthenticationSteps)) {
            $this->executeDefaultXAuthFlow($player);
            return;
        }

        $startIndex = 0;
        if ($startStepId !== null) {
            $searchResult = array_search($startStepId, $this->orderedAuthenticationSteps, true);
            if ($searchResult === false) {
                $this->plugin->getLogger()->error("Attempted to start authentication from unknown step '{$startStepId}' for player '{$player->getName()}'. Starting from the first configured step.");
            } else {
                $startIndex = $searchResult;
            }
        }

        $this->findAndExecuteNextStep($player, $startIndex);
    }
    
    private function executeDefaultXAuthFlow(Player $player): void {
        Await::f2c(function() use ($player) {
            $this->plugin->getLogger()->debug("No authentication flow order defined. Player '{$player->getName()}' will proceed with default XAuth flow.");
            $this->plugin->scheduleKickTask($player);

            $playerData = yield from $this->plugin->getDataProvider()->getPlayer($player);
            $formsEnabled = $this->plugin->getConfig()->getNested("forms.enabled", true);
            $messages = (array)($this->plugin->getCustomMessages()->get("messages") ?? []);

            if ($playerData !== null) {
                $message = (string)($messages["login_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->plugin->getFormManager()->sendLoginForm($player);
                } else {
                    $this->plugin->sendTitleMessage($player, "login_prompt");
                }
            } else {
                $message = (string)($messages["register_prompt"] ?? "");
                $player->sendMessage($message);
                if ($formsEnabled) {
                    $this->plugin->getFormManager()->sendRegisterForm($player);
                } else {
                    $this->plugin->sendTitleMessage($player, "register_prompt");
                }
            }
        });
    }

    /**
     * Marks an authentication step as completed for a player and advances to the next step.
     *
     * @param Player $player
     * @param string $completedStepId The ID of the step that was just completed.
     */
    public function completeStep(Player $player, string $completedStepId): void {
        $this->processStepTransition($player, $completedStepId, 'completed');
    }

    /**
     * Marks an authentication step as skipped for a player and advances to the next step.
     *
     * @param Player $player
     * @param string $skippedStepId The ID of the step that was just skipped.
     */
    public function skipStep(Player $player, string $skippedStepId): void {
        $this->processStepTransition($player, $skippedStepId, 'skipped');
    }

    private function processStepTransition(Player $player, string $stepId, string $status): void {
        $this->recordStepStatus($player, $stepId, $status);

        $playerName = strtolower($player->getName());
        if (!isset($this->playerAuthenticationFlow[$playerName])) {
            $this->plugin->getLogger()->warning("Player '{$player->getName()}' finished step '{$stepId}' but is not in an active authentication flow.");
            return;
        }
        
        $currentStepIndex = $this->playerAuthenticationFlow[$playerName];
        $this->findAndExecuteNextStep($player, $currentStepIndex + 1);
    }

    private function recordStepStatus(Player $player, string $stepId, string $status): void {
        $context = $this->getContextForPlayer($player);
        if ($context !== null) {
            $context->setStepStatus($stepId, $status);
            $this->plugin->getLogger()->debug("Recorded step '{$stepId}' as '{$status}' for player '{$player->getName()}'.");
        }
    }

    private function findAndExecuteNextStep(Player $player, int $startIndex): void {
        $playerName = strtolower($player->getName());

        for ($i = $startIndex; $i < count($this->orderedAuthenticationSteps); $i++) {
            $nextStepId = $this->orderedAuthenticationSteps[$i];
            
            if (isset($this->availableAuthenticationSteps[$nextStepId])) {
                $this->playerAuthenticationFlow[$playerName] = $i;
                $this->plugin->getLogger()->debug("Advancing player '{$player->getName()}' to authentication step '{$nextStepId}'.");
                $this->availableAuthenticationSteps[$nextStepId]->start($player);
                return;
            }
            
            $this->plugin->getLogger()->debug("Configured authentication step '{$nextStepId}' not registered by any plugin. Skipping for player '{$player->getName()}'.");
        }

        $this->plugin->getLogger()->debug("All authentication steps completed for player '{$player->getName()}'.");
        $this->finalizeFlow($player);
    }

    private function finalizeFlow(Player $player): void {
        $playerName = strtolower($player->getName());
        $context = $this->getContextForPlayer($player);

        if ($context === null) {
            $this->plugin->getLogger()->error("Cannot finalize flow for player '{$player->getName()}': No authentication context found.");
            // Potentially kick the player here to prevent them from getting stuck
            $player->kick("An internal authentication error occurred.");
            return;
        }

        $loginType = $context->getLoginType();
        $authEvent = new PlayerPreAuthenticateEvent($player, $loginType);
        $authEvent->call();

        if ($authEvent->isCancelled()) {
            $this->plugin->getPlayerStateService()->restorePlayerState($player);
            $kickMessage = $authEvent->getKickMessage() ?? "Authentication cancelled by another plugin.";
            $player->kick($kickMessage);
            $this->cleanUpPlayerState($playerName);
            return;
        }

        $this->plugin->getAuthenticationService()->finalizeAuthentication($player, $context);

        foreach ($this->availableAuthenticationSteps as $step) {
            if ($step instanceof FinalizableStep) {
                $step->onFlowComplete($player, $context);
            }
        }
        
        $this->cleanUpPlayerState($playerName);
    }

    private function cleanUpPlayerState(string $lowercasePlayerName): void {
        unset($this->playerAuthenticationFlow[$lowercasePlayerName], $this->playerContexts[$lowercasePlayerName]);
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
        if ($context === null) {
            return null;
        }
        return $context->getStepStatus($stepId);
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

    /**
     * @return non-empty-string[]
     */
    public function getOrderedAuthenticationSteps(): array {
        return $this->orderedAuthenticationSteps;
    }

    public function getContextForPlayer(Player $player): ?AuthenticationContext {
        return $this->playerContexts[strtolower($player->getName())] ?? null;
    }
}
