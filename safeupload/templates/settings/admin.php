<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
use OCP\Util;

Util::addScript('safeupload', 'admin-settings');
Util::addStyle('safeupload', 'admin-settings');
?>

<div id="safeupload-admin-settings" class="section">
    <h2><?php p($l->t('Safe Upload')); ?></h2>
    <p class="settings-hint">
        <?php p($l->t('Configure the external scan API used to check uploaded files before they are written to storage.')); ?>
    </p>

    <form id="safeupload-form" data-save-url="<?php p(\OCP\Server::get(\OCP\IURLGenerator::class)->linkToRoute('safeupload.settings.save')); ?>">
        <input type="hidden" id="safeupload-requesttoken" value="<?php p($_['requesttoken']); ?>">

        <div class="safeupload-field">
            <label for="safeupload-api-url"><?php p($l->t('Scan API URL')); ?></label>
            <input type="text" id="safeupload-api-url" name="api_url"
                   value="<?php p($_['api_url']); ?>" style="width: 25em;">
        </div>

        <div class="safeupload-field">
            <label for="safeupload-api-key"><?php p($l->t('Scan API key')); ?></label>
            <input type="password" id="safeupload-api-key" name="api_key"
                   value="<?php p($_['api_key']); ?>" style="width: 25em;"
                   autocomplete="off"
                   placeholder="<?php p($l->t('Sent as an Authorization: Bearer header. Leave blank if not required.')); ?>">
        </div>

        <div class="safeupload-field">
            <label for="safeupload-timeout"><?php p($l->t('Timeout (seconds)')); ?></label>
            <input type="number" id="safeupload-timeout" name="timeout_seconds" min="1"
                   value="<?php p($_['timeout_seconds']); ?>">
        </div>

        <div class="safeupload-field">
            <label for="safeupload-fail-mode"><?php p($l->t('Fail mode')); ?></label>
            <select id="safeupload-fail-mode" name="fail_mode">
                <option value="closed" <?php p($_['fail_mode'] === 'closed' ? 'selected' : ''); ?>>
                    <?php p($l->t('Fail closed (block upload if scan cannot be completed)')); ?>
                </option>
                <option value="open" <?php p($_['fail_mode'] === 'open' ? 'selected' : ''); ?>>
                    <?php p($l->t('Fail open (allow upload if scan cannot be completed)')); ?>
                </option>
            </select>
        </div>

        <div class="safeupload-field">
            <label for="safeupload-max-size"><?php p($l->t('Max file size to scan (MB)')); ?></label>
            <input type="number" id="safeupload-max-size" name="max_scan_size_mb" min="1"
                   value="<?php p($_['max_scan_size_mb']); ?>">
        </div>

        <div class="safeupload-field">
            <label for="safeupload-oversized-action"><?php p($l->t('Files larger than the max size')); ?></label>
            <select id="safeupload-oversized-action" name="oversized_action">
                <option value="block" <?php p($_['oversized_action'] === 'block' ? 'selected' : ''); ?>>
                    <?php p($l->t('Block (cannot be verified)')); ?>
                </option>
                <option value="allow" <?php p($_['oversized_action'] === 'allow' ? 'selected' : ''); ?>>
                    <?php p($l->t('Allow (skip scanning)')); ?>
                </option>
            </select>
        </div>

        <input type="submit" class="button primary" value="<?php p($l->t('Save')); ?>">
        <span id="safeupload-save-status"></span>
    </form>
</div>
