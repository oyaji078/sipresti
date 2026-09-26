"""Pemuat model XGBoost dan mesin inferensi.

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
MODEL_FILENAME = "xgboost_model.json"
METADATA_FILENAME = "metadata.json"


class ModelNotReadyError(Exception):
    """Artifact model belum tersedia atau gagal dimuat."""


class Predictor:
    def __init__(self, model_dir: Path | None = None) -> None:
        self._lock = threading.Lock()
        self.model_dir = Path(model_dir or os.environ.get("MODEL_PATH") or DEFAULT_MODEL_DIR)
        self.model: Any = None
        self.metadata: dict[str, Any] = {}
        self.load_error: str | None = None
        self._load()

    # ------------------------------------------------------------------ load
    def _load(self) -> None:
        model_file = self.model_dir / MODEL_FILENAME
        metadata_file = self.model_dir / METADATA_FILENAME

        if metadata_file.exists():
            try:
                self.metadata = json.loads(metadata_file.read_text(encoding="utf-8"))
            except (OSError, json.JSONDecodeError) as exc:
                self.metadata = {}
                self.load_error = f"metadata.json tidak dapat dibaca: {exc}"

        if not model_file.exists():
            self.load_error = self.load_error or "Artifact model belum tersedia."
            return

        try:
            import xgboost as xgb
        except ImportError as exc:  # pragma: no cover - bergantung environment
            self.load_error = f"Library xgboost tidak tersedia: {exc}"
            return

        try:
            booster = xgb.Booster()
            booster.load_model(str(model_file))
            self.model = booster
            self.load_error = None
        except Exception as exc:  # noqa: BLE001 - kegagalan load harus dilaporkan apa adanya
            self.model = None
            self.load_error = f"Model gagal dimuat: {exc}"

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
        import xgboost as xgb

        try:
            vector = build_feature_vector(payload, self.metadata)
        except PreprocessingError as exc:
            raise ValueError(str(exc)) from exc

        feature_names = self.metadata.get("feature_order") or self.metadata.get("features")
        matrix = xgb.DMatrix(np.array([vector], dtype="float32"), feature_names=list(feature_names))

        with self._lock:
            raw = self.model.predict(matrix)

        raw_row = np.atleast_1d(raw[0])

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
