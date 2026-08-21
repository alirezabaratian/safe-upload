# Safe Upload — Nextcloud Extension (Phase 2)

## Goal

Build a Nextcloud app called **Safe Upload** that intercepts file uploads (create + update of nodes),
sends the file content to an external scan API, waits synchronously for the verdict, and:

- **Allows** the write if the scan result is `clean`
- **Blocks** the write (aborts upload with an error shown to the user) if the result is
  `infected`, `encrypted`, or `error`

This phase also includes a **mock scan service** in Python so the Nextcloud app can be developed
and tested without the real multi-AV backend. The mock must expose the exact same API contract
as the real scan service so swapping the URL later requires zero code changes.

---

## Part 1 — Mock Scan Service (Python)

A minimal Flask (or FastAPI) app, single file, no external dependencies beyond the framework.

### Endpoint contract

```
POST /api/scan
Content-Type: multipart/form-data (field name: "file")

Response 200:
{
  "scan_id": "uuid4-string",
  "status": "clean" | "infected" | "encrypted" | "error",
  "engines": [
    {"engine": "mock-engine-1", "verdict": "clean"},
    {"engine": "mock-engine-2", "verdict": "clean"}
  ]
}
```

### Mock behavior (deterministic, filename-driven, for repeatable tests)

Trigger the verdict based on the **uploaded filename** (case-insensitive substring match),
so testers can force each code path without crafting real malicious files:

| Filename contains | Verdict returned |
|---|---|
| `eicar` (or actual EICAR test string detected in content) | `infected` |
| `encrypted` | `encrypted` |
| `error` | `error` (also simulate by returning HTTP 500) |
| anything else | `clean` |

Also add a `SCAN_DELAY_SECONDS` env var (default `0`) that sleeps before responding, so the
Nextcloud side's timeout handling can be tested.

### Requirements

- `POST /api/scan` accepts the uploaded file as multipart form data
- Detect the real [EICAR test string](https://en.wikipedia.org/wiki/EICAR_test_file) in file
  content (not just filename) and return `infected` — this lets us test with the actual
  standard test file too
- Return proper HTTP 500 with `{"status": "error"}` body when filename contains `error`
- Log every request (filename, size, verdict) to stdout
- Run on `0.0.0.0:8081` by default, configurable via `PORT` env var
- Include a `requirements.txt` and a one-line `python app.py` run instruction
- Include a `Dockerfile` (optional but nice — Nextcloud is often tested in Docker, so having
  the mock service on the same Docker network simplifies testing)

### Deliverables for Part 1

- `mock-scan-service/app.py`
- `mock-scan-service/requirements.txt`
- `mock-scan-service/Dockerfile` (optional)
- `mock-scan-service/README.md` — how to run it and what test filenames to use

---

## Part 2 — Nextcloud App: `safeupload`

### Integration approach

Use an **event listener** on file write events rather than the Workflow Engine UI-based checks —
it's simpler to reason about for a hard block-on-upload requirement and doesn't require admins
to configure workflow rules manually.

Listen to:
- `OCP\Files\Events\Node\BeforeNodeWrittenEvent` — fires before a node (new file or file update)
  is persisted to storage. Throwing an exception from the listener **aborts the write** and
  surfaces an error to the client.

> Claude Code: verify the exact event class name and method signature against the installed
> Nextcloud server version's PHP source (`lib/private/Files/Node/...` and
> `lib/public/Files/Events/Node/`) before finalizing — event names have shifted across major
> Nextcloud versions (20 vs 27+). Confirm which server version this app targets and adjust
> `info.xml` `<dependencies><nextcloud min-version.../>` accordingly.

### App structure

```
safeupload/
├── appinfo/
│   ├── info.xml
│   └── routes.php              (only if an admin settings page needs endpoints)
├── lib/
│   ├── AppInfo/
│   │   └── Application.php     # registers the event listener via IBootstrap
│   ├── Listener/
│   │   └── ScanUploadListener.php
│   ├── Service/
│   │   └── ScanService.php     # HTTP call to scan API, contract parsing
│   └── Settings/
│       ├── AdminSettings.php   # optional: settings page for API URL/timeout
│       └── AdminSection.php
├── templates/
│   └── settings/admin.php      # optional simple form: scan API URL, timeout, fail-open/closed
├── css/
├── js/
└── README.md
```

### `lib/Service/ScanService.php` — responsibilities

- Accept a `\OCP\Files\File` (or raw stream/content) and send it to the scan API via
  Nextcloud's `IClientService` (`OCP\Http\Client\IClientService`) — **do not** use raw
  `curl`/`file_get_contents`; use the injected HTTP client service so proxy settings,
  logging, and testing mocks all work correctly.
