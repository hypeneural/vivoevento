from __future__ import annotations

import argparse
import json
import shutil
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export CFP-FP into a reusable FaceSearch pose holdout manifest.")
    parser.add_argument("--root", required=True)
    parser.add_argument("--image-selection", choices=["spread", "sequential"], default="spread")
    parser.add_argument("--offset", type=int, default=0)
    parser.add_argument("--people", type=int, default=12)
    parser.add_argument("--frontal-per-person", type=int, default=2)
    parser.add_argument("--profile-per-person", type=int, default=2)
    parser.add_argument("--output-dir", required=True)

    return parser.parse_args()


def select_images(images: list[Path], strategy: str, limit: int) -> list[Path]:
    limit = max(1, limit)
    ordered = sorted(images, key=lambda item: item.name.lower())

    if strategy == "sequential" or len(ordered) <= limit:
        return ordered[:limit]

    selected: list[Path] = []
    used: set[str] = set()
    candidate_indexes = [0, len(ordered) - 1, len(ordered) // 2]

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


def load_people(root: Path) -> list[dict[str, Any]]:
    people: list[dict[str, Any]] = []

    for person_dir in sorted([item for item in root.iterdir() if item.is_dir()], key=lambda item: item.name):
        frontal = sorted((person_dir / "frontal").glob("*.jpg"), key=lambda item: item.name.lower())
        profile = sorted((person_dir / "profile").glob("*.jpg"), key=lambda item: item.name.lower())

        if len(frontal) < 1 or len(profile) < 1:
            continue

        people.append({
            "person_id": f"cfp_{person_dir.name}",
            "subject_code": person_dir.name,
            "frontal": frontal,
            "profile": profile,
        })

    return people


def export_entries(selected_people: list[dict[str, Any]], image_selection: str, output_dir: Path) -> tuple[list[dict[str, Any]], dict[str, list[str]]]:
    entries: list[dict[str, Any]] = []
    ids_by_person: dict[str, list[str]] = {}

    for person in selected_people:
        person_id = str(person["person_id"])
        subject_code = str(person["subject_code"])
        ids_by_person[person_id] = []

        for pose, scene_type, quality_label, limit in [
            ("frontal", "single_prominent", "good", int(person["frontal_per_person"])),
            ("profile", "single_profile", "profile_extreme", int(person["profile_per_person"])),
        ]:
            images = select_images(list(person[pose]), image_selection, limit)

            for index, image_path in enumerate(images, start=1):
                relative_path = Path("images") / subject_code / pose / image_path.name
                target_path = output_dir / relative_path
                target_path.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(image_path, target_path)

                entry_id = f"cfp-fp-{subject_code}-{pose}-{index:02d}"
                ids_by_person[person_id].append(entry_id)

                entries.append({
                    "id": entry_id,
                    "dataset": "cfp_fp",
                    "event_id": "local-cfp-fp",
                    "person_id": person_id,
                    "relative_path": relative_path.as_posix(),
                    "scene_type": scene_type,
                    "quality_label": quality_label,
                    "pose_bucket": pose,
                    "is_public_search_eligible": False,
                    "expected_moderation_bucket": "safe",
                    "consent_basis": "public_research_dataset_internal_calibration",
                    "size_bytes": int(image_path.stat().st_size),
                    "requires_derivative_for_compreface": False,
                    "target_face_selection": {
                        "strategy": "largest",
                        "value": 1,
                    },
                    "notes": "Exported from local CFP-FP for pose holdout calibration.",
                })

    for entry in entries:
        entry["expected_positive_set"] = list(ids_by_person.get(str(entry["person_id"]), []))

    return entries, ids_by_person


def main() -> int:
    args = parse_args()
    root = Path(args.root).expanduser()
    image_selection = args.image_selection
    offset = max(0, int(args.offset))
    people_requested = max(2, int(args.people))
    frontal_per_person = max(1, int(args.frontal_per_person))
    profile_per_person = max(1, int(args.profile_per_person))
    output_dir = Path(args.output_dir).expanduser()
    output_dir.mkdir(parents=True, exist_ok=True)

    if not root.is_dir():
        raise FileNotFoundError(f"CFP-FP root not found: {root}")

    people = load_people(root)
    selected_people: list[dict[str, Any]] = []

    for person in people[offset:]:
        if len(selected_people) >= people_requested:
            break

        person["frontal_per_person"] = frontal_per_person
        person["profile_per_person"] = profile_per_person
        selected_people.append(person)

    if len(selected_people) < 2:
        raise RuntimeError("CFP-FP export requires at least two subjects after filtering.")

    entries, ids_by_person = export_entries(selected_people, image_selection, output_dir)

    if len(ids_by_person) < 2 or len(entries) < 4:
        raise RuntimeError("CFP-FP export did not produce enough entries for holdout evaluation.")

    summary = {
        "entries_exported": len(entries),
        "people_selected": len(ids_by_person),
        "offset": offset,
        "image_selection": image_selection,
        "frontal_per_person": frontal_per_person,
        "profile_per_person": profile_per_person,
        "quality_label_counts": dict(Counter(str(entry["quality_label"]) for entry in entries)),
        "scene_type_counts": dict(Counter(str(entry["scene_type"]) for entry in entries)),
    }

    manifest = {
        "version": 1,
        "created_at": datetime.now(timezone.utc).isoformat(),
        "status": "local_export_ready",
        "manifest_schema": "face_search_identity_dataset_v1",
        "dataset": "cfp_fp",
        "lane": "identity_pose_holdout",
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
            "This manifest is intended for frontal-profile pose holdout evaluation.",
            "Expected positive sets were derived from the exported subjects in this slice.",
        ],
    }

    manifest_path = output_dir / "manifest.json"
    report_path = output_dir / "report.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2), encoding="utf-8")

    report = {
        "dataset": "cfp_fp",
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
