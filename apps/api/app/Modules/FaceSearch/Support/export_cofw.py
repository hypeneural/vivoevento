from __future__ import annotations

import argparse
import json
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import h5py
import numpy as np
from PIL import Image

LFPW_TRAIN_COUNT = 845
LANDMARK_COUNT = 29


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export local COFW MAT files into image assets and metadata.")
    parser.add_argument("--variant", choices=["color", "gray"], default="color")
    parser.add_argument("--root", required=True)
    parser.add_argument("--splits", default="train,test")
    parser.add_argument("--include-lfpw", default="0")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def normalize_splits(raw_value: str) -> list[str]:
    splits: list[str] = []

    for token in raw_value.split(","):
        value = token.strip().lower()

        if not value:
            continue

        if value == "all":
            splits.extend(["train", "test"])
            continue

        if value in {"train", "test"}:
            splits.append(value)

    if not splits:
        return ["train", "test"]

    seen: set[str] = set()
    normalized: list[str] = []

    for split in splits:
        if split in seen:
            continue

        seen.add(split)
        normalized.append(split)

    return normalized


def mat_path(root: Path, variant: str, split: str) -> Path:
    if variant == "color":
        return root / ("COFW_train_color.mat" if split == "train" else "COFW_test_color.mat")

    return root / ("COFW_train.mat" if split == "train" else "COFW_test.mat")


def dataset_keys(split: str) -> tuple[str, str, str]:
    if split == "train":
        return ("IsTr", "phisTr", "bboxesTr")

    return ("IsT", "phisT", "bboxesT")


def normalize_image(array: np.ndarray, variant: str) -> np.ndarray:
    image = np.asarray(array)

    if image.ndim == 3 and image.shape[0] in {3, 4}:
        image = np.transpose(image, (1, 2, 0))

    image = np.clip(image, 0, 255).astype(np.uint8)

    if variant == "gray" and image.ndim == 3:
        image = image[:, :, 0]

    return image


def percentile(values: list[float], value: int) -> float | None:
    if not values:
        return None

    return round(float(np.percentile(np.asarray(values, dtype=float), value)), 4)


def build_entry(
    variant: str,
    split: str,
    source_index: int,
    source_subset: str,
    relative_path: Path,
    bbox: np.ndarray,
    phis: np.ndarray,
) -> dict[str, Any]:
    xs = phis[:LANDMARK_COUNT]
    ys = phis[LANDMARK_COUNT : LANDMARK_COUNT * 2]
    occlusion_bits = phis[LANDMARK_COUNT * 2 : LANDMARK_COUNT * 3]
    occluded_landmarks = int(np.sum(occlusion_bits >= 0.5))
    occlusion_rate = round(occluded_landmarks / LANDMARK_COUNT, 4)
    face_span_min = round(float(min(bbox[2], bbox[3])), 2)
    bbox_values = [round(float(value), 2) for value in bbox.tolist()]
    source_key = source_subset.replace("_", "-")

    return {
        "id": f"cofw-{variant}-{source_key}-{source_index:04d}",
        "dataset": "cofw",
        "variant": variant,
        "split": split,
        "source_subset": source_subset,
        "source_index": source_index,
        "relative_path": relative_path.as_posix(),
        "bbox": {
            "x": bbox_values[0],
            "y": bbox_values[1],
            "width": bbox_values[2],
            "height": bbox_values[3],
        },
        "face_span_min_px": face_span_min,
        "landmark_count": LANDMARK_COUNT,
        "occluded_landmarks_count": occluded_landmarks,
        "occlusion_rate": occlusion_rate,
        "quality_label": "occluded" if occluded_landmarks > 0 else "good",
        "scene_type": "portrait_occluded" if occluded_landmarks > 0 else "portrait_clear",
        "expected_moderation_bucket": "safe",
        "is_public_search_eligible": False,
        "expected_positive_set": [],
        "landmark_bounds": {
            "x_min": round(float(np.min(xs)), 2),
            "x_max": round(float(np.max(xs)), 2),
            "y_min": round(float(np.min(ys)), 2),
            "y_max": round(float(np.max(ys)), 2),
        },
    }


