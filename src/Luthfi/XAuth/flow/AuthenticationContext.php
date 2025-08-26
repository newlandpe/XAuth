<?php

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
