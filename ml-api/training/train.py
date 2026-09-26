"""Pipeline training XGBoost untuk SIPRESTI.

Dijalankan OFFLINE (laptop peneliti), bukan di Vercel dan bukan saat pengguna
menekan tombol prediksi.

Prinsip yang ditegakkan skrip ini:
  * Nama kolom dataset tidak diasumsikan — ditentukan lewat argumen CLI.
  * Mapping label diambil APA ADANYA dari dataset, bukan ditebak.
  * Metrik yang ditulis ke metadata.json berasal dari hasil evaluasi nyata.
  * Pemeriksaan data leakage dijalankan dan dilaporkan, tidak disembunyikan.
  * Penyeimbangan kelas TIDAK otomatis; harus diminta eksplisit.

Contoh:
    python training/train.py \
        --data data/eppbgm.xlsx \
        --col-gender "Jenis Kelamin" --col-age "Usia (bulan)" \
        --col-weight "Berat" --col-height "Tinggi" \
        --col-label "Status TB/U" \
        --version XGB-1.0.0

Dependensi: pip install -r training/requirements-training.txt
"""

from __future__ import annotations

import argparse
import hashlib
import json
import platform
import sys
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import pandas as pd
import xgboost as xgb
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import train_test_split
from sklearn.tree import DecisionTreeClassifier

FEATURE_ORDER = ["gender", "age_months", "weight_kg", "height_cm"]
BASE_DIR = Path(__file__).resolve().parent.parent


# --------------------------------------------------------------------- CLI
def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Training model XGBoost prediksi risiko stunting")
    p.add_argument("--data", required=True, help="Berkas dataset (.csv / .xlsx)")
    p.add_argument("--sheet", default=0, help="Nama/indeks sheet untuk berkas Excel")

    p.add_argument("--col-gender", required=True)
    p.add_argument("--col-age", required=True)
    p.add_argument("--col-weight", required=True)
    p.add_argument("--col-height", required=True)
    p.add_argument("--col-label", required=True)
    p.add_argument("--col-id", default=None,
                   help="Kolom pengenal balita. Bila diisi, split dilakukan per balita "
                        "agar pengukuran anak yang sama tidak bocor ke data uji.")

    p.add_argument("--version", required=True, help="Versi model, contoh XGB-1.0.0")
    p.add_argument("--test-size", type=float, default=0.2)
    p.add_argument("--random-state", type=int, default=42)
    p.add_argument("--balance", choices=["none", "scale_pos_weight"], default="none",
                   help="Penanganan ketidakseimbangan kelas. Default: none (lihat laporan distribusi dulu).")
    p.add_argument("--n-estimators", type=int, default=300)
    p.add_argument("--max-depth", type=int, default=4)
    p.add_argument("--learning-rate", type=float, default=0.1)
    p.add_argument("--subsample", type=float, default=0.9)
    p.add_argument("--colsample-bytree", type=float, default=0.9)

    p.add_argument("--out", default=str(BASE_DIR / "models"))
    p.add_argument("--note", default="", help="Catatan bebas yang disimpan di metadata")
    p.add_argument("--allow-leakage", action="store_true",
                   help="Lanjutkan training walau terdeteksi indikasi kuat data leakage.")
    return p.parse_args()


# ------------------------------------------------------------------ helpers
def log(msg: str = "") -> None:
    print(msg, flush=True)


def section(title: str) -> None:
    log()
    log("=" * 70)
    log(title)
    log("=" * 70)


def load_dataframe(path: Path, sheet) -> pd.DataFrame:
    if not path.exists():
        sys.exit(f"Dataset tidak ditemukan: {path}")

    if path.suffix.lower() in {".xlsx", ".xls"}:
        return pd.read_excel(path, sheet_name=sheet)

    for sep in (";", ","):
        try:
            df = pd.read_csv(path, sep=sep)
            if df.shape[1] > 1:
                return df
        except Exception:  # noqa: BLE001
            continue

    sys.exit(f"Berkas tidak dapat dibaca sebagai CSV/Excel: {path}")


