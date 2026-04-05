import { useEffect, useState } from 'react';
import Cropper, { type Area } from 'react-easy-crop';
import 'react-easy-crop/react-easy-crop.css';
import { RotateCw, ZoomIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Slider } from '@/components/ui/slider';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { getCroppedAvatarBlob } from '@/modules/auth/utils/avatarCrop';

interface AvatarCropDialogProps {
  open: boolean;
  imageSrc: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: (blob: Blob) => Promise<void> | void;
}

export function AvatarCropDialog({
  open,
  imageSrc,
  isSubmitting,
  onOpenChange,
  onConfirm,
}: AvatarCropDialogProps) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);

  useEffect(() => {
    if (!open) {
      setCrop({ x: 0, y: 0 });
      setZoom(1);
      setRotation(0);
      setCroppedAreaPixels(null);
    }
  }, [open]);

  const handleConfirm = async () => {
    if (!imageSrc || !croppedAreaPixels) return;

    const blob = await getCroppedAvatarBlob(imageSrc, croppedAreaPixels, rotation);
    await onConfirm(blob);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Ajustar foto de perfil</DialogTitle>
          <DialogDescription>
            Posicione a imagem em um recorte fixo 1:1. O resultado final sera salvo em formato padronizado.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="relative h-72 overflow-hidden rounded-2xl bg-black sm:h-96">
            {imageSrc && (
              <Cropper
                image={imageSrc}
                crop={crop}
                zoom={zoom}
                rotation={rotation}
                aspect={1}
                cropShape="round"
                showGrid={false}
                onCropChange={setCrop}
                onZoomChange={setZoom}
                onRotationChange={setRotation}
                onCropComplete={(_, croppedPixels) => setCroppedAreaPixels(croppedPixels)}
              />
            )}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="flex items-center gap-2">
                  <ZoomIn className="h-4 w-4" />
                  Zoom
                </span>
                <span className="text-muted-foreground">{zoom.toFixed(1)}x</span>
              </div>
              <Slider
                min={1}
                max={3}
                step={0.1}
                value={[zoom]}
                onValueChange={([value]) => setZoom(value)}
              />
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="flex items-center gap-2">
                  <RotateCw className="h-4 w-4" />
                  Rotacao
                </span>
                <span className="text-muted-foreground">{Math.round(rotation)}°</span>
              </div>
              <Slider
                min={0}
                max={360}
                step={1}
                value={[rotation]}
                onValueChange={([value]) => setRotation(value)}
              />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button onClick={handleConfirm} disabled={!imageSrc || !croppedAreaPixels || isSubmitting}>
            {isSubmitting ? 'Salvando...' : 'Salvar foto'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
