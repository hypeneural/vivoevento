export type PreparedGameAssets = {
  cover: string;
  puzzleTiles: string[];
  memoryFaces: string[];
};

const imageElementCache = new Map<string, Promise<HTMLImageElement>>();
const squareImageCache = new Map<string, Promise<string>>();
const puzzleTileCache = new Map<string, Promise<string[]>>();
const assetBundleCache = new Map<string, Promise<PreparedGameAssets>>();

function loadImageElement(src: string) {
  let existing = imageElementCache.get(src);

  if (!existing) {
    existing = new Promise<HTMLImageElement>((resolve, reject) => {
      const image = new Image();
      image.crossOrigin = "anonymous";
      image.decoding = "async";
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error(`Unable to load image asset: ${src}`));
      image.src = src;
    });

    imageElementCache.set(src, existing);
  }

  return existing;
}

async function createSquareImage(src: string, size: number) {
  const cacheKey = `${src}|${size}`;
  const existing = squareImageCache.get(cacheKey);

  if (existing) {
    return existing;
  }

  const next = loadImageElement(src).then((image) => {
    const canvas = document.createElement("canvas");
    const context = canvas.getContext("2d");

    if (!context) {
      throw new Error("Canvas 2D context is unavailable.");
    }

    const side = Math.min(image.naturalWidth, image.naturalHeight);
    const sx = Math.max((image.naturalWidth - side) / 2, 0);
    const sy = Math.max((image.naturalHeight - side) / 2, 0);

    canvas.width = size;
    canvas.height = size;

    context.drawImage(image, sx, sy, side, side, 0, 0, size, size);

    return canvas.toDataURL("image/jpeg", 0.86);
  });

  squareImageCache.set(cacheKey, next);
  return next;
}

async function createPuzzleTiles(src: string, size = 540) {
  const cacheKey = `${src}|tiles|${size}`;
  const existing = puzzleTileCache.get(cacheKey);

  if (existing) {
    return existing;
  }

  const next = loadImageElement(src).then((image) => {
    const sourceSide = Math.min(image.naturalWidth, image.naturalHeight);
    const sx = Math.max((image.naturalWidth - sourceSide) / 2, 0);
    const sy = Math.max((image.naturalHeight - sourceSide) / 2, 0);
    const tileSize = Math.floor(size / 3);

    return Array.from({ length: 9 }).map((_, index) => {
      const tileCanvas = document.createElement("canvas");
      const tileContext = tileCanvas.getContext("2d");

      if (!tileContext) {
        throw new Error("Canvas 2D context is unavailable.");
      }

      const column = index % 3;
      const row = Math.floor(index / 3);
      const sourceTile = sourceSide / 3;

      tileCanvas.width = tileSize;
      tileCanvas.height = tileSize;

      tileContext.drawImage(
        image,
        sx + column * sourceTile,
        sy + row * sourceTile,
        sourceTile,
        sourceTile,
        0,
        0,
        tileSize,
        tileSize
      );

      return tileCanvas.toDataURL("image/jpeg", 0.86);
    });
  });

  puzzleTileCache.set(cacheKey, next);
  return next;
}

export async function prepareGameAssets(puzzleSource: string, memorySources: string[]) {
  const cacheKey = `${puzzleSource}|${memorySources.join("|")}`;
  const existing = assetBundleCache.get(cacheKey);

  if (existing) {
    return existing;
  }

  const next = Promise.all([
    createSquareImage(puzzleSource, 480),
    createPuzzleTiles(puzzleSource),
    Promise.all(memorySources.slice(0, 6).map((source) => createSquareImage(source, 256))),
  ]).then(([cover, puzzleTiles, memoryFaces]) => ({
    cover,
    puzzleTiles,
    memoryFaces,
  }));

  assetBundleCache.set(cacheKey, next);
  return next;
}
