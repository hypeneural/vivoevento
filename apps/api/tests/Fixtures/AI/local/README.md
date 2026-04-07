# Local Consented Dataset

This directory stores manifests for local-only consented datasets that stay outside the repository.

Use this path when:

- the user has explicitly authorized local validation;
- the image files should not be committed into `tests/Fixtures/AI/`;
- the team still needs a repeatable manifest for smoke tests and benchmark preparation.

Rules:

- never commit the raw external images into this directory;
- keep the manifest under version control, but keep the assets outside the repository;
- resolve the asset root from an environment variable first;
- if a source file is larger than the CompreFace 5 MB request limit, mark it as requiring a derivative for smoke execution.
- when a photo has multiple faces, define `target_face_selection` in the manifest so smoke/benchmark pick the intended person instead of the largest face by accident.

Current local manifest:

- `vipsocial.manifest.json`

Recommended local env:

- `VIPSOCIAL_DATASET_ROOT=%USERPROFILE%\\Desktop\\vipsocial`

Recommended manifest hints for multi-face photos:

- `scene_type`
- `target_face_selection.strategy`
- `target_face_selection.value`
