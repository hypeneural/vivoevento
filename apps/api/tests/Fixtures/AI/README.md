# AI Photo Fixture Dataset

This directory is the curated fixture contract for photo AI validation.

It is intentionally not a dump of customer uploads. Any real image added here must be anonymized, consented for internal validation, and small enough for local automated tests and provider smoke tests.

## Purpose

- provide a repeatable dataset for `ContentModeration` smoke checks;
- provide a repeatable dataset for `FaceSearch` threshold and recall validation;
- provide a shared input source for later `exact|ann` benchmark work;
- prevent ad-hoc photo folders from becoming the benchmark source of truth.

## Required Groups

- `safety-safe`: benign event photos such as group, party, and portrait images.
- `safety-review-block`: controlled samples that exercise review/block moderation thresholds.
- `face-search-positive`: the same anonymized or consented person across multiple photos in the same event.
- `face-search-negative`: different people with partially similar appearance.
- `face-search-low-quality`: blurred, dark, occluded, profile-extreme, or very small faces.
- `cross-event-isolation`: same or similar people across different events to validate event scoping.

## Manifest Contract

`manifest.json` is the source of truth. Each fixture entry must include:

- `id`
- `path`
- `event_id`
- `person_id`
- `expected_positive_set`
- `quality_label`
- `is_public_search_eligible`
- `expected_moderation_bucket`

Optional but recommended fields:

- `consent_basis`
- `notes`
- `tags`

## Privacy Rules

- Do not commit raw customer uploads.
- Do not commit API keys, provider request logs, or private CDN URLs.
- Prefer synthetic, staff-consented, or legally cleared anonymized assets.
- If a fixture contains a recognizable person, document the consent basis in the manifest.
- If a fixture is synthetic and not useful for biometric recall, mark it in `tags`.

## Current Status

The fixture contract is ready, but the actual anonymized image assets are still an operational dependency before `H3` benchmark work can be considered valid.
