"""SIPRESTI ML API — Flask + XGBoost.

Dipanggil server-to-server oleh aplikasi Laravel di Hostinger.
Browser tidak pernah memanggil API ini secara langsung.

Endpoint:
    GET  /api/health
    GET  /api/v1/model/info
    POST /api/v1/predict
"""

from __future__ import annotations

import hmac
import logging
import os
import sys
from pathlib import Path
from typing import Any

from flask import Flask, jsonify, request

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from ml.predictor import ModelNotReadyError, get_predictor  # noqa: E402
from ml.validator import ValidationError, validate_payload  # noqa: E402

SERVICE_NAME = "SIPRESTI ML API"
MAX_CONTENT_LENGTH = 16 * 1024  # payload prediksi sangat kecil

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)
logger = logging.getLogger("sipresti.ml")

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = MAX_CONTENT_LENGTH
app.config["JSON_SORT_KEYS"] = False

# Model dimuat satu kali saat modul diinisialisasi.
predictor = get_predictor()
if predictor.is_ready:
    logger.info("Model dimuat: versi=%s", predictor.model_version)
else:
    logger.warning("Model belum siap: %s", predictor.load_error)


# --------------------------------------------------------------------- utils
def error_response(code: str, message: str, status: int, request_id: str | None = None):
    payload: dict[str, Any] = {
        "success": False,
        "request_id": request_id,
        "error": {"code": code, "message": message},
    }
    return jsonify(payload), status


def check_api_key() -> tuple[bool, str | None]:
    """Verifikasi X-API-Key dengan perbandingan constant-time."""
    expected = os.environ.get("ML_API_KEY", "")
    if not expected:
        return False, "SERVER_MISCONFIGURED"

    provided = request.headers.get("X-API-Key", "")
    if not provided:
        return False, "UNAUTHORIZED"

    if not hmac.compare_digest(provided.encode("utf-8"), expected.encode("utf-8")):
        return False, "UNAUTHORIZED"

    return True, None


# ------------------------------------------------------------------ handlers
@app.get("/api/health")
def health():
    ready = predictor.is_ready
    body = {
        "success": True,
        "service": SERVICE_NAME,
        "model_loaded": ready,
        "model_version": predictor.model_version,
    }
    return jsonify(body), 200 if ready else 503


@app.get("/api/v1/model/info")
def model_info():
    ok, reason = check_api_key()
    if not ok:
        if reason == "SERVER_MISCONFIGURED":
            return error_response("SERVER_MISCONFIGURED", "ML_API_KEY belum dikonfigurasi.", 503)
        return error_response("UNAUTHORIZED", "API key tidak valid.", 401)

    if not predictor.is_ready:
        return error_response("MODEL_NOT_READY", "Model XGBoost belum tersedia.", 503)

    return jsonify({"success": True, "data": predictor.info()}), 200


@app.post("/api/v1/predict")
def predict():
    ok, reason = check_api_key()
    if not ok:
        if reason == "SERVER_MISCONFIGURED":
            logger.error("ML_API_KEY belum diset pada environment.")
            return error_response("SERVER_MISCONFIGURED", "ML_API_KEY belum dikonfigurasi.", 503)
        logger.warning("Permintaan ditolak: API key tidak valid.")
        return error_response("UNAUTHORIZED", "API key tidak valid.", 401)

    payload = request.get_json(silent=True)
    if payload is None:
        return error_response("BAD_REQUEST", "Body request harus berupa JSON yang valid.", 400)

    request_id = payload.get("request_id") if isinstance(payload, dict) else None

    try:
        cleaned = validate_payload(payload, predictor.validation_ranges)
    except ValidationError as exc:
        logger.info("Validasi gagal (request_id=%s): %s", request_id, exc.message)
        return error_response("VALIDATION_ERROR", exc.message, 422, request_id)

    if not predictor.is_ready:
        logger.warning("Prediksi ditolak, model belum siap (request_id=%s)", cleaned["request_id"])
        return error_response(
            "MODEL_NOT_READY", "Model XGBoost belum tersedia.", 503, cleaned["request_id"]
        )

    features = {k: v for k, v in cleaned.items() if k != "request_id"}

    try:
        result = predictor.predict(features)
    except ModelNotReadyError as exc:
        return error_response("MODEL_NOT_READY", str(exc), 503, cleaned["request_id"])
    except ValueError as exc:
        return error_response("VALIDATION_ERROR", str(exc), 422, cleaned["request_id"])
    except Exception:  # noqa: BLE001
        logger.exception("Prediksi gagal (request_id=%s)", cleaned["request_id"])
        return error_response(
            "INTERNAL_ERROR", "Terjadi kesalahan internal saat memproses prediksi.", 500,
            cleaned["request_id"],
        )

    logger.info(
        "Prediksi sukses request_id=%s class=%s model=%s",
        cleaned["request_id"], result["prediction_class"], result["model_version"],
    )

    return jsonify({
        "success": True,
        "request_id": cleaned["request_id"],
        "data": {
            "prediction_class": result["prediction_class"],
            "prediction_label": result["prediction_label"],
            "probability": result["probability"],
            "model_version": result["model_version"],
        },
    }), 200


# -------------------------------------------------------------- error pages
@app.errorhandler(404)
def not_found(_):
    return error_response("NOT_FOUND", "Endpoint tidak ditemukan.", 404)


@app.errorhandler(405)
def method_not_allowed(_):
    return error_response("METHOD_NOT_ALLOWED", "Metode HTTP tidak diizinkan.", 405)


@app.errorhandler(413)
def payload_too_large(_):
    return error_response("PAYLOAD_TOO_LARGE", "Ukuran request melebihi batas.", 413)


@app.errorhandler(500)
def internal_error(_):
    return error_response("INTERNAL_ERROR", "Terjadi kesalahan internal.", 500)


if __name__ == "__main__":
    app.run(
        host=os.environ.get("HOST", "127.0.0.1"),
        port=int(os.environ.get("PORT", "5000")),
        debug=os.environ.get("APP_ENV") == "local",
    )
