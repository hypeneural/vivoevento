from __future__ import annotations

import argparse
import json
import math
import shutil
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export Caltech WebFaces slices to a reusable FaceSearch manifest.")
    parser.add_argument("--dataset-root", required=True)
    parser.add_argument("--ground-truth", required=True)
    parser.add_argument(
        "--selection",
        choices=["sequential", "smallest_annotated_faces", "multi_face_density"],
        default="sequential",
    )
    parser.add_argument("--limit", type=int, default=100)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def percentile(values: list[float], pct: int) -> float | None:
    if not values:
        return None

    ordered = sorted(values)
    position = (max(0, min(100, pct)) / 100.0) * (len(ordered) - 1)
    lower = int(math.floor(position))
    upper = int(math.ceil(position))

    if lower == upper:
        return round(float(ordered[lower]), 4)

    weight = position - lower
    interpolated = ordered[lower] + ((ordered[upper] - ordered[lower]) * weight)

    return round(float(interpolated), 4)


def estimate_bbox(points: list[tuple[float, float]]) -> dict[str, int]:
    left_eye, right_eye, nose, mouth = points
    xs = [point[0] for point in points]
    ys = [point[1] for point in points]
    min_x = min(xs)
    max_x = max(xs)
    min_y = min(ys)
    max_y = max(ys)
    horizontal_span = max(1.0, max_x - min_x)
    vertical_span = max(1.0, max_y - min_y)
    interocular = max(1.0, abs(right_eye[0] - left_eye[0]))
    eye_y = (left_eye[1] + right_eye[1]) / 2.0
    eye_to_mouth = max(1.0, mouth[1] - eye_y)
    center_x = sum(xs) / len(xs)
    box_width = max(horizontal_span * 2.4, interocular * 2.2, eye_to_mouth * 1.8)
    box_height = max(vertical_span * 2.8, eye_to_mouth * 2.6, box_width * 1.1)
    x = max(0, int(round(center_x - (box_width / 2.0))))
    y = max(0, int(round(eye_y - (box_height * 0.38))))

    return {
        "x": x,
        "y": y,
        "width": max(1, int(round(box_width))),
        "height": max(1, int(round(box_height))),
    }


def parse_ground_truth(ground_truth_path: Path) -> list[dict[str, Any]]:
    grouped: dict[str, list[dict[str, Any]]] = {}

    for line in ground_truth_path.read_text(encoding="utf-8").splitlines():
        parts = line.strip().split()

        if len(parts) != 9:
            continue

        image_name = parts[0]
        left_eye = (float(parts[1]), float(parts[2]))
        right_eye = (float(parts[3]), float(parts[4]))
        nose = (float(parts[5]), float(parts[6]))
        mouth = (float(parts[7]), float(parts[8]))
        points = [left_eye, right_eye, nose, mouth]
        xs = [point[0] for point in points]
        ys = [point[1] for point in points]
        face_span = min(max(xs) - min(xs), max(ys) - min(ys))

        grouped.setdefault(image_name, []).append(
            {
                "bbox": estimate_bbox(points),
                "face_span_min_px": round(float(face_span), 2),
                "invalid": False,
                "landmarks": [
                    {"name": "left_eye", "x": int(round(left_eye[0])), "y": int(round(left_eye[1]))},
                    {"name": "right_eye", "x": int(round(right_eye[0])), "y": int(round(right_eye[1]))},
                    {"name": "nose", "x": int(round(nose[0])), "y": int(round(nose[1]))},
                    {"name": "mouth", "x": int(round(mouth[0])), "y": int(round(mouth[1]))},
                ],
                "bbox_estimation_method": "landmark_envelope_v1",
            }
        )

    items: list[dict[str, Any]] = []

    for image_name, annotations in grouped.items():
        spans = [float(annotation["face_span_min_px"]) for annotation in annotations]
        items.append(
            {
                "image_name": image_name,
                "annotated_faces_count": len(annotations),
                "estimated_annotated_face_span_min_px": percentile(spans, 0),
                "estimated_annotated_face_span_p50_px": percentile(spans, 50),
                "estimated_annotated_face_span_p95_px": percentile(spans, 95),
                "estimated_annotated_face_span_max_px": percentile(spans, 100),
                "annotations": annotations,
            }
        )

    return items


