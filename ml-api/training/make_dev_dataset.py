"""Pembuat DATASET SINTETIS untuk pengembangan.

=============================================================================
PERINGATAN — DEVELOPMENT / SYNTHETIC DATA
=============================================================================
Berkas yang dihasilkan skrip ini BUKAN data pasien dan BUKAN data E-PPBGM.
Isinya dibangkitkan secara acak, semata-mata agar jalur integrasi
Hostinger -> Vercel -> XGBoost dapat diuji end-to-end sebelum dataset asli
tersedia.

Model yang dilatih dari berkas ini WAJIB diberi versi berakhiran
"-DEV-SYNTHETIC" dan TIDAK BOLEH dipakai sebagai hasil penelitian.
=============================================================================

Pemakaian:
    python training/make_dev_dataset.py --rows 1200 --out training/data/dev-synthetic.csv
"""

from __future__ import annotations

import argparse
import csv
import math
import random
from pathlib import Path

HEADER = [
    "id_balita",
    "nama",
    "jenis_kelamin",
    "usia_bulan",
    "berat_kg",
    "tinggi_cm",
    "status_gizi",
]


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Bangkitkan dataset sintetis untuk pengembangan")
    p.add_argument("--rows", type=int, default=1200)
    p.add_argument("--seed", type=int, default=2026)
    p.add_argument("--out", default="training/data/dev-synthetic.csv")
    return p.parse_args()


def expected_height(age_months: int, is_male: bool) -> float:
    """Perkiraan tinggi rata-rata kasar sebagai basis simulasi (bukan tabel resmi)."""
    base = 49.5 if is_male else 48.8
    # Pertumbuhan cepat pada tahun pertama lalu melandai.
    growth = 25.0 * (1 - math.exp(-age_months / 9.0)) + 0.52 * age_months
    return base + growth


def expected_weight(age_months: int, is_male: bool) -> float:
    """Perkiraan berat rata-rata kasar sebagai basis simulasi (bukan tabel resmi)."""
    base = 3.3 if is_male else 3.2
    # Naik cepat pada tahun pertama, lalu sekitar 0.2 kg per bulan.
    growth = 6.2 * (1 - math.exp(-age_months / 5.5)) + 0.19 * age_months
    return base + growth


def main() -> None:
    args = parse_args()
    rng = random.Random(args.seed)

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)

    rows = []
    for i in range(1, args.rows + 1):
        is_male = rng.random() < 0.51
        gender = "L" if is_male else "P"
        age = rng.randint(0, 59)

        # Faktor laten yang TIDAK ikut menjadi fitur model: menirukan pengaruh
        # pola asuh, asupan, dan riwayat penyakit. Faktor inilah yang membuat
        # label tidak menjadi fungsi deterministik dari antropometri.
        latent_risk = rng.gauss(0, 1)

        h_ref = expected_height(age, is_male)
        height = h_ref + rng.gauss(0, 2.6) - 2.4 * max(latent_risk, 0)
        height = max(44.0, min(120.0, height))

        w_ref = expected_weight(age, is_male)
        weight = w_ref + rng.gauss(0, 0.85) - 0.6 * max(latent_risk, 0)
        weight = max(2.0, min(28.0, weight))

        # Peluang risiko dipengaruhi defisit tinggi, defisit berat, usia, dan
        # faktor laten, lalu diacak. Tidak ada aturan ambang yang pasti.
        height_deficit = (h_ref - height) / 3.0
        weight_deficit = (w_ref - weight) / 1.4
        logit = -1.55 + 1.05 * height_deficit + 0.42 * weight_deficit + 0.55 * latent_risk + 0.012 * age
        probability = 1 / (1 + math.exp(-logit))

        label = "BERISIKO" if rng.random() < probability else "TIDAK_BERISIKO"

        rows.append([
            f"DEV-{i:05d}",
            f"Balita Sintetis {i}",
            gender,
            age,
            round(weight, 2),
            round(height, 1),
            label,
        ])

    with out_path.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh, delimiter=";")
        writer.writerow(HEADER)
        writer.writerows(rows)

    berisiko = sum(1 for r in rows if r[6] == "BERISIKO")
    print("=" * 70)
    print("DEVELOPMENT / SYNTHETIC DATA — BUKAN DATA PASIEN")
    print("=" * 70)
    print(f"Berkas        : {out_path}")
    print(f"Jumlah baris  : {len(rows)}")
    print(f"BERISIKO      : {berisiko} ({berisiko / len(rows) * 100:.1f}%)")
    print(f"TIDAK_BERISIKO: {len(rows) - berisiko} ({(len(rows) - berisiko) / len(rows) * 100:.1f}%)")
    print()
    print("Latih dengan versi yang jelas menandai sifatnya, contoh:")
    print(f"  python training/train.py --data {out_path} \\")
    print("      --col-gender jenis_kelamin --col-age usia_bulan \\")
    print("      --col-weight berat_kg --col-height tinggi_cm \\")
    print("      --col-label status_gizi --col-id id_balita \\")
    print("      --version XGB-0.0.1-DEV-SYNTHETIC")


if __name__ == "__main__":
    main()
