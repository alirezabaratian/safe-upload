# Safe Upload

A Nextcloud app that scans files on upload (create and update) against an
external scan API and blocks the write if the file is not clean.

## How it works

`ScanUploadListener` listens for `OCP\Files\Events\Node\BeforeNodeWrittenEvent`,
which fires before a node's content is persisted to storage. For any file
(not directories) within the configured size limit, it sends the content to
the configured scan API via `ScanService` and:

- **Allows** the write if the API returns `status: "clean"`
- **Blocks** the write (throws `GenericFileException`, shown to the user as
  an upload error) if the API returns `infected`, `encrypted`, or `error`

By default the app **fails closed**: if the scan API is unreachable, times
out, or errors, the upload is blocked. This can be switched to fail-open in
the admin settings.

> This app targets Nextcloud 27–30. `BeforeNodeWrittenEvent` and its
> `getNode()` signature should be re-verified against
> `lib/public/Files/Events/Node/BeforeNodeWrittenEvent.php` in the server
> version you deploy to — event class locations and constructors have
> shifted across major Nextcloud versions.

## Install / enable

1. Copy (or symlink) this `safeupload/` directory into your Nextcloud
   server's `apps/` (or `apps-extra/`, depending on your setup) directory,
   so the path is `apps/safeupload/appinfo/info.xml`.
2. Enable the app:

   ```bash
   php occ app:enable safeupload
   ```

3. Configure the scan API URL, either via **Admin Settings → Safe Upload**,
   or via `occ`:

   ```bash
   php occ config:app:set safeupload api_url --value="http://localhost:8081/api/scan"
   php occ config:app:set safeupload api_key --value="your-scan-api-token"
   php occ config:app:set safeupload timeout_seconds --value="30"
   php occ config:app:set safeupload fail_mode --value="closed"
   php occ config:app:set safeupload max_scan_size_mb --value="200"
   php occ config:app:set safeupload oversized_action --value="block"
   ```

## Settings

| Setting | Config key | Default | Notes |
|---|---|---|---|
| Scan API URL | `api_url` | `http://localhost:8081/api/scan` | Point at the mock service in dev, the real scan API in prod — no code change required. |
| Scan API key | `api_key` | *(empty)* | Sent as `Authorization: Bearer <key>`. Leave blank for scan APIs that don't require auth (e.g. the mock service). |
| Timeout (seconds) | `timeout_seconds` | `30` | HTTP timeout for the scan request. |
| Fail mode | `fail_mode` | `closed` | `closed` blocks uploads if the scan API is unreachable/errors; `open` allows them. |
| Max file size to scan (MB) | `max_scan_size_mb` | `200` | Files larger than this are not scanned. |
| Oversized action | `oversized_action` | `block` | What happens to files above the max size: `block` (safest) or `allow` (skip scan). |

## Development

This app has no Composer dependencies beyond PSR-4 autoloading (`OCA\SafeUpload\` → `lib/`).
Pair it with `../mock-scan-service` for local testing — see that directory's README
for filenames that trigger each verdict.
