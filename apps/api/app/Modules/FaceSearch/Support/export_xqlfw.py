from __future__ import annotations

import argparse
import json
import re
import shutil
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export XQLFW into a reusable FaceSearch identity manifest.")
    parser.add_argument("--variant", choices=["original", "aligned_112"], default="original")
    parser.add_argument("--root", required=True)
    parser.add_argument("--scores-path", required=True)
    parser.add_argument("--pairs-path", default="")
    parser.add_argument("--selection", choices=["official_pairs", "highest_quality", "sequential"], default="official_pairs")
    parser.add_argument("--image-selection", choices=["score_spread", "top_score", "sequential"], default="score_spread")
    parser.add_argument("--offset", type=int, default=0)
    parser.add_argument("--people", type=int, default=12)
    parser.add_argument("--images-per-person", type=int, default=4)
    parser.add_argument("--min-images-per-person", type=int, default=2)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def percentile(values: list[float], pct: int) -> float | None:
    if not values:
        return None

    ordered = sorted(values)
    if len(ordered) == 1:
        return round(float(ordered[0]), 4)

    rank = (pct / 100.0) * (len(ordered) - 1)
    lower = int(rank)
    upper = min(lower + 1, len(ordered) - 1)
    weight = rank - lower

    return round(float(ordered[lower] * (1.0 - weight) + ordered[upper] * weight), 4)


def parse_scores(scores_path: Path) -> dict[tuple[str, str], float]:
    scores: dict[tuple[str, str], float] = {}
    lines = scores_path.read_text(encoding="utf-8").splitlines()

    for line in lines[1:]:
        line = line.strip()

        if not line:
            continue

        parts = re.split(r"\s+", line)

        if len(parts) < 3:
            continue

        person_id = parts[0]

        try:
            image_number = int(parts[1])
            score = float(parts[2])
        except ValueError:
            continue

        filename = f"{person_id}_{image_number:04d}.jpg"
        scores[(person_id, filename)] = score

    return scores


def parse_official_pairs(pairs_path: Path | None) -> set[str]:
    if pairs_path is None or not pairs_path.is_file():
        return set()

    identities: set[str] = set()
    lines = pairs_path.read_text(encoding="utf-8").splitlines()

    for index, line in enumerate(lines):
        line = line.strip()

        if not line:
            continue

        if index == 0:
            continue

        parts = re.split(r"\s+", line)

        if len(parts) == 3:
            identities.add(parts[0])
        elif len(parts) >= 4:
            identities.add(parts[0])
            identities.add(parts[2])

    return identities


def quality_label(score: float | None) -> str:
    if score is None:
        return "unknown"
    if score >= 0.75:
        return "good"
    if score >= 0.55:
        return "mixed"
    return "low_quality"


def slug_token(value: str) -> str:
    normalized = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")

    return normalized or "unknown"


def load_identities(root: Path, scores: dict[tuple[str, str], float], official_pairs: set[str], min_images_per_person: int) -> list[dict[str, Any]]:
    identities: list[dict[str, Any]] = []

    for person_dir in sorted(root.iterdir(), key=lambda item: item.name.lower()):
        if not person_dir.is_dir():
            continue

        images: list[dict[str, Any]] = []

        for image_path in sorted(person_dir.iterdir(), key=lambda item: item.name.lower()):
            if not image_path.is_file():
                continue

            if image_path.suffix.lower() not in {".jpg", ".jpeg", ".png"}:
                continue

            score = scores.get((person_dir.name, image_path.name))
            images.append({
                "filename": image_path.name,
                "path": image_path,
                "score": score,
                "size_bytes": image_path.stat().st_size,
            })

        if len(images) < min_images_per_person:
            continue

        scored_values = [float(image["score"]) for image in images if image["score"] is not None]

        identities.append({
            "person_id": person_dir.name,
            "images": images,
            "image_count": len(images),
            "images_with_score": len(scored_values),
            "mean_score": round(sum(scored_values) / len(scored_values), 4) if scored_values else None,
            "min_score": round(min(scored_values), 4) if scored_values else None,
            "max_score": round(max(scored_values), 4) if scored_values else None,
            "is_in_official_pairs": person_dir.name in official_pairs,
        })

    return identities


