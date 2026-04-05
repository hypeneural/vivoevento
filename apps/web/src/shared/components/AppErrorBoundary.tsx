import React from 'react';
import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface AppErrorBoundaryProps {
  children: React.ReactNode;
  resetKey?: string;
}

interface AppErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class AppErrorBoundary extends React.Component<AppErrorBoundaryProps, AppErrorBoundaryState> {
  state: AppErrorBoundaryState = {
    hasError: false,
    error: null,
  };

  static getDerivedStateFromError(error: Error): AppErrorBoundaryState {
    return {
      hasError: true,
      error,
    };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Unhandled UI error', error, errorInfo);
  }

  componentDidUpdate(prevProps: AppErrorBoundaryProps) {
    if (this.state.hasError && prevProps.resetKey !== this.props.resetKey) {
      this.setState({
        hasError: false,
        error: null,
      });
    }
  }

  private handleRetry = () => {
    this.setState({
      hasError: false,
      error: null,
    });
  };

  private handleReload = () => {
    window.location.reload();
  };

  render() {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6">
        <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-sm">
          <div className="mb-4 inline-flex rounded-full bg-destructive/10 p-3 text-destructive">
            <AlertTriangle className="h-5 w-5" />
          </div>

          <h1 className="text-lg font-semibold">Nao foi possivel carregar esta tela</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            Um erro de interface interrompeu a renderizacao atual. Tente novamente ou recarregue a pagina.
          </p>

          <p className="mt-3 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
            {this.state.error?.message ?? 'Erro inesperado'}
          </p>

          <div className="mt-5 flex flex-wrap gap-3">
            <Button onClick={this.handleRetry}>Tentar novamente</Button>
            <Button variant="outline" onClick={this.handleReload}>Recarregar pagina</Button>
          </div>
        </div>
      </div>
    );
  }
}
