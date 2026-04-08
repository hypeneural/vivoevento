from __future__ import annotations

import argparse
import json
import shutil
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export LFW into a reusable FaceSearch identity manifest.")
    parser.add_argument("--root", required=True)
    parser.add_argument("--selection", choices=["largest_identities", "sequential"], default="largest_identities")
    parser.add_argument("--image-selection", choices=["spread", "sequential"], default="spread")
    parser.add_argument("--offset", type=int, default=0)
    parser.add_argument("--people", type=int, default=12)
    parser.add_argument("--images-per-person", type=int, default=4)
    parser.add_argument("--min-images-per-person", type=int, default=4)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def slug_token(value: str) -> str:
    return "".join(ch if ch.isalnum() else "-" for ch in value.lower()).strip("-") or "unknown"


def load_people(root: Path, min_images_per_person: int) -> list[dict[str, Any]]:
    people: list[dict[str, Any]] = []

    for person_dir in sorted([item for item in root.iterdir() if item.is_dir()], key=lambda item: item.name.lower()):
        images = sorted(person_dir.glob("*.jpg"), key=lambda item: item.name.lower())

        if len(images) < min_images_per_person:
            continue

        people.append({
            "person_id": person_dir.name,
            "images": images,
            "image_count": len(images),
        })

    return people


def sort_people(people: list[dict[str, Any]], selection: str) -> list[dict[str, Any]]:
    if selection == "sequential":
        return sorted(people, key=lambda item: str(item["person_id"]).lower())

    return sorted(
        people,
        key=lambda item: (
            -int(item["image_count"]),
            str(item["person_id"]).lower(),
        ),
    )


def select_images(images: list[Path], strategy: str, limit: int) -> list[Path]:
    limit = max(2, limit)
    ordered = sorted(images, key=lambda item: item.name.lower())

    if strategy == "sequential" or len(ordered) <= limit:
        return ordered[:limit]

    selected: list[Path] = []
    used: set[str] = set()
    candidate_indexes = [
        0,
        len(ordered) - 1,
        len(ordered) // 2,
        max(0, len(ordered) // 3),
        min(len(ordered) - 1, (2 * len(ordered)) // 3),
    ]

    for index in candidate_indexes:
        if index < 0 or index >= len(ordered):
            continue

        candidate = ordered[index]

        if candidate.name in used:
            continue

        selected.append(candidate)
        used.add(candidate.name)

        if len(selected) >= limit:
            break

    if len(selected) < limit:
        for candidate in ordered:
            if candidate.name in used:
                continue

            selected.append(candidate)
            used.add(candidate.name)

            if len(selected) >= limit:
                break

    return sorted(selected, key=lambda item: item.name.lower())[:limit]


def export_entries(selected_people: list[dict[str, Any]], image_selection: str, output_dir: Path) -> tuple[list[dict[str, Any]], dict[str, list[str]]]:
    entries: list[dict[str, Any]] = []
    ids_by_person: dict[str, list[str]] = {}

    for person in selected_people:
        person_id = str(person["person_id"])
        person_slug = slug_token(person_id)
        images = select_images(list(person["images"]), image_selection, int(person["images_per_person"]))

        if len(images) < 2:
            continue

        ids_by_person[person_id] = []

        for index, image_path in enumerate(images, start=1):
            relative_path = Path("images") / person_id / image_path.name
            target_path = output_dir / relative_path
            target_path.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(image_path, target_path)

            entry_id = f"lfw-{person_slug}-{index:02d}"
            ids_by_person[person_id].append(entry_id)

            entries.append({
                "id": entry_id,
                "dataset": "lfw",
                "event_id": "local-lfw",
                "person_id": person_id,
                "relative_path": relative_path.as_posix(),
                "scene_type": "single_prominent",
                "quality_label": "baseline_identity",
                "is_public_search_eligible": False,
                "expected_moderation_bucket": "safe",
                "consent_basis": "public_research_dataset_internal_calibration",
                "size_bytes": int(image_path.stat().st_size),
                "requires_derivative_for_compreface": False,
                "target_face_selection": {
                    "strategy": "largest",
                    "value": 1,
                },
                "notes": "Exported from local LFW for primary identity calibration.",
            })

    for entry in entries:
        entry["expected_positive_set"] = list(ids_by_person.get(str(entry["person_id"]), []))

    return entries, ids_by_person


def main() -> int:
    args = parse_args()
    root = Path(args.root).expanduser()
    selection = args.selection
    image_selection = args.image_selection
    offset = max(0, int(args.offset))
    people_requested = max(2, int(args.people))
    images_per_person = max(2, int(args.images_per_person))
    min_images_per_person = max(2, int(args.min_images_per_person))
    output_dir = Path(args.output_dir).expanduser()
    output_dir.mkdir(parents=True, exist_ok=True)

    if not root.is_dir():
        raise FileNotFoundError(f"LFW root not found: {root}")

    people = sort_people(load_people(root, min_images_per_person), selection)
    selected_people: list[dict[str, Any]] = []

    for person in people[offset:]:
        if len(selected_people) >= people_requested:
            break

        person["images_per_person"] = images_per_person
        selected_people.append(person)

    if len(selected_people) < 2:
        raise RuntimeError("LFW export requires at least two identities after filtering.")

    entries, ids_by_person = export_entries(selected_people, image_selection, output_dir)

    if len(ids_by_person) < 2 or len(entries) < 4:
        raise RuntimeError("LFW export did not produce enough entries for identity calibration.")

    summary = {
        "entries_exported": len(entries),
        "people_selected": len(ids_by_person),
        "offset": offset,
        "selection": selection,
        "image_selection": image_selection,
        "images_per_person_requested": images_per_person,
        "quality_label_counts": dict(Counter(str(entry["quality_label"]) for entry in entries)),
        "image_count_by_person": {
            str(person["person_id"]): int(person["image_count"])
            for person in selected_people
        },
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_identity_dataset_v1",
        "dataset": "lfw",
        "lane": "identity_primary",
        "selection": selection,
        "image_selection": image_selection,
        "offset": offset,
        "asset_root_env": "",
        "fallback_asset_root": str(output_dir),
        "source_root": str(root),
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
            "This manifest is intended for primary identity calibration, not customer search.",
            "The local export expects an LFW directory tree organized by person.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "lfw",
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
