"""Konversi artifact XGBoost SIPRESTI menjadi artifact inferensi ONNX.

XGBoost tetap digunakan untuk training. Vercel hanya membutuhkan ONNX Runtime,
sehingga bundle fungsi jauh lebih kecil dan tetap menghasilkan prediksi yang
sama dengan model sumber.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

import onnxmltools
import xgboost as xgb
from onnxmltools.convert.common.data_types import FloatTensorType

BASE_DIR = Path(__file__).resolve().parent.parent
DEFAULT_MODEL_DIR = BASE_DIR / "models"


def convert(model_dir: Path) -> Path:
    source = model_dir / "xgboost_model.json"
    target = model_dir / "xgboost_model.onnx"
    metadata_path = model_dir / "metadata.json"

    if not source.exists():
        raise FileNotFoundError(f"Artifact XGBoost tidak ditemukan: {source}")
    if not metadata_path.exists():
        raise FileNotFoundError(f"Metadata model tidak ditemukan: {metadata_path}")

    metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
    feature_order = metadata.get("feature_order") or metadata.get("features")
    if not feature_order:
        raise ValueError("Metadata tidak memuat feature_order maupun features.")

    booster = xgb.Booster()
    booster.load_model(str(source))
    # Converter ONNXMLTools membaca dump pohon XGBoost dan mensyaratkan nama
    # internal f0, f1, ... . Urutan semantik tetap dijaga oleh metadata.
    booster.feature_names = None
    onnx_model = onnxmltools.convert_xgboost(
        booster,
        initial_types=[("features", FloatTensorType([None, len(feature_order)]))],
        target_opset=15,
    )
    onnxmltools.utils.save_model(onnx_model, str(target))

    metadata["runtime_artifact"] = target.name
    metadata["runtime_format"] = "ONNX"
    metadata["runtime_checksum"] = "sha256:" + hashlib.sha256(target.read_bytes()).hexdigest()
    metadata_path.write_text(
        json.dumps(metadata, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    return target


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--model-dir", type=Path, default=DEFAULT_MODEL_DIR)
    args = parser.parse_args()

    target = convert(args.model_dir.resolve())
    print(f"ONNX      : {target}")
    print(f"Ukuran    : {target.stat().st_size / 1024:.1f} KiB")


if __name__ == "__main__":
    main()
