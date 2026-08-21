<?php

declare(strict_types=1);

namespace OCA\SafeUpload\Controller;

use OCA\SafeUpload\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    #[AuthorizedAdminSetting(settings: \OCA\SafeUpload\Settings\AdminSettings::class)]
    public function save(
        string $api_url,
        string $api_key,
        int $timeout_seconds,
        string $fail_mode,
        int $max_scan_size_mb,
        string $oversized_action,
    ): DataResponse {
        $fail_mode = $fail_mode === 'open' ? 'open' : 'closed';
        $oversized_action = $oversized_action === 'allow' ? 'allow' : 'block';
        $timeout_seconds = max(1, $timeout_seconds);
        $max_scan_size_mb = max(1, $max_scan_size_mb);

        $this->config->setAppValue(Application::APP_ID, 'api_url', $api_url);
        $this->config->setAppValue(Application::APP_ID, 'api_key', $api_key);
        $this->config->setAppValue(Application::APP_ID, 'timeout_seconds', (string)$timeout_seconds);
        $this->config->setAppValue(Application::APP_ID, 'fail_mode', $fail_mode);
        $this->config->setAppValue(Application::APP_ID, 'max_scan_size_mb', (string)$max_scan_size_mb);
        $this->config->setAppValue(Application::APP_ID, 'oversized_action', $oversized_action);

        return new DataResponse(['status' => 'ok']);
    }
}