def export_split(
    root: Path,
    output_dir: Path,
    variant: str,
    split: str,
    include_lfpw: bool,
    remaining_limit: int | None,
) -> list[dict[str, Any]]:
    file_path = mat_path(root, variant, split)

    if not file_path.is_file():
        raise FileNotFoundError(f"COFW file not found: {file_path}")

    image_key, phis_key, bbox_key = dataset_keys(split)
    entries: list[dict[str, Any]] = []

    with h5py.File(file_path, "r") as handle:
        images = handle[image_key]
        phis = handle[phis_key]
        bboxes = handle[bbox_key]

        total = int(images.shape[1])

        for index in range(total):
            source_index = index + 1
            source_subset = "cofw_test"

            if split == "train":
                source_subset = "lfpw_train" if source_index <= LFPW_TRAIN_COUNT else "cofw_train"

                if source_subset == "lfpw_train" and not include_lfpw:
                    continue

            ref = images[0, index]
            image_array = normalize_image(np.asarray(handle[ref]), variant)
            file_name = f"cofw-{variant}-{source_subset.replace('_', '-')}-{source_index:04d}.png"
            relative_path = Path("images") / split / file_name
            absolute_path = output_dir / relative_path
            absolute_path.parent.mkdir(parents=True, exist_ok=True)
            Image.fromarray(image_array).save(absolute_path)

            entries.append(
                build_entry(
                    variant=variant,
                    split=split,
                    source_index=source_index,
                    source_subset=source_subset,
                    relative_path=relative_path,
                    bbox=np.asarray(bboxes[:, index], dtype=float),
                    phis=np.asarray(phis[:, index], dtype=float),
                )
            )

            if remaining_limit is not None and len(entries) >= remaining_limit:
                break

    return entries


def main() -> int:
    args = parse_args()
    root = Path(args.root).expanduser()
    output_dir = Path(args.output_dir).expanduser()
    output_dir.mkdir(parents=True, exist_ok=True)
    splits = normalize_splits(args.splits)
    include_lfpw = str(args.include_lfpw).strip().lower() in {"1", "true", "yes", "on"}
    limit = max(0, int(args.limit))

    entries: list[dict[str, Any]] = []

    for split in splits:
        remaining_limit = None

        if limit > 0:
            remaining = limit - len(entries)

            if remaining <= 0:
                break

            remaining_limit = remaining

        entries.extend(
            export_split(
                root=root,
                output_dir=output_dir,
                variant=args.variant,
                split=split,
                include_lfpw=include_lfpw,
                remaining_limit=remaining_limit,
            )
        )

    occlusion_rates = [float(entry["occlusion_rate"]) for entry in entries]
    face_spans = [float(entry["face_span_min_px"]) for entry in entries]
    split_counts = Counter(str(entry["split"]) for entry in entries)
    subset_counts = Counter(str(entry["source_subset"]) for entry in entries)
    summary = {
        "entries_exported": len(entries),
        "split_counts": dict(split_counts),
        "source_subset_counts": dict(subset_counts),
        "occluded_entries": sum(1 for entry in entries if int(entry["occluded_landmarks_count"]) > 0),
        "p50_occlusion_rate": percentile(occlusion_rates, 50),
        "p95_occlusion_rate": percentile(occlusion_rates, 95),
        "p50_face_span_min_px": percentile(face_spans, 50),
        "p95_face_span_min_px": percentile(face_spans, 95),
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_detection_dataset_v1",
        "dataset": "cofw",
        "lane": "detection_occlusion",
        "variant": args.variant,
        "splits": splits,
        "include_lfpw": include_lfpw,
        "source_root": str(root),
        "output_dir": str(output_dir),
        "privacy": {
            "raw_customer_uploads_allowed": False,
            "source_is_public_research_dataset": True,
            "secrets_allowed": False,
        },
        "summary": summary,
        "entries": entries,
        "notes": [
            "The first 845 training images belong to LFPW; include_lfpw=false exports only the 500 COFW train images plus the 507 COFW test images.",
            "Bounding boxes come from the source dataset and are documented as an imperfect detector simulation.",
            "This manifest is intended for detection, occlusion, and quality-gate calibration, not identity matching.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"

    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "cofw",
        "variant": args.variant,
        "splits": splits,
        "include_lfpw": include_lfpw,
        "source_root": str(root),
        "output_dir": str(output_dir),
        "manifest_path": str(manifest_path),
        "summary": summary,
        "sample_entry_ids": [str(entry["id"]) for entry in entries[:10]],
        "request_outcome": "success",
    }

    report_path.write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding="utf-8")
    sys.stdout.write(json.dumps(report, ensure_ascii=True))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
