# Hero Phone Assets

Assets otimizados para a hero da landing page.

## Arquivos publicados

- `public/assets/hero-phone/poster-phone.jpg`: poster carregado imediatamente na hero.
- `public/assets/hero-phone/preview-phone.mp4`: preview curta, sem audio, usada dentro do mockup.
- `public/assets/hero-phone/preview-phone.webm`: alternativa para navegadores com suporte.
- `public/assets/hero-phone/full-demo.mp4`: versao completa usada no modal.
- `public/assets/hero-phone/phone-frame.svg`: moldura estatica do celular.

## Fonte usada nesta versao

`C:\Users\Usuario\Desktop\0410 (2)\1.mp4`

## Pipeline ffmpeg

Poster:

```powershell
ffmpeg -y -ss 1 -i "C:\Users\Usuario\Desktop\0410 (2)\1.mp4" `
  -frames:v 1 `
  -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280" `
  "apps/landing/public/assets/hero-phone/poster-phone.jpg"
```

Preview MP4:

```powershell
ffmpeg -y -ss 0.8 -t 8 -i "C:\Users\Usuario\Desktop\0410 (2)\1.mp4" `
  -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280" `
  -an `
  -c:v libx264 `
  -profile:v high `
  -preset slow `
  -crf 24 `
  -movflags +faststart `
  "apps/landing/public/assets/hero-phone/preview-phone.mp4"
```

Preview WebM:

```powershell
ffmpeg -y -ss 0.8 -t 8 -i "C:\Users\Usuario\Desktop\0410 (2)\1.mp4" `
  -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280" `
  -an `
  -c:v libvpx-vp9 `
  -b:v 0 `
  -crf 34 `
  "apps/landing/public/assets/hero-phone/preview-phone.webm"
```

Versao completa:

```powershell
ffmpeg -y -i "C:\Users\Usuario\Desktop\0410 (2)\1.mp4" `
  -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280" `
  -c:v libx264 `
  -profile:v high `
  -preset slow `
  -crf 26 `
  -c:a aac `
  -b:a 96k `
  -movflags +faststart `
  "apps/landing/public/assets/hero-phone/full-demo.mp4"
```
