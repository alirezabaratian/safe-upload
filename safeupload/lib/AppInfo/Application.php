<?php

declare(strict_types=1);

namespace OCA\SafeUpload\AppInfo;

use OCA\SafeUpload\Listener\ScanUploadListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'safeupload';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(BeforeNodeWrittenEvent::class, ScanUploadListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
