/**
 * Error Boundary Component
 * Captura erros de componentes e exibe fallback gracioso
 * 
 * Requirement 30: Manter CTAs funcionais mesmo com falha de componentes interativos
 */

import { Component, ErrorInfo, ReactNode } from 'react';
import styles from './ErrorBoundary.module.scss';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  showCTA?: boolean;
  componentName?: string;
}

interface State {
  hasError: boolean;
  error?: Error;
}

export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    // Track error in analytics
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'exception', {
        description: `${this.props.componentName || 'Component'}: ${error.message}`,
        fatal: false,
      });
    }
  }

  render() {
    if (this.state.hasError) {
      // Use custom fallback if provided
      if (this.props.fallback) {
        return this.props.fallback;
      }

      // Default fallback with optional CTA
      return (
        <div className={styles.errorContainer}>
          <div className={styles.errorContent}>
            <svg 
              className={styles.errorIcon}
              width="48" 
              height="48" 
              viewBox="0 0 24 24" 
              fill="none" 
              stroke="currentColor" 
              strokeWidth="2"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            
            <h3 className={styles.errorTitle}>
              {this.props.componentName 
                ? `Erro ao carregar ${this.props.componentName}` 
                : 'Erro ao carregar componente'}
            </h3>
            
            <p className={styles.errorMessage}>
              Não foi possível carregar este conteúdo. Tente recarregar a página.
            </p>

            {this.props.showCTA && (
              <div className={styles.errorCTA}>
                <a 
                  href="https://eventovivo.com/agendar" 
                  className={styles.primaryButton}
                >
                  Agendar demonstração
                </a>
                <a 
                  href="https://wa.me/5511999999999?text=Olá!%20Quero%20conhecer%20a%20plataforma%20Evento%20Vivo." 
                  className={styles.secondaryButton}
                >
                  Falar no WhatsApp
                </a>
              </div>
            )}

            {import.meta.env.MODE === 'development' && this.state.error && (
              <details className={styles.errorDetails}>
                <summary>Detalhes do erro (desenvolvimento)</summary>
                <pre>{this.state.error.message}</pre>
                <pre>{this.state.error.stack}</pre>
              </details>
            )}
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

/**
 * Hook-based error boundary wrapper
 * Para uso com componentes funcionais
 */
export function withErrorBoundary<P extends object>(
  Component: React.ComponentType<P>,
  options?: {
    fallback?: ReactNode;
    showCTA?: boolean;
    componentName?: string;
  }
) {
  return function WithErrorBoundary(props: P) {
    return (
      <ErrorBoundary {...options}>
        <Component {...props} />
      </ErrorBoundary>
    );
  };
}
