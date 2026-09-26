"""Preprocessing feature sebelum masuk ke model XGBoost.

Urutan dan encoding feature SELALU mengikuti `metadata.json` milik model,
tidak pernah diasumsikan di dalam kode.
"""

from __future__ import annotations

from typing import Any


class PreprocessingError(Exception):
    """Feature tidak dapat disiapkan sesuai metadata model."""


def encode_gender(value: Any, mapping: dict[str, Any] | None) -> float:
    """Ubah nilai gender menjadi numerik sesuai `gender_encoding` metadata."""
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        return float(value)

    if not mapping:
        raise PreprocessingError(
            "Nilai 'gender' berupa teks tetapi metadata model tidak memuat 'gender_encoding'."
        )

    key = str(value).strip()
    for candidate in (key, key.upper(), key.lower(), key.capitalize()):
        if candidate in mapping:
            return float(mapping[candidate])

    normalized = {str(k).strip().lower(): v for k, v in mapping.items()}
    if key.lower() in normalized:
        return float(normalized[key.lower()])

    raise PreprocessingError(f"Nilai 'gender' tidak dikenali: {value!r}")


def build_feature_vector(payload: dict[str, Any], metadata: dict[str, Any]) -> list[float]:
    """Susun vektor feature mengikuti urutan pada metadata model."""
    order = metadata.get("feature_order") or metadata.get("features")
    if not order:
        raise PreprocessingError("Metadata model tidak memuat 'feature_order' maupun 'features'.")

    gender_encoding = metadata.get("gender_encoding") or {}
    vector: list[float] = []

    for feature in order:
        if feature not in payload:
            raise PreprocessingError(f"Feature '{feature}' tidak tersedia pada payload.")

        value = payload[feature]
        if feature == "gender":
            vector.append(encode_gender(value, gender_encoding))
        else:
            vector.append(float(value))

    return vector
