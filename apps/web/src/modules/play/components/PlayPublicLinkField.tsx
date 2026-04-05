import { Copy, ExternalLink, Link2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import { copyTextToClipboard } from '@/modules/play/utils/public-links';

type PlayPublicLinkFieldProps = {
  label: string;
  url: string;
  description?: string;
  compact?: boolean;
  className?: string;
};

export function PlayPublicLinkField({
  label,
  url,
  description,
  compact = false,
  className,
}: PlayPublicLinkFieldProps) {
  const { toast } = useToast();

  async function handleCopy() {
    try {
      const copied = await copyTextToClipboard(url);

      if (!copied) {
        throw new Error('Nao foi possivel copiar o link.');
      }

      toast({
        title: 'Link copiado',
        description: 'O endereco publico foi copiado para a area de transferencia.',
      });
    } catch (error) {
      toast({
        title: 'Falha ao copiar link',
        description: error instanceof Error ? error.message : 'Nao foi possivel copiar o link agora.',
        variant: 'destructive',
      });
    }
  }

  return (
    <div className={`rounded-2xl border border-slate-200 bg-slate-50/80 p-4 ${className ?? ''}`.trim()}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <Link2 className="h-4 w-4 text-slate-500" />
            <span>{label}</span>
          </div>
          {description ? (
            <p className="text-xs text-muted-foreground">{description}</p>
          ) : null}
        </div>

        <div className="flex items-center gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => void handleCopy()}>
            <Copy className="mr-1.5 h-4 w-4" />
            Copiar
          </Button>
          <Button type="button" asChild size="sm">
            <a href={url} target="_blank" rel="noreferrer">
              <ExternalLink className="mr-1.5 h-4 w-4" />
              Abrir
            </a>
          </Button>
        </div>
      </div>

      <div className={`mt-3 rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-700 ${compact ? 'break-all' : 'break-all sm:text-sm'}`}>
        {url}
      </div>
    </div>
  );
}
