<?php

declare(strict_types=1);

namespace OCA\SafeUpload\Service;

use OCP\Files\File;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ScanService {
    private const APP_ID = 'safeupload';

    private const DEFAULT_API_URL = 'http://localhost:8081/api/scan';
    private const DEFAULT_TIMEOUT_SECONDS = 30;
    private const DEFAULT_FAIL_MODE = 'closed';
    private const DEFAULT_MAX_SCAN_SIZE_MB = 200;
    private const DEFAULT_OVERSIZED_ACTION = 'block';
    private const DEFAULT_API_KEY = '';

    public function __construct(
        private IClientService $clientService,
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function getApiUrl(): string {
        return $this->config->getAppValue(self::APP_ID, 'api_url', self::DEFAULT_API_URL);
    }

    /**
     * Bearer token sent to the scan API. Stored via IConfig like the other
     * settings; never included in logs or returned to end users.
     */
    public function getApiKey(): string {
        return $this->config->getAppValue(self::APP_ID, 'api_key', self::DEFAULT_API_KEY);
    }

    public function getTimeoutSeconds(): int {
        return (int)$this->config->getAppValue(self::APP_ID, 'timeout_seconds', (string)self::DEFAULT_TIMEOUT_SECONDS);
    }

    public function getFailMode(): string {
        $mode = $this->config->getAppValue(self::APP_ID, 'fail_mode', self::DEFAULT_FAIL_MODE);
        return $mode === 'open' ? 'open' : 'closed';
    }

    public function getMaxScanSizeMb(): int {
        return (int)$this->config->getAppValue(self::APP_ID, 'max_scan_size_mb', (string)self::DEFAULT_MAX_SCAN_SIZE_MB);
    }

    /**
     * What to do with a file that exceeds max_scan_size_mb, since it can't be
     * verified: 'block' (default, safest) or 'allow' (skip scanning and let
     * the write through).
     */
    public function getOversizedAction(): string {
        $action = $this->config->getAppValue(self::APP_ID, 'oversized_action', self::DEFAULT_OVERSIZED_ACTION);
        return $action === 'allow' ? 'allow' : 'block';
    }

    /**
     * Sends the given file's content to the scan API and returns the verdict.
     *
     * Never throws for network/HTTP failures — those are represented as a
     * ScanResult with status = 'error' so the caller can apply fail-mode
     * policy uniformly. The detailed failure reason is logged here and kept
     * out of the value returned to end users.
     */
    public function scan(File $file): ScanResult {
        $apiUrl = $this->getApiUrl();
        $timeout = $this->getTimeoutSeconds();
        $filename = $file->getName();
        $size = $file->getSize();

        try {
            $stream = $file->fopen('r');
            if ($stream === false) {
                throw new \RuntimeException('could not open file stream for scanning');
            }

            $options = [
                'timeout' => $timeout,
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $stream,
                        'filename' => $filename,
                    ],
                ],
            ];

            $apiKey = $this->getApiKey();
            if ($apiKey !== '') {
                $options['headers'] = [
                    'Authorization' => 'Bearer ' . $apiKey,
                ];
            }

            $client = $this->clientService->newClient();
            $response = $client->post($apiUrl, $options);

            $body = json_decode($response->getBody(), true);
            if (!is_array($body) || !isset($body['status'])) {
                throw new \RuntimeException('scan API returned an unexpected response body');
            }

            $result = new ScanResult(
                (string)$body['status'],
                $body['scan_id'] ?? null,
                $body['engines'] ?? [],
            );

            $this->logDecision($filename, $size, $result);
            return $result;
        } catch (\Throwable $e) {
            // The scan API itself returns HTTP 500 with a JSON error body for
            // simulated "error" verdicts; Nextcloud's HTTP client throws for
            // non-2xx responses, so we still try to recover the JSON body here.
            $decoded = $this->tryDecodeErrorResponse($e);
            $status = $decoded['status'] ?? ScanResult::STATUS_ERROR;

            $result = new ScanResult(
                (string)$status,
                $decoded['scan_id'] ?? null,
                $decoded['engines'] ?? [],
                $e->getMessage(),
            );

            $this->logDecision($filename, $size, $result);
            return $result;
        }
    }

    private function tryDecodeErrorResponse(\Throwable $e): ?array {
        if (!method_exists($e, 'getResponse')) {
            return null;
        }
        $response = $e->getResponse();
        if ($response === null) {
            return null;
        }
        $body = json_decode((string)$response->getBody(), true);
        return is_array($body) ? $body : null;
    }

    private function logDecision(string $filename, int $size, ScanResult $result): void {
        $context = [
            'app' => self::APP_ID,
            'filename' => $filename,
            'size' => $size,
            'scan_id' => $result->getScanId(),
            'verdict' => $result->getStatus(),
        ];

        if ($result->isClean()) {
            $this->logger->info('Scan decision: clean', $context);
            return;
        }

        if ($result->getStatus() === ScanResult::STATUS_ERROR && $result->getErrorReason() !== null) {
            // Distinguish "scan backend unreachable/errored" from a detected
            // threat in the logs, even though the user-facing message is the
            // same generic wording for both.
            $context['reason'] = $result->getErrorReason();
        }

        $this->logger->warning('Scan decision: blocked', $context);
    }
}
