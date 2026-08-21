# Mock Scan Service

A minimal Flask app that mimics the real multi-AV scan API contract, so the
Safe Upload Nextcloud app can be developed and tested without the real backend.

## Run locally

```bash
pip install -r requirements.txt
python app.py
```

Runs on `0.0.0.0:8081` by default. Override with the `PORT` env var.

Simulate scan latency (to test the Nextcloud app's timeout handling) with:

```bash
SCAN_DELAY_SECONDS=5 python app.py
```

## Run with Docker

```bash
docker build -t safeupload-mock-scan .
docker run -p 8081:8081 -e SCAN_DELAY_SECONDS=0 safeupload-mock-scan
```

## API contract

```
POST /api/scan
Content-Type: multipart/form-data (field name: "file")

200 response:
{
  "scan_id": "uuid4-string",
  "status": "clean" | "infected" | "encrypted" | "error",
  "engines": [
    {"engine": "mock-engine-1", "verdict": "clean"},
    {"engine": "mock-engine-2", "verdict": "clean"}
  ]
}
```

On `error`, the service returns HTTP 500 with `{"scan_id": ..., "status": "error", "engines": []}`.

## Quick test

```bash
curl -F file=@somefile.txt http://localhost:8081/api/scan
```

## Verdict is driven by filename (case-insensitive substring match)

| Upload this filename       | Verdict returned |
|-----------------------------|-------------------|
| anything containing `eicar` | `infected`        |
| anything containing `encrypted` | `encrypted`    |
| anything containing `error` | `error` (HTTP 500)|
| anything else                | `clean`           |

The real [EICAR test string](https://en.wikipedia.org/wiki/EICAR_test_file) is also
detected by content, regardless of filename — download the standard EICAR test file
and upload it to exercise real content-based detection.

## Example test files

```bash
echo "hello" > clean.txt
echo "hello" > test-infected-eicar.txt      # triggers "infected" by filename
echo "hello" > test-encrypted.zip           # triggers "encrypted" by filename
echo "hello" > test-error.bin               # triggers "error" (HTTP 500)

curl -F file=@clean.txt http://localhost:8081/api/scan
curl -F file=@test-infected-eicar.txt http://localhost:8081/api/scan
curl -F file=@test-encrypted.zip http://localhost:8081/api/scan
curl -F file=@test-error.bin http://localhost:8081/api/scan
```
