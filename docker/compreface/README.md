# CompreFace Local Stack

This directory contains the local Docker stack used to run CompreFace on a dedicated port for Evento Vivo smoke tests.

## Port

- UI/base URL: `http://127.0.0.1:8002`

## Start

```powershell
docker compose --env-file .env -f docker-compose.yml up -d
```

## Stop

```powershell
docker compose --env-file .env -f docker-compose.yml down
```

## Required UI Steps

After the stack is up, create these services in the CompreFace UI:

1. Face Detection Service
2. Face Verification Service

Then copy the generated `x-api-key` values into:

- `FACE_SEARCH_COMPRE_FACE_DETECTION_API_KEY`
- `FACE_SEARCH_COMPRE_FACE_VERIFICATION_API_KEY`

## App Integration

The Laravel app expects:

- `FACE_SEARCH_COMPRE_FACE_BASE_URL=http://127.0.0.1:8002`
- `VIPSOCIAL_DATASET_ROOT=C:\Users\Usuario\Desktop\vipsocial`

Smoke commands:

```powershell
cd apps/api
php artisan face-search:smoke-compreface --dry-run
php artisan face-search:smoke-compreface
```
