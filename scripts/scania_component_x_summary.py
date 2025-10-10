#!/usr/bin/env python3
"""
Summarise the SCANIA Component X dataset.

Reads training CSVs downloaded from https://doi.org/10.5878/jvb5-d390 and
generates a compact JSON summary that we can bundle with the project.

Usage:
  python scripts/scania_component_x_summary.py --src /path/to/data \
        --out data/scania_component_x_summary.json
"""
from __future__ import annotations

import argparse
import csv
import json
from collections import defaultdict
from pathlib import Path
from statistics import mean, median
from typing import Dict, Iterable, Tuple


def read_train_tte(path: Path) -> Dict[str, Dict[str, float]]:
    """Return dict keyed by vehicle_id with length and repair flag."""
    info: Dict[str, Dict[str, float]] = {}
    with path.open(newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            vehicle_id = row["vehicle_id"]
            length = float(row["length_of_study_time_step"])
            repair = int(float(row["in_study_repair"]))
            info[vehicle_id] = {
                "length_of_study": length,
                "repaired": repair,
            }
    return info


def read_specifications(path: Path) -> Dict[str, Dict[str, str]]:
    """Return dict keyed by vehicle_id with specification fields."""
    specs: Dict[str, Dict[str, str]] = {}
    with path.open(newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            vehicle_id = row["vehicle_id"]
            specs[vehicle_id] = {k: v for k, v in row.items() if k != "vehicle_id"}
    return specs


def read_max_time_steps(path: Path) -> Dict[str, float]:
    """Return maximum observed time_step per vehicle."""
    maxima: Dict[str, float] = {}
    with path.open(newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            vid = row["vehicle_id"]
            time_step = float(row["time_step"])
            current = maxima.get(vid)
            if current is None or time_step > current:
                maxima[vid] = time_step
    return maxima


def aggregate_by_spec(
    records: Iterable[Tuple[str, Dict[str, str], Dict[str, float], float]]
) -> Dict[str, Dict[str, float]]:
    """
    Aggregate statistics per Spec_0 category (vehicle archetype).

    Returns mapping spec_value -> summary stats.
    """
    by_spec: Dict[str, Dict[str, list]] = defaultdict(lambda: defaultdict(list))

    for vid, spec, tte, max_time in records:
        spec_value = spec.get("Spec_0", "unknown")
        bucket = by_spec[spec_value]
        bucket["length_of_study"].append(tte["length_of_study"])
        bucket["repaired"].append(tte["repaired"])
        bucket["max_time_step"].append(max_time)

    summary: Dict[str, Dict[str, float]] = {}
    for spec_value, values in by_spec.items():
        repaired_flags = values["repaired"]
        lengths = values["length_of_study"]
        max_times = values["max_time_step"]
        if not lengths:
            continue
        summary[spec_value] = {
            "vehicles": len(lengths),
            "repairs": int(sum(repaired_flags)),
            "repair_rate": sum(repaired_flags) / len(repaired_flags),
            "median_length_of_study": median(lengths),
            "mean_length_of_study": mean(lengths),
            "median_max_time_step": median(max_times),
            "mean_max_time_step": mean(max_times),
        }
    return summary


def build_summary(src: Path) -> Dict[str, object]:
    """Produce final summary from dataset directory."""
    train_tte = read_train_tte(src / "train_tte.csv")
    specs = read_specifications(src / "train_specifications.csv")
    max_time = read_max_time_steps(src / "train_operational_readouts.csv")

    records = []
    for vid, tte in train_tte.items():
        spec = specs.get(vid, {})
        max_ts = max_time.get(vid, 0.0)
        records.append((vid, spec, tte, max_ts))

    overall_lengths = [r[2]["length_of_study"] for r in records]
    overall_repairs = [r[2]["repaired"] for r in records]
    overall_max_times = [r[3] for r in records]

    summary = {
        "dataset": "SCANIA Component X",
        "source": "https://doi.org/10.5878/jvb5-d390",
        "training_rows": len(overall_lengths),
        "training_repairs": int(sum(overall_repairs)),
        "training_repair_rate": sum(overall_repairs) / len(overall_repairs),
        "length_of_study": {
            "median": median(overall_lengths),
            "mean": mean(overall_lengths),
            "min": min(overall_lengths),
            "max": max(overall_lengths),
        },
        "max_time_step": {
            "median": median(overall_max_times),
            "mean": mean(overall_max_times),
            "min": min(overall_max_times),
            "max": max(overall_max_times),
        },
        "by_spec_0": aggregate_by_spec(records),
    }
    return summary


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--src",
        required=True,
        help="Path to dataset data directory (contains train_*.csv files).",
    )
    parser.add_argument(
        "--out",
        required=True,
        help="Output JSON path (will be overwritten).",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    src = Path(args.src).expanduser().resolve()
    out = Path(args.out).expanduser().resolve()

    if not src.exists():
        raise SystemExit(f"Dataset directory not found: {src}")

    summary = build_summary(src)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(summary, indent=2))
    print(f"Wrote summary to {out}")


if __name__ == "__main__":
    main()
