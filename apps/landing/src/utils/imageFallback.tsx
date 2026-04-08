/**
 * Utilitário para fallback de imagens
 * Fornece SVG placeholder quando imagem falha ao carregar
 */

/**
 * Gera SVG placeholder para imagem indisponível
 * @param width - Largura do placeholder
 * @param height - Altura do placeholder
 * @param text - Texto a exibir no placeholder
 * @returns Data URI do SVG
 */
export function generateImagePlaceholder(
  width: number = 900,
  height: number = 600,
  text: string = 'Imagem indisponível'
): string {
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
      <rect fill="#f3f4f6" width="${width}" height="${height}"/>
      <text 
        x="50%" 
        y="50%" 
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
 * Handler para evento onError de imagem
 * Substitui imagem por placeholder SVG
 * 
 * @example
 * <img 
 *   src={image.src} 
 *   alt={image.alt}
 *   onError={handleImageError}
 * />
 */
export function handleImageError(
  event: React.SyntheticEvent<HTMLImageElement, Event>
): void {
  const img = event.currentTarget;
  
  // Evita loop infinito se placeholder também falhar
  if (img.src.startsWith('data:image/svg+xml')) {
    return;
  }

  // Substitui por placeholder
  img.src = generateImagePlaceholder();
  img.alt = 'Imagem de evento indisponível';
}

/**
 * Handler customizável para evento onError de imagem
 * Permite especificar dimensões e texto do placeholder
 * 
 * @example
 * <img 
 *   src={image.src} 
 *   alt={image.alt}
 *   onError={createImageErrorHandler(1200, 800, 'Foto do evento indisponível')}
 * />
 */
export function createImageErrorHandler(
  width?: number,
  height?: number,
  text?: string
) {
  return (event: React.SyntheticEvent<HTMLImageElement, Event>): void => {
    const img = event.currentTarget;
    
    // Evita loop infinito
    if (img.src.startsWith('data:image/svg+xml')) {
      return;
    }

    img.src = generateImagePlaceholder(width, height, text);
    img.alt = text || 'Imagem de evento indisponível';
  };
}

/**
 * Componente wrapper para imagem com fallback automático
 * 
 * @example
 * <ImageWithFallback 
 *   src={event.photo}
 *   alt="Foto do evento"
 *   fallbackText="Foto do evento indisponível"
 * />
 */
export interface ImageWithFallbackProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  src: string;
  alt: string;
  fallbackText?: string;
  fallbackWidth?: number;
  fallbackHeight?: number;
}

export function ImageWithFallback({
  src,
  alt,
  fallbackText,
  fallbackWidth,
  fallbackHeight,
  onError,
  ...props
}: ImageWithFallbackProps) {
  const handleError = (event: React.SyntheticEvent<HTMLImageElement, Event>) => {
    // Chama handler customizado se fornecido
    if (onError) {
      onError(event);
    }
    
    // Aplica fallback padrão
    createImageErrorHandler(fallbackWidth, fallbackHeight, fallbackText)(event);
  };

  return (
    <img 
      src={src} 
      alt={alt} 
      onError={handleError}
      {...props}
    />
  );
}
