import os
import sys
import time
import uuid
import logging

from flask import Flask, request, jsonify

app = Flask(__name__)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    stream=sys.stdout,
)
log = logging.getLogger("mock-scan-service")

EICAR_STRING = (
    b"X5O!P%@AP[4\\PZX54(P^)7CC)7}$"
    b"EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*"
)

SCAN_DELAY_SECONDS = float(os.environ.get("SCAN_DELAY_SECONDS", "0"))
PORT = int(os.environ.get("PORT", "8081"))


def classify(filename: str, content: bytes) -> str:
    lower_name = filename.lower()

    if EICAR_STRING in content:
        return "infected"
    if "eicar" in lower_name:
        return "infected"
    if "encrypted" in lower_name:
        return "encrypted"
    if "error" in lower_name:
        return "error"
    return "clean"


def make_engines(status: str):
    verdict = "infected" if status == "infected" else "clean"
    return [
        {"engine": "mock-engine-1", "verdict": verdict},
        {"engine": "mock-engine-2", "verdict": verdict},
    ]


@app.route("/api/scan", methods=["POST"])
def scan():
    if SCAN_DELAY_SECONDS > 0:
        time.sleep(SCAN_DELAY_SECONDS)

    if "file" not in request.files:
        return jsonify({"status": "error", "message": "missing 'file' field"}), 400

    uploaded = request.files["file"]
    filename = uploaded.filename or ""
    content = uploaded.read()
    size = len(content)

    status = classify(filename, content)
    scan_id = str(uuid.uuid4())

    log.info("scan request filename=%r size=%d verdict=%s", filename, size, status)

    if status == "error":
        return (
            jsonify(
                {
                    "scan_id": scan_id,
                    "status": "error",
                    "engines": [],
                }
            ),
            500,
        )

    response = {
        "scan_id": scan_id,
        "status": status,
        "engines": make_engines(status),
    }
    return jsonify(response), 200


@app.route("/healthz", methods=["GET"])
def healthz():
    return jsonify({"status": "ok"}), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=PORT)