def select_items(items: list[dict[str, Any]], selection: str, limit: int) -> list[dict[str, Any]]:
    if selection == "smallest_annotated_faces":
        items = sorted(
            items,
            key=lambda item: (
                float(item["estimated_annotated_face_span_min_px"] or 10**9),
                -int(item["annotated_faces_count"]),
                str(item["image_name"]),
            ),
        )
    elif selection == "multi_face_density":
        items = sorted(
            items,
            key=lambda item: (
                -int(item["annotated_faces_count"]),
                float(item["estimated_annotated_face_span_min_px"] or 10**9),
                str(item["image_name"]),
            ),
        )
    else:
        items = sorted(items, key=lambda item: str(item["image_name"]))

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


def export_items(selected_items: list[dict[str, Any]], dataset_root: Path, output_dir: Path) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []

    for index, item in enumerate(selected_items, start=1):
        source_image = dataset_root / str(item["image_name"])

        if not source_image.is_file():
            raise FileNotFoundError(f"Caltech WebFaces image not found: {source_image}")

        relative_path = Path("images") / Path(str(item["image_name"]))
        absolute_path = output_dir / relative_path
        absolute_path.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source_image, absolute_path)

        spans = [float(annotation["face_span_min_px"]) for annotation in item["annotations"]]

        entries.append(
            {
                "id": f"caltech-webfaces-{index:04d}",
                "dataset": "caltech_webfaces",
                "split": "all",
                "relative_path": relative_path.as_posix(),
                "scene_type": scene_type(int(item["annotated_faces_count"])),
                "quality_label": quality_label(percentile(spans, 0)),
                "annotation_count": int(item["annotated_faces_count"]),
                "valid_annotation_count": int(item["annotated_faces_count"]),
                "invalid_annotation_count": 0,
                "face_span_min_px_min": percentile(spans, 0),
                "face_span_min_px_p50": percentile(spans, 50),
                "face_span_min_px_p95": percentile(spans, 95),
                "face_span_min_px_max": percentile(spans, 100),
                "expected_moderation_bucket": "safe",
                "is_public_search_eligible": False,
                "expected_positive_set": [],
                "annotations": item["annotations"],
            }
        )

    return entries


def main() -> int:
    args = parse_args()
    dataset_root = Path(args.dataset_root).expanduser()
    ground_truth_path = Path(args.ground_truth).expanduser()
    output_dir = Path(args.output_dir).expanduser()
    output_dir.mkdir(parents=True, exist_ok=True)

    items = parse_ground_truth(ground_truth_path)
    selected_items = select_items(items, args.selection, max(1, int(args.limit)))
    entries = export_items(selected_items, dataset_root, output_dir)
    annotation_counts = [int(entry["annotation_count"]) for entry in entries]
    face_spans = [float(entry["face_span_min_px_min"]) for entry in entries if entry["face_span_min_px_min"] is not None]

    summary = {
        "entries_exported": len(entries),
        "annotations_total": int(sum(annotation_counts)),
        "p50_annotations_per_image": percentile([float(value) for value in annotation_counts], 50),
        "p95_annotations_per_image": percentile([float(value) for value in annotation_counts], 95),
        "p50_face_span_min_px": percentile(face_spans, 50),
        "p95_face_span_min_px": percentile(face_spans, 95),
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_detection_dataset_v1",
        "dataset": "caltech_webfaces",
        "lane": "detection_small_faces_negatives",
        "selection": args.selection,
        "source_dataset_root": str(dataset_root),
        "source_ground_truth_path": str(ground_truth_path),
        "output_dir": str(output_dir),
        "privacy": {
            "raw_customer_uploads_allowed": False,
            "source_is_public_research_dataset": True,
            "secrets_allowed": False,
        },
        "summary": summary,
        "entries": entries,
        "notes": [
            "Annotations come from WebFaces_GroundThruth.txt and expose eyes, nose, and mouth center landmarks.",
            "Bounding boxes are inferred locally via landmark_envelope_v1 to enable approximate IoU-based detection probes.",
            "This manifest is intended for detection, density, and small-face calibration, not identity matching.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "caltech_webfaces",
        "selection": args.selection,
        "dataset_root": str(dataset_root),
        "ground_truth_path": str(ground_truth_path),
        "output_dir": str(output_dir),
        "manifest_path": str(manifest_path),
        "summary": summary,
        "annotation_bbox_estimation_method": "landmark_envelope_v1",
        "sample_entry_ids": [str(entry["id"]) for entry in entries[:10]],
        "scene_type_counts": dict(Counter(str(entry["scene_type"]) for entry in entries)),
        "quality_label_counts": dict(Counter(str(entry["quality_label"]) for entry in entries)),
        "request_outcome": "success",
    }

    report_path.write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=True))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
