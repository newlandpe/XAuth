<?php

declare(strict_types=1);

namespace Luthfi\XAuth\steps;

use Luthfi\XAuth\flow\AuthenticationContext;
use pocketmine\player\Player;

interface FinalizableStep {

    /**
     * @param Player $player The player who completed the authentication flow.
     * @param AuthenticationContext $context The context containing information
     *                                     about all completed and skipped steps.
     */
    public function onFlowComplete(Player $player, AuthenticationContext $context): void;
}
