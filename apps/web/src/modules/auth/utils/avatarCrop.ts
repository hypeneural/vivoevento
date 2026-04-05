import type { Area } from 'react-easy-crop';

function createImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.addEventListener('load', () => resolve(image));
    image.addEventListener('error', () => reject(new Error('Nao foi possivel carregar a imagem.')));
    image.src = src;
  });
}

function getRadianAngle(degreeValue: number): number {
  return (degreeValue * Math.PI) / 180;
}

function rotateSize(width: number, height: number, rotation: number) {
  const rotRad = getRadianAngle(rotation);

  return {
    width: Math.abs(Math.cos(rotRad) * width) + Math.abs(Math.sin(rotRad) * height),
    height: Math.abs(Math.sin(rotRad) * width) + Math.abs(Math.cos(rotRad) * height),
  };
}

export async function getCroppedAvatarBlob(
  imageSrc: string,
  croppedAreaPixels: Area,
  rotation: number,
): Promise<Blob> {
  const image = await createImage(imageSrc);
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');

  if (!context) {
    throw new Error('Nao foi possivel preparar o recorte da imagem.');
  }

  const rotationInRadians = getRadianAngle(rotation);
  const rotatedSize = rotateSize(image.width, image.height, rotation);

  canvas.width = rotatedSize.width;
  canvas.height = rotatedSize.height;

  context.translate(rotatedSize.width / 2, rotatedSize.height / 2);
  context.rotate(rotationInRadians);
  context.translate(-image.width / 2, -image.height / 2);
  context.drawImage(image, 0, 0);

  const croppedCanvas = document.createElement('canvas');
  const croppedContext = croppedCanvas.getContext('2d');

  if (!croppedContext) {
    throw new Error('Nao foi possivel finalizar o recorte da imagem.');
  }

  croppedCanvas.width = 512;
  croppedCanvas.height = 512;

  croppedContext.drawImage(
    canvas,
    croppedAreaPixels.x,
    croppedAreaPixels.y,
    croppedAreaPixels.width,
    croppedAreaPixels.height,
    0,
    0,
    512,
    512,
  );

  return new Promise((resolve, reject) => {
    croppedCanvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Nao foi possivel gerar o avatar recortado.'));
        return;
      }

      resolve(blob);
    }, 'image/webp', 0.92);
  });
}
