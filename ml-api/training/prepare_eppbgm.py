"""Pembersihan dataset E-PPBGM asli menjadi berkas siap latih.

Dataset sumber adalah ekspor "Daftar Anak Berdasarkan Status Gizi" dari
Sigizi Terpadu/e-PPGBM (berformat HTML disimpan sebagai .xls). Skrip ini:

  1. Membaca kedua berkas ekspor dan menggabungkannya (dedup berdasarkan NIK,
     mengambil data terbaru bila ada duplikat).
  2. Menghitung age_months dari (Tgl Lahir, Tanggal Pengukuran) — BUKAN dari
     kolom teks "Usia Saat Ukur" — agar konsisten dengan cara aplikasi
     menghitung usia (Measurement::calculateAgeMonths).
  3. Membentuk label biner dari kolom TB/U, mengikuti kategori resmi Kemenkes:
     "Pendek" + "Sangat Pendek" -> BERISIKO_STUNTING
     "Normal"                   -> TIDAK_BERISIKO_STUNTING
     Kategori "Tinggi" (bila ada) DIBUANG dari data latih: itu bukan stunting
     maupun target yang didefinisikan skripsi, dan tidak dikategorikan asal.
  4. TIDAK membuang kolom BB Lahir, TB Lahir, Naik Berat Badan, MBG — kolom-
     kolom itu disimpan di berkas keluaran untuk opsi eksperimen tambahan,
     tetapi TIDAK dipakai sebagai fitur pada model utama (lihat README di
     docs/PHASE-0-AUDIT.md §B K-1: fitur model dikunci oleh Master Prompt
     pada gender, age_months, weight_kg, height_cm).

Keluaran: training/data/eppbgm-clean.csv
"""

from __future__ import annotations

import re
from pathlib import Path

import pandas as pd

BASE_DIR = Path(__file__).resolve().parent.parent.parent
OUT_DIR = Path(__file__).resolve().parent / "data"
OUT_DIR.mkdir(parents=True, exist_ok=True)

SOURCES = [
    BASE_DIR / "Daftar-Anak-Berdasarkan-Status-Gizi-220926091639.xls",
    BASE_DIR / "Daftar-Anak-Berdasarkan-Status-Gizi-220926095441.xls",
]


def load(path: Path) -> pd.DataFrame:
    tables = pd.read_html(path)
    return tables[0]


def compute_age_months(birth: pd.Timestamp, measured: pd.Timestamp) -> int | None:
    if pd.isna(birth) or pd.isna(measured) or measured < birth:
        return None
    months = (measured.year - birth.year) * 12 + (measured.month - birth.month)
    if measured.day < birth.day:
        months -= 1
    return max(0, int(months))


def main() -> None:
    frames = []
    for src in SOURCES:
        if not src.exists():
            print(f"Lewati (tidak ditemukan): {src}")
            continue
        df = load(src)
        df["_sumber"] = src.name
        frames.append(df)
        print(f"Dimuat {src.name}: {df.shape[0]} baris")

    if not frames:
        raise SystemExit("Tidak ada berkas sumber yang ditemukan.")

    combined = pd.concat(frames, ignore_index=True)
    print(f"\nGabungan sebelum dedup: {combined.shape[0]} baris")

    combined["Tanggal Pengukuran"] = pd.to_datetime(combined["Tanggal Pengukuran"], errors="coerce")
    combined = combined.sort_values("Tanggal Pengukuran").drop_duplicates(subset="NIK", keep="last")
    print(f"Sesudah dedup NIK (ambil pengukuran terbaru): {combined.shape[0]} baris")

    combined["Tgl Lahir"] = pd.to_datetime(combined["Tgl Lahir"], errors="coerce")

    combined["age_months"] = combined.apply(
        lambda r: compute_age_months(r["Tgl Lahir"], r["Tanggal Pengukuran"]), axis=1
    )

    def normalize_label(value: str) -> str | None:
        value = str(value).strip()
        if value in ("Pendek", "Sangat Pendek"):
            return "BERISIKO_STUNTING"
        if value == "Normal":
            return "TIDAK_BERISIKO_STUNTING"
        return None  # "Tinggi" atau nilai tak dikenal -> dibuang dari data latih

    combined["status_stunting"] = combined["TB/U"].apply(normalize_label)

    before_label_filter = len(combined)
    dropped_labels = combined[combined["status_stunting"].isna()]["TB/U"].value_counts()
    combined = combined.dropna(subset=["status_stunting"])
    print(f"\nBaris dengan label TB/U di luar Normal/Pendek/Sangat Pendek dibuang: "
          f"{before_label_filter - len(combined)}")
    if len(dropped_labels) > 0:
        print(dropped_labels)

    out = pd.DataFrame({
        "nik": combined["NIK"].astype(str),
        "nama": combined["Nama"],
        "jenis_kelamin": combined["JK"],
        "age_months": combined["age_months"],
        "berat_kg": pd.to_numeric(combined["Berat"], errors="coerce"),
        "tinggi_cm": pd.to_numeric(combined["Tinggi"], errors="coerce"),
        "cara_ukur": combined["Cara Ukur"],
        "berat_lahir_kg": pd.to_numeric(combined["BB Lahir"], errors="coerce"),
        "tinggi_lahir_cm": pd.to_numeric(combined["TB Lahir"], errors="coerce"),
        "naik_berat_badan": combined["Naik Berat Badan"],
        "mbg": combined["MBG"],
        "posyandu": combined["Posyandu"],
        "desa": combined["Desa/Kel"],
        "status_gizi_tb_u_asli": combined["TB/U"],
        "zs_tb_u": pd.to_numeric(combined["ZS TB/U"], errors="coerce"),
        "status_stunting": combined["status_stunting"],
    })

    before_na = len(out)
    out = out.dropna(subset=["age_months", "berat_kg", "tinggi_cm", "jenis_kelamin"])
    print(f"\nBaris dibuang karena usia/berat/tinggi/JK kosong: {before_na - len(out)}")

    out_path = OUT_DIR / "eppbgm-clean.csv"
    out.to_csv(out_path, index=False, sep=";")

    print(f"\n{'=' * 70}")
    print(f"Berkas bersih: {out_path}")
    print(f"Jumlah baris final: {len(out)}")
    print(f"{'=' * 70}")
    print("\nDistribusi label biner (status_stunting):")
    print(out["status_stunting"].value_counts())
    print(f"Proporsi BERISIKO_STUNTING: {(out['status_stunting'] == 'BERISIKO_STUNTING').mean() * 100:.1f}%")
    print("\nDistribusi jenis kelamin:")
    print(out["jenis_kelamin"].value_counts())
    print("\nRentang usia (bulan):", out["age_months"].min(), "-", out["age_months"].max())
    print("Rentang berat (kg):", out["berat_kg"].min(), "-", out["berat_kg"].max())
    print("Rentang tinggi (cm):", out["tinggi_cm"].min(), "-", out["tinggi_cm"].max())


if __name__ == "__main__":
    main()
