<?php

declare(strict_types=1);

namespace OCA\SafeUpload\Listener;

use OCA\SafeUpload\Service\ScanResult;
use OCA\SafeUpload\Service\ScanService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<BeforeNodeWrittenEvent> */
class ScanUploadListener implements IEventListener {
    public function __construct(
        private ScanService $scanService,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof BeforeNodeWrittenEvent) {
            return;
        }

        $node = $event->getNode();

        if (!$node instanceof File) {
            // Directories and other non-file nodes are not scanned.
            return;
        }

        $maxScanSizeBytes = $this->scanService->getMaxScanSizeMb() * 1024 * 1024;
        $size = $node->getSize();

        if ($size > $maxScanSizeBytes) {
            if ($this->scanService->getOversizedAction() === 'allow') {
                $this->logger->info('Skipping scan: file exceeds max scan size, oversized_action=allow', [
                    'app' => 'safeupload',
                    'filename' => $node->getName(),
                    'size' => $size,
                ]);
                return;
            }

            $this->logger->warning('Blocking write: file exceeds max scan size and cannot be verified', [
                'app' => 'safeupload',
                'filename' => $node->getName(),
                'size' => $size,
            ]);
            throw new GenericFileException('File rejected: file is too large to be scanned for safety.');
        }

        $result = $this->scanService->scan($node);

        if ($result->isClean()) {
            return;
        }

        if ($result->getStatus() === ScanResult::STATUS_ERROR && $this->scanService->getFailMode() === 'open') {
            $this->logger->warning('Allowing write despite scan error: fail_mode=open', [
                'app' => 'safeupload',
                'filename' => $node->getName(),
                'reason' => $result->getErrorReason(),
            ]);
            return;
        }

        throw new GenericFileException($this->userMessageFor($result->getStatus()));
    }

    private function userMessageFor(string $status): string {
        return match ($status) {
            ScanResult::STATUS_INFECTED => 'File rejected: malware detected.',
            ScanResult::STATUS_ENCRYPTED => 'File rejected: encrypted/password-protected files are not permitted.',
            default => 'File rejected: could not verify file safety (scan error).',
        };
    }
}
