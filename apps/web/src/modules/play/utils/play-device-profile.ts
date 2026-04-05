type NavigatorWithConnection = Navigator & {
  connection?: {
    saveData?: boolean;
    effectiveType?: string;
    downlink?: number;
  };
};

export type PlayConnectionProfile = {
  saveData: boolean;
  effectiveType: string;
  downlink: number | null;
};

export type PlayRuntimeDeviceProfile = {
  platform?: string;
  viewportWidth?: number;
  viewportHeight?: number;
  pixelRatio?: number;
  connection?: {
    saveData?: boolean;
    effectiveType?: string;
    downlink?: number;
  };
};

export type PlayAssetQueryProfile = {
  cacheKey: string;
  params: Record<string, string | number | boolean>;
  device: PlayRuntimeDeviceProfile;
  bucket: 'constrained' | 'standard' | 'rich';
};

export function getPlayConnectionProfile(): PlayConnectionProfile {
  if (typeof navigator === 'undefined') {
    return {
      saveData: false,
      effectiveType: '4g',
      downlink: null,
    };
  }

  const connection = (navigator as NavigatorWithConnection).connection;

  return {
    saveData: Boolean(connection?.saveData),
    effectiveType: connection?.effectiveType ?? '4g',
    downlink: typeof connection?.downlink === 'number' ? connection.downlink : null,
  };
}

function resolveBucket(connection: PlayConnectionProfile, viewportWidth: number, pixelRatio: number) {
  if (
    connection.saveData
    || connection.effectiveType === 'slow-2g'
    || connection.effectiveType === '2g'
    || ((connection.downlink ?? 10) < 1)
  ) {
    return 'constrained' as const;
  }

  if (
    connection.effectiveType === '3g'
    || ((connection.downlink ?? 10) < 2.5)
    || viewportWidth < 430
    || pixelRatio < 2
  ) {
    return 'standard' as const;
  }

  return 'rich' as const;
}

export function getPlayAssetQueryProfile(): PlayAssetQueryProfile {
  const viewportWidth = typeof window === 'undefined' ? 390 : window.innerWidth;
  const viewportHeight = typeof window === 'undefined' ? 844 : window.innerHeight;
  const pixelRatio = typeof window === 'undefined' ? 2 : Number(window.devicePixelRatio || 1);
  const platform = typeof navigator === 'undefined' ? undefined : navigator.platform;
  const connection = getPlayConnectionProfile();
  const bucket = resolveBucket(connection, viewportWidth, pixelRatio);
  const viewportBucket = viewportWidth < 430 ? 'sm' : viewportWidth < 820 ? 'md' : 'lg';
  const dprBucket = pixelRatio < 1.5 ? 'sd' : pixelRatio < 2.5 ? 'md' : 'hd';

  return {
    bucket,
    cacheKey: `${bucket}:${viewportBucket}:${dprBucket}`,
    params: {
      platform: platform ?? 'unknown',
      viewport_width: viewportWidth,
      viewport_height: viewportHeight,
      pixel_ratio: Number(pixelRatio.toFixed(2)),
      save_data: connection.saveData,
      effective_type: connection.effectiveType,
      downlink: connection.downlink ?? 0,
    },
    device: {
      platform,
      viewportWidth,
      viewportHeight,
      pixelRatio,
      connection: {
        saveData: connection.saveData,
        effectiveType: connection.effectiveType,
        downlink: connection.downlink ?? undefined,
      },
    },
  };
}
