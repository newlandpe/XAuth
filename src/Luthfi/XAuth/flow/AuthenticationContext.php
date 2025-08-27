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

class AuthenticationContext {

    /** @var array<string, string> */
    private array $stepStatuses = []; // [stepId => 'completed' | 'skipped']

    private ?string $loginType = null;

    public function setStepStatus(string $stepId, string $status): void {
        $this->stepStatuses[$stepId] = $status;
    }

    public function wasStepCompleted(string $stepId): bool {
        return ($this->stepStatuses[$stepId] ?? null) === 'completed';
    }

    public function getCompletedSteps(): array {
        return array_keys($this->stepStatuses, 'completed', true);
    }

    public function setLoginType(string $type): void {
        $this->loginType = $type;
    }

    public function getLoginType(): string {
        return $this->loginType ?? \Luthfi\XAuth\event\PlayerPreAuthenticateEvent::LOGIN_TYPE_MANUAL;
    }
}