- Read config via `IConfig`:
  - `safeupload.api_url` (default `http://localhost:8081/api/scan`)
  - `safeupload.timeout_seconds` (default `30`)
  - `safeupload.fail_mode` = `closed` (block on timeout/error) or `open` (allow on
    timeout/error) — **default to `closed`** since this is a security control
- Send file as multipart, parse JSON response into a small `ScanResult` value object with
  `status` and `engines`.
- On HTTP timeout, connection error, or non-200 response: return a `ScanResult` with
  `status = 'error'`, and let the listener apply the configured fail-mode.
- Log every scan decision via `OCP\ILogger` / `LoggerInterface` (filename, size, scan_id,
  verdict) at `info` level for clean, `warning` for anything blocked.

### `lib/Listener/ScanUploadListener.php` — responsibilities

- Implements `IEventListener<BeforeNodeWrittenEvent>`
- Skip scanning for:
  - Directories
  - Files above a configurable max size (`safeupload.max_scan_size_mb`, default e.g. `200`) —
    define the behavior for oversized files explicitly (block by default, since we can't verify
    them, but make it configurable)
- Get the node's content stream, pass to `ScanService::scan()`
- If `status === 'clean'` → return normally, write proceeds
- If `status` is `infected` / `encrypted` / `error` → throw
  `\OCP\Files\Storage\NotPermittedException` (or `\OCP\Files\GenericFileException`) with a
  clear message per verdict, e.g.:
  - infected: `"File rejected: malware detected."`
  - encrypted: `"File rejected: encrypted/password-protected files are not permitted."`
  - error: `"File rejected: could not verify file safety (scan error)."`
- Ensure the thrown exception message is safe to show to end users (no internal stack traces,
  no scan engine internals — just the category).

### `appinfo/info.xml` essentials

- `<id>safeupload</id>`
- Category: `security`
- `<dependencies><nextcloud min-version="27" max-version="30"/></dependencies>` (Claude Code:
  set actual target version based on the dev environment)
- No special `<repair-steps>` needed for this phase

### Admin settings page (optional but recommended)

A simple settings form under **Admin Settings → Security** with:
- Scan API URL (text input)
- Timeout in seconds
- Fail mode (dropdown: fail closed / fail open)
- Max file size to scan (MB)

Persist via `IConfig::setAppValue('safeupload', key, value)`. This avoids hardcoding the mock
service URL and makes swapping in the real scan service later a one-field change.

### Deliverables for Part 2

- Full `safeupload/` app skeleton as above, installable via `occ app:enable safeupload`
- Composer autoloading set up correctly (`composer.json` + `vendor/` or PSR-4 without composer
  if the app avoids external deps)
- `README.md` inside the app with install/enable instructions

---

## Testing Checklist (do this at the end, in order)

1. Start the mock scan service (`python app.py`), confirm `curl -F file=@somefile.txt
   http://localhost:8081/api/scan` returns a `clean` verdict.
2. Enable the app: `occ app:enable safeupload`
3. Set the API URL via admin settings (or `occ config:app:set safeupload api_url --value=...`)
4. Upload a normal file via the Nextcloud web UI → should succeed, check logs for `clean`
   decision.
5. Upload a file named `test-infected.txt` → upload should be **rejected** with the infected
   message.
6. Download the real [EICAR test file](https://en.wikipedia.org/wiki/EICAR_test_file) and
   upload it → should be rejected as `infected` (content-based detection).
7. Upload a file named `test-encrypted.zip` → rejected as encrypted.
8. Upload a file named `test-error.bin` → rejected as error (fail-closed default).
9. Stop the mock scan service entirely and attempt an upload → confirm fail-closed behavior
   blocks the upload with a timeout-driven error, and check the Nextcloud log entry is clear
   about *why* it was blocked (scan unreachable vs. detected threat — these should be
   distinguishable in logs even if the user-facing message is generic).
10. Set `SCAN_DELAY_SECONDS=5` on the mock and confirm the app's timeout setting is respected
    (test with timeout set below and above the delay).

---

## Explicitly Out of Scope for This Phase

- Async/quarantine upload flow
- Multi-engine aggregation logic (that lives in the real scan service, already built)
- Real AV engine integration
- Chunked/resumable upload interception nuances (WebDAV chunking, `New-Uploads` API) — flag
  if this becomes relevant, since large-file chunked uploads may fire write events differently
  than the simple single-request case and could need separate handling in a later phase
