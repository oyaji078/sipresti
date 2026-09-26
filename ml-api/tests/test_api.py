"""Pengujian ML API SIPRESTI.

Mencakup keamanan API key, validasi payload, konsistensi preprocessing,
dan kewajaran keluaran model (§AR Master Prompt).

Jalankan:
    ML_API_KEY=kunci-uji .venv/Scripts/python.exe -m pytest tests -q
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import pytest

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

TEST_KEY = "kunci-uji-untuk-pengujian-otomatis"
os.environ["ML_API_KEY"] = TEST_KEY

from api.index import app  # noqa: E402
from ml.predictor import Predictor  # noqa: E402
from ml.preprocessing import PreprocessingError, build_feature_vector, encode_gender  # noqa: E402
from ml.validator import ValidationError, validate_payload  # noqa: E402

MODEL_DIR = BASE_DIR / "models"
MODEL_AVAILABLE = (
    (MODEL_DIR / "xgboost_model.onnx").exists()
    or (MODEL_DIR / "xgboost_model.json").exists()
) and (MODEL_DIR / "metadata.json").exists()
requires_model = pytest.mark.skipif(not MODEL_AVAILABLE, reason="artifact model belum tersedia")

VALID_PAYLOAD = {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "gender": "L",
    "age_months": 30,
    "weight_kg": 11.4,
    "height_cm": 86.5,
}


@pytest.fixture()
def client():
    app.config.update(TESTING=True)
    with app.test_client() as c:
        yield c


def auth_headers() -> dict[str, str]:
    return {"X-API-Key": TEST_KEY, "Content-Type": "application/json"}


# ------------------------------------------------------------------- health
def test_health_tidak_membocorkan_rahasia(client):
    response = client.get("/api/health")
    body = response.get_json()

    assert set(body.keys()) == {"success", "service", "model_loaded", "model_version"}
    assert body["service"] == "SIPRESTI ML API"

    raw = json.dumps(body)
    assert TEST_KEY not in raw
    assert str(BASE_DIR) not in raw


def test_health_tidak_memerlukan_api_key(client):
    assert client.get("/api/health").status_code in (200, 503)


@requires_model
def test_health_melaporkan_model_dimuat(client):
    body = client.get("/api/health").get_json()

    assert body["model_loaded"] is True
    assert body["model_version"]


# --------------------------------------------------------------- keamanan
def test_predict_tanpa_api_key_ditolak(client):
    response = client.post("/api/v1/predict", json=VALID_PAYLOAD)

    assert response.status_code == 401
    assert response.get_json()["error"]["code"] == "UNAUTHORIZED"


def test_predict_dengan_api_key_salah_ditolak(client):
    response = client.post(
        "/api/v1/predict",
        json=VALID_PAYLOAD,
        headers={"X-API-Key": "kunci-yang-salah", "Content-Type": "application/json"},
    )

    assert response.status_code == 401
    assert response.get_json()["success"] is False


def test_model_info_memerlukan_api_key(client):
    assert client.get("/api/v1/model/info").status_code == 401


def test_endpoint_tidak_dikenal_mengembalikan_404_berformat(client):
    body = client.get("/api/v1/tidak-ada").get_json()

    assert body["success"] is False
    assert body["error"]["code"] == "NOT_FOUND"


def test_metode_salah_ditolak(client):
    response = client.get("/api/v1/predict", headers=auth_headers())

    assert response.status_code == 405
    assert response.get_json()["error"]["code"] == "METHOD_NOT_ALLOWED"


# --------------------------------------------------------------- validasi
def test_body_bukan_json_ditolak(client):
    response = client.post("/api/v1/predict", data="bukan json", headers=auth_headers())

    assert response.status_code == 400
    assert response.get_json()["error"]["code"] == "BAD_REQUEST"


@pytest.mark.parametrize("field", ["gender", "age_months", "weight_kg", "height_cm"])
def test_fitur_yang_hilang_ditolak(client, field):
    payload = dict(VALID_PAYLOAD)
    payload.pop(field)

    response = client.post("/api/v1/predict", json=payload, headers=auth_headers())

    assert response.status_code == 422
    assert response.get_json()["error"]["code"] == "VALIDATION_ERROR"
    assert field in response.get_json()["error"]["message"]


def test_request_id_wajib_uuid(client):
    payload = dict(VALID_PAYLOAD, request_id="bukan-uuid")

    response = client.post("/api/v1/predict", json=payload, headers=auth_headers())

    assert response.status_code == 422
    assert "request_id" in response.get_json()["error"]["message"]


def test_request_id_dikembalikan_apa_adanya(client):
    response = client.post("/api/v1/predict", json=VALID_PAYLOAD, headers=auth_headers())

    assert response.get_json()["request_id"] == VALID_PAYLOAD["request_id"]


@pytest.mark.parametrize("value", ["abc", None, float("nan"), float("inf"), True])
def test_angka_tidak_valid_ditolak(client, value):
    payload = dict(VALID_PAYLOAD, weight_kg=value)

    response = client.post("/api/v1/predict", json=payload, headers=auth_headers())

    assert response.status_code == 422


@pytest.mark.parametrize(
    ("field", "value"),
    [("age_months", -1), ("age_months", 200), ("weight_kg", -5), ("height_cm", 0)],
)
def test_nilai_di_luar_batas_teknis_ditolak(client, field, value):
    payload = dict(VALID_PAYLOAD, **{field: value})

    response = client.post("/api/v1/predict", json=payload, headers=auth_headers())

    assert response.status_code == 422


def test_usia_desimal_ditolak(client):
    payload = dict(VALID_PAYLOAD, age_months=24.5)

    response = client.post("/api/v1/predict", json=payload, headers=auth_headers())

    assert response.status_code == 422


def test_angka_dengan_koma_desimal_diterima():
    cleaned = validate_payload(dict(VALID_PAYLOAD, weight_kg="11,4"))

    assert cleaned["weight_kg"] == 11.4


def test_validator_menolak_body_bukan_objek():
    with pytest.raises(ValidationError):
        validate_payload(["bukan", "objek"])


# ----------------------------------------------------------- preprocessing
def test_encode_gender_memakai_mapping_metadata():
    mapping = {"L": 0, "P": 1}

    assert encode_gender("L", mapping) == 0.0
    assert encode_gender("p", mapping) == 1.0
    assert encode_gender(1, mapping) == 1.0


def test_encode_gender_menolak_nilai_tak_dikenal():
    with pytest.raises(PreprocessingError):
        encode_gender("Z", {"L": 0, "P": 1})


def test_encode_gender_tanpa_mapping_menolak_teks():
    with pytest.raises(PreprocessingError):
        encode_gender("L", None)


def test_urutan_fitur_mengikuti_metadata():
    metadata = {
        "feature_order": ["height_cm", "weight_kg", "age_months", "gender"],
        "gender_encoding": {"L": 0, "P": 1},
    }
    payload = {"gender": "P", "age_months": 30, "weight_kg": 11.4, "height_cm": 86.5}

    assert build_feature_vector(payload, metadata) == [86.5, 11.4, 30.0, 1.0]


def test_fitur_kurang_ditolak_preprocessing():
    metadata = {"feature_order": ["gender", "age_months"], "gender_encoding": {"L": 0}}

    with pytest.raises(PreprocessingError):
        build_feature_vector({"gender": "L"}, metadata)


# ----------------------------------------------------------- model absen
def test_predictor_tanpa_artifact_tidak_siap(tmp_path):
    predictor = Predictor(model_dir=tmp_path)

    assert predictor.is_ready is False
    assert predictor.model_version is None
    assert predictor.load_error


# ---------------------------------------------------------------- inferensi
@requires_model
def test_model_dapat_dimuat():
    predictor = Predictor(model_dir=MODEL_DIR)

    assert predictor.is_ready is True
    assert predictor.model_version
    assert predictor.runtime_format in {"ONNX", "XGBoost"}


@requires_model
def test_jumlah_dan_nama_fitur_benar():
    metadata = json.loads((MODEL_DIR / "metadata.json").read_text(encoding="utf-8"))

    assert metadata["feature_order"] == ["gender", "age_months", "weight_kg", "height_cm"]
    assert len(metadata["feature_order"]) == 4
    assert metadata["label_mapping"]
    assert metadata["gender_encoding"]


@requires_model
def test_prediksi_valid_dan_probability_dalam_rentang(client):
    response = client.post("/api/v1/predict", json=VALID_PAYLOAD, headers=auth_headers())
    body = response.get_json()

    assert response.status_code == 200
    assert body["success"] is True

    data = body["data"]
    assert set(data.keys()) == {"prediction_class", "prediction_label", "probability", "model_version"}
    assert 0.0 <= data["probability"] <= 1.0

    metadata = json.loads((MODEL_DIR / "metadata.json").read_text(encoding="utf-8"))
    assert data["prediction_label"] in metadata["label_mapping"].values()
    assert str(data["prediction_class"]) in metadata["label_mapping"]


@requires_model
def test_preprocessing_konsisten_input_sama_hasil_sama(client):
    first = client.post("/api/v1/predict", json=VALID_PAYLOAD, headers=auth_headers()).get_json()
    second = client.post(
        "/api/v1/predict",
        json=dict(VALID_PAYLOAD, request_id="550e8400-e29b-41d4-a716-446655440099"),
        headers=auth_headers(),
    ).get_json()

    assert first["data"]["prediction_class"] == second["data"]["prediction_class"]
    assert first["data"]["probability"] == second["data"]["probability"]


@requires_model
def test_gender_numerik_dan_teks_memberi_hasil_sama(client):
    metadata = json.loads((MODEL_DIR / "metadata.json").read_text(encoding="utf-8"))
    code = metadata["gender_encoding"]["L"]

    teks = client.post("/api/v1/predict", json=VALID_PAYLOAD, headers=auth_headers()).get_json()
    numerik = client.post(
        "/api/v1/predict",
        json=dict(VALID_PAYLOAD, gender=code, request_id="550e8400-e29b-41d4-a716-446655440098"),
        headers=auth_headers(),
    ).get_json()

    assert teks["data"]["probability"] == numerik["data"]["probability"]


@requires_model
def test_model_info_memuat_metrik_hasil_training(client):
    body = client.get("/api/v1/model/info", headers=auth_headers()).get_json()
    data = body["data"]

    assert data["algorithm"] == "XGBoost"
    assert data["model_version"]
    assert set(data["metrics"].keys()) == {"accuracy", "precision", "recall", "f1_score"}

    for name, value in data["metrics"].items():
        assert value is None or 0.0 <= value <= 1.0, f"metrik {name} di luar rentang"


@requires_model
def test_model_yang_berbeda_kondisi_memberi_keluaran_berbeda(client):
    kurus = client.post(
        "/api/v1/predict",
        json=dict(VALID_PAYLOAD, weight_kg=8.0, height_cm=78.0,
                  request_id="550e8400-e29b-41d4-a716-446655440001"),
        headers=auth_headers(),
    ).get_json()

    sehat = client.post(
        "/api/v1/predict",
        json=dict(VALID_PAYLOAD, weight_kg=14.0, height_cm=94.0,
                  request_id="550e8400-e29b-41d4-a716-446655440002"),
        headers=auth_headers(),
    ).get_json()

    # Model nyata harus membedakan kedua kondisi; bila identik, artinya
    # keluaran tidak benar-benar berasal dari fitur masukan.
    assert kurus["data"]["probability"] != sehat["data"]["probability"]
