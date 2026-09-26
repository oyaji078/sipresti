"""Validasi payload request prediksi.

Validasi di sini bersifat teknis (tipe, finite, non-negatif, rentang wajar).
Ambang klinis TIDAK dikarang di sini: bila dataset/pedoman resmi sudah tersedia,
rentang diambil dari `validation_ranges` pada metadata model.
"""

from __future__ import annotations

import math
import uuid
from typing import Any

# Rentang teknis default. Bukan ambang klinis, hanya penjaga nilai mustahil.
DEFAULT_RANGES: dict[str, tuple[float, float]] = {
    "age_months": (0, 60),
    "weight_kg": (0.5, 60.0),
    "height_cm": (20.0, 150.0),
}

REQUIRED_FIELDS = ("gender", "age_months", "weight_kg", "height_cm")


class ValidationError(Exception):
    """Payload tidak lolos validasi."""

    def __init__(self, message: str, field: str | None = None) -> None:
        super().__init__(message)
        self.message = message
        self.field = field


def _as_number(value: Any, field: str) -> float:
    if isinstance(value, bool):
        raise ValidationError(f"Field '{field}' harus berupa angka.", field)
    if isinstance(value, (int, float)):
        number = float(value)
    elif isinstance(value, str):
        try:
            number = float(value.strip().replace(",", "."))
        except ValueError as exc:
            raise ValidationError(f"Field '{field}' harus berupa angka.", field) from exc
    else:
        raise ValidationError(f"Field '{field}' harus berupa angka.", field)

    if math.isnan(number) or math.isinf(number):
        raise ValidationError(f"Field '{field}' bukan angka yang valid.", field)
    return number


def validate_request_id(value: Any) -> str:
    if value is None or (isinstance(value, str) and not value.strip()):
        raise ValidationError("Field 'request_id' wajib diisi.", "request_id")
    if not isinstance(value, str):
        raise ValidationError("Field 'request_id' harus berupa string UUID.", "request_id")

    candidate = value.strip()
    try:
        uuid.UUID(candidate)
    except ValueError as exc:
        raise ValidationError("Field 'request_id' harus berupa UUID yang valid.", "request_id") from exc
    return candidate


def validate_payload(payload: Any, ranges: dict[str, list] | None = None) -> dict[str, Any]:
    """Kembalikan payload bersih atau lempar ValidationError."""
    if not isinstance(payload, dict):
        raise ValidationError("Body request harus berupa objek JSON.")

    cleaned: dict[str, Any] = {"request_id": validate_request_id(payload.get("request_id"))}

    for field in REQUIRED_FIELDS:
        if field not in payload or payload[field] is None or payload[field] == "":
            raise ValidationError(f"Field '{field}' wajib diisi.", field)

    gender = payload["gender"]
    if isinstance(gender, bool):
        raise ValidationError("Field 'gender' tidak valid.", "gender")
    if isinstance(gender, (int, float)):
        cleaned["gender"] = int(gender)
    elif isinstance(gender, str):
        value = gender.strip()
        if not value:
            raise ValidationError("Field 'gender' wajib diisi.", "gender")
        cleaned["gender"] = value
    else:
        raise ValidationError("Field 'gender' harus berupa string atau angka.", "gender")

    effective = dict(DEFAULT_RANGES)
    for key, bounds in (ranges or {}).items():
        if key in effective and isinstance(bounds, (list, tuple)) and len(bounds) == 2:
            effective[key] = (float(bounds[0]), float(bounds[1]))

    age = _as_number(payload["age_months"], "age_months")
    if age != int(age):
        raise ValidationError("Field 'age_months' harus berupa bilangan bulat.", "age_months")
    cleaned["age_months"] = int(age)

    cleaned["weight_kg"] = round(_as_number(payload["weight_kg"], "weight_kg"), 3)
    cleaned["height_cm"] = round(_as_number(payload["height_cm"], "height_cm"), 3)

    for field in ("age_months", "weight_kg", "height_cm"):
        low, high = effective[field]
        value = cleaned[field]
        if value < low or value > high:
            raise ValidationError(
                f"Field '{field}' di luar rentang yang diizinkan ({low} - {high}).", field
            )

    return cleaned
