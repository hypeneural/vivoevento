from __future__ import annotations

import argparse
import json
import re
import shutil
import zipfile
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import gdown
import numpy as np
import requests

WIDER_URLS = {
    "train": "https://drive.google.com/uc?export=download&id=15hGDLhsx8bLgLcIRD5DhYt5iBxnjNF1M",
    "validation": "https://drive.google.com/uc?export=download&id=1GUCogbp16PMGa39thoMMeWxp7Rp5oM8Q",
    "test": "https://drive.google.com/uc?export=download&id=1HIfDbVEWKmsYKJZm4lchTBDLW5N7dY5T",
    "annotations": "http://shuoyang1213.me/WIDERFACE/support/bbx_annotation/wider_face_split.zip",
}

ARCHIVE_NAMES = {
    "train": "wider_train.zip",
    "validation": "wider_val.zip",
    "test": "wider_test.zip",
    "annotations": "wider_annotations.zip",
}

ANNOTATION_FILES = {
    "train": "wider_face_split/wider_face_train_bbx_gt.txt",
    "validation": "wider_face_split/wider_face_val_bbx_gt.txt",
    "test": "wider_face_split/wider_face_test_filelist.txt",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export WIDER FACE slices to a reusable FaceSearch manifest.")
    parser.add_argument("--cache-dir", required=True)
    parser.add_argument("--splits", default="validation")
    parser.add_argument("--selection", choices=["dense_annotations", "smallest_face", "sequential"], default="dense_annotations")
    parser.add_argument("--limit", type=int, default=50)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def normalize_splits(raw_value: str) -> list[str]:
    splits: list[str] = []

    for token in raw_value.split(","):
        value = token.strip().lower()

        if not value:
            continue

        if value == "all":
            splits.extend(["train", "validation", "test"])
        elif value == "val":
            splits.append("validation")
        elif value in {"train", "validation", "test"}:
            splits.append(value)

    if not splits:
        return ["validation"]

    seen: set[str] = set()
    normalized: list[str] = []

    for split in splits:
        if split in seen:
            continue
        seen.add(split)
        normalized.append(split)

    return normalized


def ensure_archive(key: str, download_dir: Path) -> Path:
    download_dir.mkdir(parents=True, exist_ok=True)
    archive_path = download_dir / ARCHIVE_NAMES[key]

    if archive_path.is_file():
        return archive_path

    url = WIDER_URLS[key]

    if "drive.google.com" in url:
        result = gdown.download(url=url, output=str(archive_path), quiet=False)

        if result is None or not archive_path.is_file():
            raise RuntimeError(f"Failed to download official WIDER FACE archive for {key}.")
    else:
        response = requests.get(url, stream=True, timeout=300)
        response.raise_for_status()

        with archive_path.open("wb") as file_handle:
            for chunk in response.iter_content(chunk_size=1024 * 1024):
                if chunk:
                    file_handle.write(chunk)

    return archive_path


def ensure_extracted(key: str, archive_path: Path, extracted_dir: Path) -> Path:
    target_dir = extracted_dir / key

    if target_dir.is_dir():
        return target_dir

    target_dir.mkdir(parents=True, exist_ok=True)

    with zipfile.ZipFile(archive_path, "r") as zip_handle:
        zip_handle.extractall(target_dir)

    return target_dir


def annotation_path(extracted_annotations_dir: Path, split: str) -> Path:
    return extracted_annotations_dir / ANNOTATION_FILES[split]


def image_root(extracted_split_dir: Path, split: str) -> Path:
    folder = {
        "train": "WIDER_train/images",
        "validation": "WIDER_val/images",
        "test": "WIDER_test/images",
    }[split]

    return extracted_split_dir / folder


def percentile(values: list[float], value: int) -> float | None:
    if not values:
        return None

    return round(float(np.percentile(np.asarray(values, dtype=float), value)), 4)


def parse_annotations(split: str, annotation_file: Path) -> list[dict[str, Any]]:
    lines = annotation_file.read_text(encoding="utf-8").splitlines()
    index = 0
    items: list[dict[str, Any]] = []
    pattern = re.compile(r"\s+")

    while index < len(lines):
        filename = lines[index].strip()
        index += 1

        if not filename:
            continue

        if split == "test":
            items.append({
                "split": split,
                "filename": filename,
                "annotations": [],
                "annotation_count": 0,
                "valid_annotation_count": 0,
                "face_span_min_px_min": None,
            })
            continue

        annotation_count = int(lines[index].strip())
        index += 1
        annotations: list[dict[str, Any]] = []

        if annotation_count == 0:
            index += 1
        else:
            for _ in range(annotation_count):
                parts = pattern.split(lines[index].strip())
                index += 1

                if len(parts) < 10:
                    raise RuntimeError(f"Invalid WIDER FACE annotation row for {filename}.")

                xmin, ymin, width, height, blur, expression, illumination, invalid, occlusion, pose = map(int, parts[:10])

                if width <= 0 or height <= 0:
                    continue

                annotations.append({
                    "bbox": {
                        "x": xmin,
                        "y": ymin,
                        "width": width,
                        "height": height,
                    },
                    "face_span_min_px": min(width, height),
                    "blur": blur,
                    "expression": bool(expression),
                    "illumination": bool(illumination),
                    "invalid": bool(invalid),
                    "occlusion": occlusion,
                    "pose": bool(pose),
                })

        valid_annotations = [annotation for annotation in annotations if not annotation["invalid"]]
        face_spans = [float(annotation["face_span_min_px"]) for annotation in valid_annotations]

        items.append({
            "split": split,
            "filename": filename,
            "annotations": annotations,
            "annotation_count": len(annotations),
            "valid_annotation_count": len(valid_annotations),
            "face_span_min_px_min": percentile(face_spans, 0),
        })

    return items


def select_items(items: list[dict[str, Any]], selection: str, limit: int) -> list[dict[str, Any]]:
    if selection == "dense_annotations":
        items = sorted(
            items,
            key=lambda item: (
                -int(item["valid_annotation_count"]),
                float(item["face_span_min_px_min"] or 10**9),
                str(item["filename"]),
            ),
        )
    elif selection == "smallest_face":
        items = sorted(
            items,
            key=lambda item: (
                float(item["face_span_min_px_min"] or 10**9),
                -int(item["valid_annotation_count"]),
                str(item["filename"]),
            ),
        )
    else:
        items = sorted(items, key=lambda item: str(item["filename"]))

    return items[: max(1, limit)]


def scene_type(annotation_count: int) -> str:
    if annotation_count >= 11:
        return "crowd_dense"
    if annotation_count >= 6:
        return "group_dense"
    if annotation_count >= 2:
        return "group_small"
    return "portrait"


def quality_label(min_face_span: float | None) -> str:
    if min_face_span is None:
        return "unknown"
    if min_face_span < 32:
        return "small_face"
    if min_face_span < 64:
        return "mixed"
    return "good"


def export_items(selected_items: list[dict[str, Any]], extracted_roots: dict[str, Path], output_dir: Path) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []

    for index, item in enumerate(selected_items, start=1):
        split = str(item["split"])
        source_image = image_root(extracted_roots[split], split) / str(item["filename"])

        if not source_image.is_file():
            raise FileNotFoundError(f"WIDER FACE image not found: {source_image}")

        relative_path = Path("images") / split / Path(str(item["filename"]))
        absolute_path = output_dir / relative_path
        absolute_path.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source_image, absolute_path)

        valid_annotations = [annotation for annotation in item["annotations"] if not annotation["invalid"]]
        face_spans = [float(annotation["face_span_min_px"]) for annotation in valid_annotations]

        entries.append({
            "id": f"wider-face-{split}-{index:04d}",
            "dataset": "wider_face",
            "split": split,
            "relative_path": relative_path.as_posix(),
            "scene_type": scene_type(len(valid_annotations)),
            "quality_label": quality_label(percentile(face_spans, 0)),
            "event_class": Path(str(item["filename"])).parts[0] if Path(str(item["filename"])).parts else "unknown",
            "annotation_count": len(item["annotations"]),
            "valid_annotation_count": len(valid_annotations),
            "invalid_annotation_count": len(item["annotations"]) - len(valid_annotations),
            "face_span_min_px_min": percentile(face_spans, 0),
            "face_span_min_px_p50": percentile(face_spans, 50),
            "face_span_min_px_p95": percentile(face_spans, 95),
            "face_span_min_px_max": percentile(face_spans, 100),
            "expected_moderation_bucket": "safe",
            "is_public_search_eligible": False,
            "expected_positive_set": [],
            "annotations": item["annotations"],
        })

    return entries


