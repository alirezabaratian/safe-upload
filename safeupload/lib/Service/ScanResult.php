<?php

declare(strict_types=1);

namespace OCA\SafeUpload\Service;

class ScanResult {
    public const STATUS_CLEAN = 'clean';
    public const STATUS_INFECTED = 'infected';
    public const STATUS_ENCRYPTED = 'encrypted';
    public const STATUS_ERROR = 'error';

    public function __construct(
        private string $status,
        private ?string $scanId = null,
        private array $engines = [],
        private ?string $errorReason = null,
    ) {
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getScanId(): ?string {
        return $this->scanId;
    }

    public function getEngines(): array {
        return $this->engines;
    }

    /**
     * Internal-only detail for logging (e.g. "connection refused", "timeout").
     * Never shown to end users.
     */
    public function getErrorReason(): ?string {
        return $this->errorReason;
    }

    public function isClean(): bool {
        return $this->status === self::STATUS_CLEAN;
    }
}
