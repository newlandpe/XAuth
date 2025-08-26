<?php

declare(strict_types=1);

namespace Luthfi\XAuth\exception;

class PlayerBlockedException extends XAuthException {

    private int $remainingMinutes;

    public function __construct(int $remainingMinutes, string $message = "", int $code = 0, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->remainingMinutes = $remainingMinutes;
    }

    public function getRemainingMinutes(): int {
        return $this->remainingMinutes;
    }
}