def main() -> int:
    args = parse_args()
    cache_dir = Path(args.cache_dir).expanduser()
    output_dir = Path(args.output_dir).expanduser()
    splits = normalize_splits(args.splits)
    selection = args.selection
    limit = max(1, int(args.limit))
    download_dir = cache_dir / "downloads"
    extracted_dir = cache_dir / "extracted"
    output_dir.mkdir(parents=True, exist_ok=True)

    archives: dict[str, Path] = {}
    extracted_roots: dict[str, Path] = {}

    archives["annotations"] = ensure_archive("annotations", download_dir)
    extracted_roots["annotations"] = ensure_extracted("annotations", archives["annotations"], extracted_dir)

    for split in splits:
        archives[split] = ensure_archive(split, download_dir)
        extracted_roots[split] = ensure_extracted(split, archives[split], extracted_dir)

    items: list[dict[str, Any]] = []

    for split in splits:
        items.extend(parse_annotations(split, annotation_path(extracted_roots["annotations"], split)))

    selected_items = select_items(items, selection, limit)
    entries = export_items(selected_items, extracted_roots, output_dir)

    face_spans = [float(entry["face_span_min_px_min"]) for entry in entries if entry["face_span_min_px_min"] is not None]
    annotation_counts = [int(entry["valid_annotation_count"]) for entry in entries]
    summary = {
        "entries_exported": len(entries),
        "split_counts": dict(Counter(str(entry["split"]) for entry in entries)),
        "valid_annotations_total": int(sum(annotation_counts)),
        "invalid_annotations_total": int(sum(int(entry["invalid_annotation_count"]) for entry in entries)),
        "p50_valid_annotations_per_image": percentile([float(value) for value in annotation_counts], 50),
        "p95_valid_annotations_per_image": percentile([float(value) for value in annotation_counts], 95),
        "p50_face_span_min_px": percentile(face_spans, 50),
        "p95_face_span_min_px": percentile(face_spans, 95),
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_detection_dataset_v1",
        "dataset": "wider_face",
        "lane": "detection_small_faces_occlusion",
        "splits": splits,
        "selection": selection,
        "source_cache_dir": str(cache_dir),
        "output_dir": str(output_dir),
        "privacy": {
            "raw_customer_uploads_allowed": False,
            "source_is_public_research_dataset": True,
            "secrets_allowed": False,
        },
        "summary": summary,
        "entries": entries,
        "notes": [
            "Source archives were obtained from the official WIDER FACE IDs embedded in the maintained TFDS builder.",
            "Google Drive confirmation can break TFDS direct download on some environments; this exporter uses gdown with the same official IDs.",
            "This manifest is intended for detection, density, and small-face calibration, not identity matching.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "wider_face",
        "splits": splits,
        "selection": selection,
        "cache_dir": str(cache_dir),
        "output_dir": str(output_dir),
        "manifest_path": str(manifest_path),
        "summary": summary,
        "sample_entry_ids": [str(entry["id"]) for entry in entries[:10]],
        "request_outcome": "success",
    }

    report_path.write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=True))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
