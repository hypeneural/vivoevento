/**
 * Utilitário para fallback de vídeos
 * Fornece fallback quando vídeo falha ao carregar ou autoplay é bloqueado
 * 
 * Requirements: 29, 30
 */

import { useEffect, useRef, useState } from 'react';

/**
 * Gera SVG placeholder para vídeo indisponível
 * @param width - Largura do placeholder
 * @param height - Altura do placeholder
 * @param text - Texto a exibir no placeholder
 * @returns Data URI do SVG
 */
export function generateVideoPlaceholder(
  width: number = 1280,
  height: number = 720,
  text: string = 'Vídeo indisponível'
): string {
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
      <rect fill="#1a1a1a" width="${width}" height="${height}"/>
      <circle cx="${width / 2}" cy="${height / 2}" r="60" fill="rgba(255,255,255,0.1)"/>
      <path 
        d="M ${width / 2 - 20} ${height / 2 - 30} L ${width / 2 - 20} ${height / 2 + 30} L ${width / 2 + 30} ${height / 2} Z" 
        fill="rgba(255,255,255,0.3)"
      />
      <text 
        x="50%" 
        y="${height / 2 + 100}" 
        dominant-baseline="middle" 
        text-anchor="middle" 
        font-family="system-ui" 
        font-size="18" 
        fill="#9ca3af"
      >${text}</text>
    </svg>
  `.trim();

  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

/**
 * Handler para evento onError de vídeo
 * Substitui vídeo por poster ou placeholder
 * 
 * @example
 * <video 
 *   src={video.src} 
 *   poster={video.poster}
 *   onError={handleVideoError}
 * />
 */
export function handleVideoError(
  event: React.SyntheticEvent<HTMLVideoElement, Event>
): void {
  const video = event.currentTarget;
  
  // Se já tem poster, não precisa fazer nada (poster será exibido)
  if (video.poster) {
    console.warn('Video failed to load, showing poster:', video.src);
    return;
  }

  // Se não tem poster, esconde o vídeo e mostra mensagem
  video.style.display = 'none';
  
  // Cria elemento de fallback
  const fallback = document.createElement('div');
  fallback.className = 'video-fallback';
  fallback.style.cssText = `
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1a1a1a;
    color: #9ca3af;
    font-family: system-ui;
    font-size: 16px;
  `;
  fallback.textContent = 'Vídeo indisponível';
  
  // Insere fallback após o vídeo
  video.parentElement?.insertBefore(fallback, video.nextSibling);
}

/**
 * Hook para detectar se autoplay foi bloqueado
 * Retorna estado e função para tentar reproduzir manualmente
 * 
 * @example
 * const { autoplayBlocked, tryPlay } = useAutoplayDetection(videoRef);
 * 
 * if (autoplayBlocked) {
 *   return <button onClick={tryPlay}>Reproduzir vídeo</button>
 * }
 */
export function useAutoplayDetection(videoRef: React.RefObject<HTMLVideoElement>) {
  const [autoplayBlocked, setAutoplayBlocked] = useState(false);
  const [canPlay, setCanPlay] = useState(false);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const handleCanPlay = () => {
      setCanPlay(true);
    };

    const handlePlay = () => {
      setAutoplayBlocked(false);
    };

    const handlePause = () => {
      // Se pausou logo após tentar play, provavelmente foi bloqueado
      if (video.currentTime === 0) {
        setAutoplayBlocked(true);
      }
    };

    video.addEventListener('canplay', handleCanPlay);
    video.addEventListener('play', handlePlay);
    video.addEventListener('pause', handlePause);

    // Tenta autoplay quando vídeo estiver pronto
    if (canPlay && video.paused) {
      const playPromise = video.play();
      
      if (playPromise !== undefined) {
        playPromise.catch((error) => {
          // Autoplay foi bloqueado
          console.warn('Autoplay blocked:', error);
          setAutoplayBlocked(true);
        });
      }
    }

    return () => {
      video.removeEventListener('canplay', handleCanPlay);
      video.removeEventListener('play', handlePlay);
      video.removeEventListener('pause', handlePause);
    };
  }, [videoRef, canPlay]);

  const tryPlay = () => {
    const video = videoRef.current;
    if (!video) return;

    video.play().catch((error) => {
      console.error('Failed to play video:', error);
    });
  };

  return { autoplayBlocked, tryPlay };
}

/**
 * Componente wrapper para vídeo com fallback automático
 * Detecta autoplay bloqueado e oferece botão de play manual
 * 
 * @example
 * <VideoWithFallback 
 *   src={video.src}
 *   poster={video.poster}
 *   fallbackText="Vídeo de demonstração indisponível"
 * />
 */
export interface VideoWithFallbackProps extends React.VideoHTMLAttributes<HTMLVideoElement> {
  src: string;
  poster?: string;
  fallbackText?: string;
  showPlayButton?: boolean;
}

export function VideoWithFallback({
  src,
  poster,
  fallbackText = 'Vídeo indisponível',
  showPlayButton = true,
  onError,
  ...props
}: VideoWithFallbackProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const { autoplayBlocked, tryPlay } = useAutoplayDetection(videoRef);
  const [hasError, setHasError] = useState(false);

  const handleError = (event: React.SyntheticEvent<HTMLVideoElement, Event>) => {
    setHasError(true);
    
    // Chama handler customizado se fornecido
    if (onError) {
      onError(event);
    }
    
    // Aplica fallback padrão
    handleVideoError(event);
  };

  // Se vídeo falhou, mostra poster ou mensagem
  if (hasError) {
    if (poster) {
      return (
        <img 
          src={poster} 
          alt={fallbackText}
          style={{ width: '100%', height: '100%', objectFit: 'cover' }}
        />
      );
    }
    
    return (
      <div 
        style={{
          width: '100%',
          height: '100%',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: '#1a1a1a',
          color: '#9ca3af',
          fontFamily: 'system-ui',
          fontSize: '16px',
        }}
      >
        {fallbackText}
      </div>
    );
  }

  return (
    <div style={{ position: 'relative', width: '100%', height: '100%' }}>
      <video 
        ref={videoRef}
        src={src} 
        poster={poster}
        onError={handleError}
        {...props}
      />
      
      {/* Botão de play manual se autoplay foi bloqueado */}
      {autoplayBlocked && showPlayButton && (
        <button
          onClick={tryPlay}
          style={{
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: '80px',
            height: '80px',
            borderRadius: '50%',
            background: 'rgba(0, 0, 0, 0.7)',
            border: '2px solid rgba(255, 255, 255, 0.8)',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            transition: 'all 0.2s ease',
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.background = 'rgba(0, 0, 0, 0.9)';
            e.currentTarget.style.transform = 'translate(-50%, -50%) scale(1.1)';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.background = 'rgba(0, 0, 0, 0.7)';
            e.currentTarget.style.transform = 'translate(-50%, -50%) scale(1)';
          }}
          aria-label="Reproduzir vídeo"
        >
          <svg 
            width="32" 
            height="32" 
            viewBox="0 0 24 24" 
            fill="white"
          >
            <path d="M8 5v14l11-7z" />
          </svg>
        </button>
      )}
    </div>
  );
}