def sort_identities(identities: list[dict[str, Any]], selection: str) -> list[dict[str, Any]]:
    def score_value(value: float | None) -> float:
        return value if value is not None else -1.0

    if selection == "highest_quality":
        return sorted(
            identities,
            key=lambda item: (
                -score_value(item["mean_score"]),
                -score_value(item["max_score"]),
                -int(item["image_count"]),
                str(item["person_id"]).lower(),
            ),
        )

    if selection == "sequential":
        return sorted(identities, key=lambda item: str(item["person_id"]).lower())

    return sorted(
        identities,
        key=lambda item: (
            0 if item["is_in_official_pairs"] else 1,
            -int(item["images_with_score"]),
            -score_value(item["mean_score"]),
            -int(item["image_count"]),
            str(item["person_id"]).lower(),
        ),
    )


def sort_images(images: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return sorted(
        images,
        key=lambda item: (
            1 if item["score"] is None else 0,
            -(float(item["score"]) if item["score"] is not None else -1.0),
            str(item["filename"]).lower(),
        ),
    )


def select_images(images: list[dict[str, Any]], strategy: str, limit: int) -> list[dict[str, Any]]:
    limit = max(2, limit)

    if strategy == "sequential":
        return sorted(images, key=lambda item: str(item["filename"]).lower())[:limit]

    ranked = sort_images(images)

    if strategy == "top_score":
        return ranked[:limit]

    selected: list[dict[str, Any]] = []
    used: set[str] = set()
    candidate_indexes = [
        0,
        len(ranked) - 1,
        len(ranked) // 2,
        max(0, len(ranked) // 3),
        min(len(ranked) - 1, (2 * len(ranked)) // 3),
    ]

    for index in candidate_indexes:
        if index < 0 or index >= len(ranked):
            continue

        candidate = ranked[index]
        filename = str(candidate["filename"])

        if filename in used:
            continue

        selected.append(candidate)
        used.add(filename)

        if len(selected) >= limit:
            break

    if len(selected) < limit:
        for candidate in ranked:
            filename = str(candidate["filename"])

            if filename in used:
                continue

            selected.append(candidate)
            used.add(filename)

            if len(selected) >= limit:
                break

    return sorted(selected, key=lambda item: str(item["filename"]).lower())[:limit]


def export_entries(
    selected_identities: list[dict[str, Any]],
    variant: str,
    image_selection: str,
    output_dir: Path,
) -> tuple[list[dict[str, Any]], dict[str, list[str]]]:
    entries: list[dict[str, Any]] = []
    ids_by_person: dict[str, list[str]] = {}

    for person in selected_identities:
        person_id = str(person["person_id"])
        person_slug = slug_token(person_id)
        selected_images = select_images(person["images"], image_selection, person["images_per_person"])

        if len(selected_images) < 2:
            continue

        ids_by_person[person_id] = []

        for index, image in enumerate(selected_images, start=1):
            source_path = Path(image["path"])
            relative_path = Path("images") / person_id / source_path.name
            target_path = output_dir / relative_path
            target_path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(source_path, target_path)

            entry_id = f"xqlfw-{variant}-{person_slug}-{index:02d}"
            ids_by_person[person_id].append(entry_id)

            entries.append({
                "id": entry_id,
                "dataset": "xqlfw",
                "variant": variant,
                "event_id": f"local-xqlfw-{variant}",
                "person_id": person_id,
                "relative_path": relative_path.as_posix(),
                "scene_type": "portrait_aligned" if variant == "aligned_112" else "single_prominent",
                "quality_label": quality_label(image["score"]),
                "xqlfw_quality_score": round(float(image["score"]), 4) if image["score"] is not None else None,
                "is_public_search_eligible": False,
                "expected_moderation_bucket": "safe",
                "consent_basis": "public_research_dataset_internal_calibration",
                "size_bytes": int(image["size_bytes"]),
                "requires_derivative_for_compreface": False,
                "target_face_selection": {
                    "strategy": "largest",
                    "value": 1,
                },
                "notes": "Exported from local XQLFW for internal identity calibration.",
            })

    for entry in entries:
        person_id = str(entry["person_id"])
        entry["expected_positive_set"] = list(ids_by_person.get(person_id, []))

    return entries, ids_by_person


def main() -> int:
    args = parse_args()
    variant = args.variant
    root = Path(args.root).expanduser()
    scores_path = Path(args.scores_path).expanduser()
    pairs_path = Path(args.pairs_path).expanduser() if args.pairs_path else None
    selection = args.selection
    image_selection = args.image_selection
    offset = max(0, int(args.offset))
    people_requested = max(2, int(args.people))
    images_per_person = max(2, int(args.images_per_person))
    min_images_per_person = max(2, int(args.min_images_per_person))
    output_dir = Path(args.output_dir).expanduser()
    output_dir.mkdir(parents=True, exist_ok=True)

    if not root.is_dir():
        raise FileNotFoundError(f"XQLFW root not found: {root}")

    if not scores_path.is_file():
        raise FileNotFoundError(f"XQLFW scores file not found: {scores_path}")

    if selection == "official_pairs" and (pairs_path is None or not pairs_path.is_file()):
        raise FileNotFoundError("XQLFW official-pairs selection requires a readable pairs file.")

    scores = parse_scores(scores_path)
    official_pairs = parse_official_pairs(pairs_path)
    identities = load_identities(root, scores, official_pairs, min_images_per_person)
    identities = sort_identities(identities, selection)

    selected_identities: list[dict[str, Any]] = []

    for identity in identities[offset:]:
        if len(selected_identities) >= people_requested:
            break

        identity["images_per_person"] = images_per_person
        selected_identities.append(identity)

    if len(selected_identities) < 2:
        raise RuntimeError("XQLFW export requires at least two identities with two images each after filtering.")

    entries, ids_by_person = export_entries(selected_identities, variant, image_selection, output_dir)

    if len(ids_by_person) < 2 or len(entries) < 4:
        raise RuntimeError("XQLFW export did not produce enough entries for FaceSearch smoke and benchmark.")

    quality_scores = [float(entry["xqlfw_quality_score"]) for entry in entries if entry["xqlfw_quality_score"] is not None]
    summary = {
        "entries_exported": len(entries),
        "people_selected": len(ids_by_person),
        "images_per_person_requested": images_per_person,
        "selection": selection,
        "image_selection": image_selection,
        "offset": offset,
        "official_pairs_identities_loaded": len(official_pairs),
        "score_records_loaded": len(scores),
        "quality_label_counts": dict(Counter(str(entry["quality_label"]) for entry in entries)),
        "p50_quality_score": percentile(quality_scores, 50),
        "p95_quality_score": percentile(quality_scores, 95),
        "min_quality_score": percentile(quality_scores, 0),
        "max_quality_score": percentile(quality_scores, 100),
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_identity_dataset_v1",
        "dataset": "xqlfw",
        "lane": "identity_threshold",
        "variant": variant,
        "selection": selection,
        "image_selection": image_selection,
        "offset": offset,
        "asset_root_env": "",
        "fallback_asset_root": str(output_dir),
        "source_root": str(root),
        "source_scores_path": str(scores_path),
        "source_pairs_path": str(pairs_path) if pairs_path is not None else None,
        "privacy": {
            "requires_explicit_consent": False,
            "raw_assets_versioned_in_repo": False,
            "source_is_public_research_dataset": True,
            "secrets_allowed": False,
        },
        "provider_constraints": {
            "compreface_max_file_size_bytes": 5242880,
            "allowed_extensions": ["jpg", "jpeg", "png"],
        },
        "summary": summary,
        "entries": entries,
        "notes": [
            "This manifest is intended for internal identity calibration only.",
            "Expected positive sets were derived from the exported identities in this slice.",
            "XQLFW quality scores are preserved as metadata and must not be confused with provider quality gates.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "xqlfw",
        "variant": variant,
        "selection": selection,
        "image_selection": image_selection,
        "offset": offset,
        "source_root": str(root),
        "output_dir": str(output_dir),
        "manifest_path": str(manifest_path),
        "summary": summary,
        "selected_people": sorted(ids_by_person.keys()),
        "sample_entry_ids": [str(entry["id"]) for entry in entries[:10]],
        "request_outcome": "success",
    }

    report_path.write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=True))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
