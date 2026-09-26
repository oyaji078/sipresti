"""Pemuat model XGBoost dan mesin inferensi ONNX/XGBoost.

Model dimuat SATU KALI saat modul diinisialisasi, bukan per request.
Bila artifact model belum tersedia, predictor berstatus "belum siap" dan API
mengembalikan MODEL_NOT_READY. Tidak ada hasil prediksi yang dikarang.
"""

from __future__ import annotations

import json
import os
import threading
from pathlib import Path
from typing import Any

from .preprocessing import PreprocessingError, build_feature_vector

BASE_DIR = Path(__file__).resolve().parent.parent
DEFAULT_MODEL_DIR = BASE_DIR / "models"
ONNX_MODEL_FILENAME = "xgboost_model.onnx"
XGBOOST_MODEL_FILENAME = "xgboost_model.json"
METADATA_FILENAME = "metadata.json"


class ModelNotReadyError(Exception):
    """Artifact model belum tersedia atau gagal dimuat."""


class Predictor:
    def __init__(self, model_dir: Path | None = None) -> None:
        self._lock = threading.Lock()
        self.model_dir = Path(model_dir or os.environ.get("MODEL_PATH") or DEFAULT_MODEL_DIR)
        self.model: Any = None
        self.runtime_format: str | None = None
        self.input_name: str | None = None
        self.output_names: list[str] = []
        self.metadata: dict[str, Any] = {}
        self.load_error: str | None = None
        self._load()

    # ------------------------------------------------------------------ load
    def _load(self) -> None:
        onnx_model_file = self.model_dir / ONNX_MODEL_FILENAME
        xgboost_model_file = self.model_dir / XGBOOST_MODEL_FILENAME
        metadata_file = self.model_dir / METADATA_FILENAME

        if metadata_file.exists():
            try:
                self.metadata = json.loads(metadata_file.read_text(encoding="utf-8"))
            except (OSError, json.JSONDecodeError) as exc:
                self.metadata = {}
                self.load_error = f"metadata.json tidak dapat dibaca: {exc}"

        if not onnx_model_file.exists() and not xgboost_model_file.exists():
            self.load_error = self.load_error or "Artifact model belum tersedia."
            return

        # ONNX diprioritaskan karena runtime produksinya jauh lebih kecil untuk
        # Vercel. Artifact XGBoost native tetap menjadi fallback pengembangan.
        if onnx_model_file.exists():
            try:
                import onnxruntime as ort

                session = ort.InferenceSession(
                    str(onnx_model_file),
                    providers=["CPUExecutionProvider"],
                )
                self.model = session
                self.runtime_format = "ONNX"
                self.input_name = session.get_inputs()[0].name
                self.output_names = [output.name for output in session.get_outputs()]
                self.load_error = None
                return
            except Exception as exc:  # noqa: BLE001 - coba fallback native di bawah
                self.load_error = f"Model ONNX gagal dimuat: {exc}"

        if xgboost_model_file.exists():
            try:
                import xgboost as xgb

                booster = xgb.Booster()
                booster.load_model(str(xgboost_model_file))
                self.model = booster
                self.runtime_format = "XGBoost"
                self.load_error = None
                return
            except Exception as exc:  # noqa: BLE001 - kegagalan load harus dilaporkan apa adanya
                self.model = None
                self.runtime_format = None
                fallback_error = f"Model XGBoost gagal dimuat: {exc}"
                self.load_error = f"{self.load_error}; {fallback_error}" if self.load_error else fallback_error

    # --------------------------------------------------------------- helpers
    @property
    def is_ready(self) -> bool:
        return self.model is not None and bool(self.metadata.get("feature_order") or self.metadata.get("features"))

    @property
    def model_version(self) -> str | None:
        return self.metadata.get("version")

    @property
    def validation_ranges(self) -> dict[str, list]:
        ranges = self.metadata.get("validation_ranges")
        return ranges if isinstance(ranges, dict) else {}

    def info(self) -> dict[str, Any]:
        metrics = self.metadata.get("metrics") or {}
        return {
            "model_version": self.model_version,
            "algorithm": self.metadata.get("algorithm"),
            "runtime_format": self.runtime_format,
            "features": self.metadata.get("features"),
            "feature_order": self.metadata.get("feature_order"),
            "label_mapping": self.metadata.get("label_mapping"),
            "gender_encoding": self.metadata.get("gender_encoding"),
            "trained_at": self.metadata.get("trained_at"),
            "checksum": self.metadata.get("checksum"),
            "dataset_size": (self.metadata.get("split") or {}).get("dataset_size"),
            "training_size": (self.metadata.get("split") or {}).get("training_size"),
            "testing_size": (self.metadata.get("split") or {}).get("testing_size"),
            "parameters": self.metadata.get("parameters"),
            "metrics": {
                "accuracy": metrics.get("accuracy"),
                "precision": metrics.get("precision"),
                "recall": metrics.get("recall"),
                "f1_score": metrics.get("f1_score"),
            },
        }

    # ------------------------------------------------------------- inference
    def _resolve_label(self, prediction_class: int) -> str:
        mapping = self.metadata.get("label_mapping") or {}
        for key in (str(prediction_class), prediction_class):
            if key in mapping:
                return str(mapping[key])
        return str(prediction_class)

    def predict(self, payload: dict[str, Any]) -> dict[str, Any]:
        if not self.is_ready:
            raise ModelNotReadyError(self.load_error or "Model belum siap.")

        import numpy as np
        try:
            vector = build_feature_vector(payload, self.metadata)
        except PreprocessingError as exc:
            raise ValueError(str(exc)) from exc

        matrix = np.array([vector], dtype="float32")

        if self.runtime_format == "ONNX":
            if not self.input_name:
                raise ModelNotReadyError("Nama input model ONNX tidak tersedia.")
            with self._lock:
                outputs = self.model.run(None, {self.input_name: matrix})

            named_outputs = dict(zip(self.output_names, outputs))
            probabilities_output = named_outputs.get("probabilities")
            if probabilities_output is None:
                probabilities_output = next(
                    (value for value in outputs if np.asarray(value).ndim == 2),
                    None,
                )
            if probabilities_output is None:
                raise RuntimeError("Model ONNX tidak menghasilkan vektor probabilitas.")
            raw_row = np.asarray(probabilities_output[0], dtype="float32")
        elif self.runtime_format == "XGBoost":
            import xgboost as xgb

            feature_names = self.metadata.get("feature_order") or self.metadata.get("features")
            dmatrix = xgb.DMatrix(matrix, feature_names=list(feature_names))
            with self._lock:
                raw = self.model.predict(dmatrix)
            raw_row = np.atleast_1d(raw[0])
        else:
            raise ModelNotReadyError("Runtime model tidak dikenali.")

        if raw_row.size == 1:
            # Objective biner: keluaran adalah probabilitas kelas positif.
            probability_positive = float(raw_row[0])
            prediction_class = int(probability_positive >= 0.5)
            probability = probability_positive if prediction_class == 1 else 1.0 - probability_positive
            probabilities = {
                "0": round(1.0 - probability_positive, 6),
                "1": round(probability_positive, 6),
            }
        else:
            # Objective multi-kelas: keluaran adalah vektor probabilitas.
            prediction_class = int(np.argmax(raw_row))
            probability = float(raw_row[prediction_class])
            probabilities = {str(i): round(float(v), 6) for i, v in enumerate(raw_row)}

        return {
            "prediction_class": prediction_class,
            "prediction_label": self._resolve_label(prediction_class),
            "probability": round(probability, 6),
            "probabilities": probabilities,
            "model_version": self.model_version,
        }


_predictor: Predictor | None = None


def get_predictor() -> Predictor:
    """Singleton predictor — model hanya dimuat sekali per proses."""
    global _predictor
    if _predictor is None:
        _predictor = Predictor()
    return _predictor


def reload_predictor() -> Predictor:
    global _predictor
    _predictor = Predictor()
    return _predictor
