import { useEffect } from 'react';
import 'photoswipe/style.css';

export interface GalleryLightboxPhoto {
  src: string;
  msrc?: string;
  width: number;
  height: number;
  alt: string;
  srcset?: string;
  sizes?: string;
}

interface GalleryPhotoLightboxProps {
  photos: GalleryLightboxPhoto[];
  activeIndex: number | null;
  onClose: () => void;
}

type LightboxInstance = {
  init: () => void;
  destroy: () => void;
  loadAndOpen: (index: number, dataSource?: unknown) => boolean;
  on: (eventName: string, callback: () => void) => void;
};

export function GalleryPhotoLightbox({ photos, activeIndex, onClose }: GalleryPhotoLightboxProps) {
  const activePhoto = activeIndex === null ? null : photos[activeIndex] ?? null;

  useEffect(() => {
    if (activeIndex === null || photos.length === 0) {
      return;
    }

    let disposed = false;
    let lightbox: LightboxInstance | null = null;

    void import('photoswipe/lightbox').then(({ default: PhotoSwipeLightbox }) => {
      if (disposed) {
        return;
      }

      lightbox = new PhotoSwipeLightbox({
        dataSource: photos.map((photo) => ({
          src: photo.src,
          msrc: photo.msrc,
          width: photo.width,
          height: photo.height,
          alt: photo.alt,
          srcset: photo.srcset,
          sizes: photo.sizes,
        })),
        pswpModule: () => import('photoswipe'),
      }) as LightboxInstance;

      lightbox.on('close', onClose);
      lightbox.init();
      lightbox.loadAndOpen(activeIndex);
    });

    return () => {
      disposed = true;
      lightbox?.destroy();
    };
  }, [activeIndex, onClose, photos]);

  if (!activePhoto) {
    return null;
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={activePhoto.alt}
      className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/90 p-4 text-white"
    >
      <div className="relative max-h-full max-w-5xl overflow-hidden rounded-3xl border border-white/10 bg-black">
        <button
          type="button"
          onClick={onClose}
          className="absolute right-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1 text-sm font-medium text-slate-950"
        >
          Fechar foto
        </button>
        <img
          src={activePhoto.src}
          srcSet={activePhoto.srcset}
          sizes={activePhoto.sizes}
          width={activePhoto.width}
          height={activePhoto.height}
          alt={activePhoto.alt}
          className="max-h-[85dvh] w-auto object-contain"
        />
      </div>
    </div>
  );
}