def fingerprint(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            digest.update(chunk)
    return "sha256:" + digest.hexdigest()


def to_number(series: pd.Series) -> pd.Series:
    if series.dtype.kind in "if":
        return series.astype(float)

    cleaned = (
        series.astype(str)
        .str.strip()
        .str.replace(" ", "", regex=False)
        .str.replace(",", ".", regex=False)
    )
    return pd.to_numeric(cleaned, errors="coerce")


# ------------------------------------------------------------------ pipeline
def main() -> None:
    args = parse_args()
    data_path = Path(args.data)
    out_dir = Path(args.out)
    out_dir.mkdir(parents=True, exist_ok=True)

    section("1. VALIDATION — memuat dataset")
    df = load_dataframe(data_path, args.sheet)
    log(f"Berkas      : {data_path}")
    log(f"Fingerprint : {fingerprint(data_path)}")
    log(f"Dimensi     : {df.shape[0]} baris x {df.shape[1]} kolom")

    required = {
        "gender": args.col_gender,
        "age_months": args.col_age,
        "weight_kg": args.col_weight,
        "height_cm": args.col_height,
        "label": args.col_label,
    }
    if args.col_id:
        required["id"] = args.col_id

    missing = [c for c in required.values() if c not in df.columns]
    if missing:
        log()
        log("Kolom pada dataset:")
        for c in df.columns:
            log(f"  - {c}")
        sys.exit(f"\nKolom berikut tidak ada di dataset: {missing}")

    work = df[list(required.values())].copy()
    work.columns = list(required.keys())

    section("2. CLEANING")
    before = len(work)

    work["age_months"] = to_number(work["age_months"])
    work["weight_kg"] = to_number(work["weight_kg"])
    work["height_cm"] = to_number(work["height_cm"])
    work["gender"] = work["gender"].astype(str).str.strip()
    work["label"] = work["label"].astype(str).str.strip()

    log("Nilai kosong per kolom:")
    for col, total in work.isna().sum().items():
        log(f"  {col:<12} {total}")

    work = work.dropna(subset=["age_months", "weight_kg", "height_cm", "label"])
    work = work[work["label"] != ""]
    work = work[(work["weight_kg"] > 0) & (work["height_cm"] > 0) & (work["age_months"] >= 0)]

    duplicates = work.duplicated().sum()
    work = work.drop_duplicates()

    log(f"\nBaris awal            : {before}")
    log(f"Duplikat dibuang      : {duplicates}")
    log(f"Baris dipakai         : {len(work)}")

    if len(work) < 50:
        sys.exit("Data bersih terlalu sedikit (<50 baris) untuk dilatih dan dievaluasi.")

    section("3. FEATURE PREPARATION — encoding diambil dari dataset")
    gender_values = sorted(work["gender"].unique())
    gender_encoding = {value: index for index, value in enumerate(gender_values)}
    log("gender_encoding (urutan alfabetis nilai yang benar-benar ada di dataset):")
    for value, code in gender_encoding.items():
        log(f"  {value!r} -> {code}")

    label_values = sorted(work["label"].unique())
    label_encoding = {value: index for index, value in enumerate(label_values)}
    label_mapping = {str(index): value for value, index in label_encoding.items()}
    log("\nlabel_mapping (kelas model -> label asli dataset):")
    for index, value in label_mapping.items():
        count = int((work["label"] == value).sum())
        log(f"  {index} -> {value!r}   (n={count})")

    work["gender_code"] = work["gender"].map(gender_encoding)
    work["y"] = work["label"].map(label_encoding)

    n_classes = len(label_values)
    if n_classes < 2:
        sys.exit("Dataset hanya memuat satu kelas label. Tidak dapat dilatih.")

    X = work[["gender_code", "age_months", "weight_kg", "height_cm"]].to_numpy(dtype="float32")
    y = work["y"].to_numpy(dtype="int32")

    section("4. LEAKAGE CHECK")
    log("Menguji apakah label dapat direkonstruksi secara deterministik dari")
    log("(jenis kelamin, usia, tinggi) — tiga variabel yang juga dipakai untuk")
    log("menghitung Z-score TB/U pada E-PPGBM.")
    probe_features = work[["gender_code", "age_months", "height_cm"]].to_numpy(dtype="float32")
    probe = DecisionTreeClassifier(max_depth=8, random_state=args.random_state)
    probe.fit(probe_features, y)
    probe_score = float(probe.score(probe_features, y))
    log(f"\nAkurasi pohon keputusan dangkal pada data latihnya sendiri: {probe_score:.4f}")

    leakage_suspected = probe_score >= 0.995
    if leakage_suspected:
        log()
        log("!! INDIKASI KUAT DATA LEAKAGE.")
        log("!! Label tampak merupakan fungsi deterministik dari fitur antropometri,")
        log("!! sehingga model hanya menghafal ulang tabel standar, bukan memprediksi")
        log("!! risiko. Metrik yang dihasilkan akan terlihat hampir sempurna dan")
        log("!! TIDAK dapat ditafsirkan sebagai kemampuan prediksi.")
        if not args.allow_leakage:
            sys.exit(
                "\nTraining dihentikan. Ubah rumusan target (mis. memprediksi status pada\n"
                "pengukuran berikutnya, atau menambah prediktor non-antropometrik), atau\n"
                "jalankan ulang dengan --allow-leakage bila memang disengaja."
            )
        log("!! Dilanjutkan karena --allow-leakage diberikan.")
    else:
        log("Tidak ada indikasi leakage deterministik.")

    section("5. SPLIT")
    stratify = y if np.min(np.bincount(y)) >= 2 else None

    if args.col_id:
        # Split per balita: seluruh pengukuran satu anak berada di sisi yang sama.
        from sklearn.model_selection import GroupShuffleSplit

        groups = work["id"].astype(str).to_numpy()
        splitter = GroupShuffleSplit(n_splits=1, test_size=args.test_size, random_state=args.random_state)
        train_idx, test_idx = next(splitter.split(X, y, groups=groups))
        X_train, X_test = X[train_idx], X[test_idx]
        y_train, y_test = y[train_idx], y[test_idx]
        log(f"Split per balita (kolom '{args.col_id}'), jumlah balita unik: {len(set(groups))}")
    else:
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=args.test_size, random_state=args.random_state, stratify=stratify
        )
        log("Split acak per baris (stratified)." if stratify is not None else "Split acak per baris.")
        log("CATATAN: bila satu balita punya beberapa pengukuran, gunakan --col-id "
            "agar tidak terjadi kebocoran antar-split.")

    log(f"\nData latih : {len(y_train)}")
    log(f"Data uji   : {len(y_test)}")
    log("\nDistribusi kelas data latih:")
    for index, count in zip(*np.unique(y_train, return_counts=True)):
        log(f"  {label_mapping[str(index)]!r}: {count} ({count / len(y_train) * 100:.1f}%)")

    section("6. IMBALANCE")
    counts = np.bincount(y_train)
    ratio = counts.max() / max(counts.min(), 1)
    log(f"Rasio kelas terbanyak : terkecil = {ratio:.2f}")
    params: dict = {
        "n_estimators": args.n_estimators,
        "max_depth": args.max_depth,
        "learning_rate": args.learning_rate,
        "subsample": args.subsample,
        "colsample_bytree": args.colsample_bytree,
        "random_state": args.random_state,
        "eval_metric": "logloss" if n_classes == 2 else "mlogloss",
    }

    if args.balance == "scale_pos_weight":
        if n_classes != 2:
            sys.exit("--balance scale_pos_weight hanya berlaku untuk klasifikasi dua kelas.")
        params["scale_pos_weight"] = float(counts[0] / max(counts[1], 1))
        log(f"scale_pos_weight diterapkan: {params['scale_pos_weight']:.4f} (diminta eksplisit)")
    else:
        log("Tidak ada penyeimbangan kelas (default). Aktifkan hanya bila memang diperlukan.")

    if n_classes > 2:
        params["objective"] = "multi:softprob"
        params["num_class"] = n_classes
    else:
        params["objective"] = "binary:logistic"

    section("7. XGBOOST TRAINING")
    model = xgb.XGBClassifier(**params)
    model.fit(X_train, y_train)
    log("Training selesai.")

    section("8. EVALUATION")
    y_pred = model.predict(X_test)
    y_proba = model.predict_proba(X_test)

    average = "binary" if n_classes == 2 else "macro"
    pos_label_index = None

    if n_classes == 2:
        # sklearn's average='binary' defaults to pos_label=1, yang BUKAN
        # jaminan kelas "berisiko". Kelas positif harus ditentukan secara
        # eksplisit, kalau tidak precision/recall/F1 yang dilaporkan bisa
        # diam-diam menjadi metrik untuk kelas mayoritas/sehat -- salah arah
        # untuk skrining kesehatan (RNF-19: recall kelas berisiko diprioritaskan).
        AT_RISK_WORDS = ("RISIKO", "STUNTING", "POSITIF", "PENDEK", "KURANG", "BURUK")
        SAFE_WORDS = ("TIDAK", "NORMAL", "NEGATIF", "BAIK", "SEHAT")

        candidates = []
        for idx, name in label_mapping.items():
            upper = name.upper()
            is_risk_like = any(w in upper for w in AT_RISK_WORDS)
            is_safe_like = any(w in upper for w in SAFE_WORDS)
            if is_risk_like and not is_safe_like:
                candidates.append(int(idx))

        if len(candidates) == 1:
            pos_label_index = candidates[0]
            log(f"Kelas positif (berisiko) dikenali dari nama label: "
                f"{label_mapping[str(pos_label_index)]!r} (indeks {pos_label_index}).")
        else:
            # Tidak dapat dikenali dari nama -> pakai kelas minoritas di data
            # latih, konvensi umum pada skrining kesehatan (kondisi yang
            # dicari selalu lebih jarang daripada kondisi normal).
            train_counts = np.bincount(y_train, minlength=n_classes)
            pos_label_index = int(np.argmin(train_counts))
            log(f"Kelas positif tidak dapat dikenali dari nama label; dipakai "
                f"kelas minoritas data latih: {label_mapping[str(pos_label_index)]!r} "
                f"(indeks {pos_label_index}, n={train_counts[pos_label_index]}).")

        metrics_kwargs = {"average": average, "pos_label": pos_label_index, "zero_division": 0}
    else:
        metrics_kwargs = {"average": average, "zero_division": 0}

    metrics = {
        "accuracy": float(accuracy_score(y_test, y_pred)),
        "precision": float(precision_score(y_test, y_pred, **metrics_kwargs)),
        "recall": float(recall_score(y_test, y_pred, **metrics_kwargs)),
        "f1_score": float(f1_score(y_test, y_pred, **metrics_kwargs)),
    }

    try:
        if n_classes == 2:
            # roc_auc_score biner MENGASUMSIKAN skor yang diberikan adalah
            # probabilitas untuk label bernilai besar (umumnya 1). Bila kelas
            # positif kita justru berlabel 0 (mis. karena urutan alfabetis),
            # y_true harus di-biner-kan ulang secara eksplisit -- kalau tidak,
            # AUC yang dihasilkan justru untuk kelas yang salah (terbalik).
            y_true_pos = (y_test == pos_label_index).astype(int)
            metrics["roc_auc"] = float(roc_auc_score(y_true_pos, y_proba[:, pos_label_index]))
        else:
            metrics["roc_auc"] = float(roc_auc_score(y_test, y_proba, multi_class="ovr", average="macro"))
    except ValueError:
        metrics["roc_auc"] = None

    cm = confusion_matrix(y_test, y_pred).tolist()

    pos_label_note = (
        f", kelas positif={label_mapping[str(pos_label_index)]!r}" if pos_label_index is not None else ""
    )
    log(f"Accuracy  : {metrics['accuracy']:.4f}")
    log(f"Precision : {metrics['precision']:.4f}  (average={average}{pos_label_note})")
    log(f"Recall    : {metrics['recall']:.4f}  (average={average}{pos_label_note})")
    log(f"F1-Score  : {metrics['f1_score']:.4f}")
    roc = metrics["roc_auc"]
    log("ROC-AUC   : " + ("tidak dapat dihitung" if roc is None else f"{roc:.4f}"))
    log("\nConfusion Matrix (baris = aktual, kolom = prediksi):")
    header = "            " + "".join(f"{label_mapping[str(i)][:10]:>12}" for i in range(n_classes))
    log(header)
    for i, row in enumerate(cm):
        log(f"{label_mapping[str(i)][:10]:>10}  " + "".join(f"{v:>12}" for v in row))

    log("\nClassification report:")
    log(classification_report(
        y_test, y_pred,
        target_names=[label_mapping[str(i)] for i in range(n_classes)],
        zero_division=0,
    ))

    importance = dict(zip(FEATURE_ORDER, [float(v) for v in model.feature_importances_]))
    log("Feature importance (gain relatif):")
    for name, value in sorted(importance.items(), key=lambda kv: -kv[1]):
        log(f"  {name:<12} {value:.4f}")

    section("9. SAVE ARTIFACT")
    model_path = out_dir / "xgboost_model.json"
    model.get_booster().feature_names = FEATURE_ORDER
    model.get_booster().save_model(str(model_path))

    checksum = "sha256:" + hashlib.sha256(model_path.read_bytes()).hexdigest()

    metadata = {
        "version": args.version,
        "algorithm": "XGBoost",
        "features": FEATURE_ORDER,
        "feature_order": FEATURE_ORDER,
        "gender_encoding": gender_encoding,
        "label_mapping": label_mapping,
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "random_state": args.random_state,
        "dataset_fingerprint": fingerprint(data_path),
        "dataset_file": data_path.name,
        "source_columns": required,
        "library_versions": {
            "python": platform.python_version(),
            "xgboost": xgb.__version__,
            "numpy": np.__version__,
            "pandas": pd.__version__,
        },
        "parameters": params,
        "split": {
            "dataset_size": int(len(work)),
            "training_size": int(len(y_train)),
            "testing_size": int(len(y_test)),
            "test_size": args.test_size,
            "grouped_by": args.col_id,
        },
        "metrics": metrics,
        "metrics_positive_class": label_mapping[str(pos_label_index)] if pos_label_index is not None else None,
        "confusion_matrix": cm,
        "feature_importance": importance,
        "leakage_check": {
            "probe_train_accuracy": probe_score,
            "suspected": bool(leakage_suspected),
        },
        "checksum": checksum,
        "artifact_name": model_path.name,
        # Rentang yang teramati pada data latih. Sifatnya INFORMATIF saja.
        # Sengaja tidak dipakai sebagai gerbang validasi API, karena balita
        # nyata bisa berada sedikit di luar rentang data latih dan tidak boleh
        # ditolak hanya karena itu. Gerbang validasi memakai batas teknis di
        # ml/validator.py.
        "observed_ranges": {
            "age_months": [float(work["age_months"].min()), float(work["age_months"].max())],
            "weight_kg": [float(work["weight_kg"].min()), float(work["weight_kg"].max())],
            "height_cm": [float(work["height_cm"].min()), float(work["height_cm"].max())],
        },
        "note": args.note,
    }

    metadata_path = out_dir / "metadata.json"
    metadata_path.write_text(json.dumps(metadata, indent=2, ensure_ascii=False), encoding="utf-8")

    log(f"Model    : {model_path}")
    log(f"Metadata : {metadata_path}")
    log(f"Checksum : {checksum}")
    log()
    log("Seluruh metrik di metadata.json berasal dari evaluasi di atas.")
    log("Langkah berikutnya: deploy ml-api ke Vercel, lalu tekan Sinkronisasi")
    log("pada halaman Admin > Model XGBoost.")


if __name__ == "__main__":
    main()
