<?php

declare(strict_types=1);

namespace OCA\SafeUpload\Settings;

use OCA\SafeUpload\Service\ScanService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
    public function __construct(
        private ScanService $scanService,
    ) {
    }

    public function getForm(): TemplateResponse {
        $parameters = [
            'api_url' => $this->scanService->getApiUrl(),
            'api_key' => $this->scanService->getApiKey(),
            'timeout_seconds' => $this->scanService->getTimeoutSeconds(),
            'fail_mode' => $this->scanService->getFailMode(),
            'max_scan_size_mb' => $this->scanService->getMaxScanSizeMb(),
            'oversized_action' => $this->scanService->getOversizedAction(),
        ];

        return new TemplateResponse('safeupload', 'settings/admin', $parameters, '');
    }

    public function getSection(): string {
        return 'safeupload';
    }

    public function getPriority(): int {
        return 10;
    }
}
