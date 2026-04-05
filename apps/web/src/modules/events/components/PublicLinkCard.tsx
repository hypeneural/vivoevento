import { Copy, ExternalLink, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ApiEventPublicLink } from '@/lib/api-types';

interface PublicLinkCardProps {
  link: ApiEventPublicLink;
  onCopy: (value: string, label: string) => void;
}

const IDENTIFIER_LABELS: Record<ApiEventPublicLink['identifier_type'], string> = {
  slug: 'Slug publico',
  upload_slug: 'Slug de envio',
  wall_code: 'Codigo do wall',
};

export function PublicLinkCard({ link, onCopy }: PublicLinkCardProps) {
  const canOpen = !!link.url;
  const canCopy = !!link.url;

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardContent className="grid gap-4 p-4 md:grid-cols-[1fr_auto]">
        <div className="space-y-3">
          <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <p className="text-sm font-semibold">{link.label}</p>
                <Badge variant={link.enabled ? 'outline' : 'secondary'}>
                  {link.enabled ? 'Ativo' : 'Inativo'}
                </Badge>
              </div>
              <p className="text-xs text-muted-foreground">
                {IDENTIFIER_LABELS[link.identifier_type]}: <span className="font-mono">{link.identifier || '—'}</span>
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="break-all text-xs text-muted-foreground">{link.url || 'Link nao disponivel para este evento.'}</p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={!canCopy || !link.url}
              onClick={() => link.url && onCopy(link.url, link.label)}
            >
              <Copy className="mr-1.5 h-3.5 w-3.5" />
              Copiar
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={!canOpen || !link.url}
              onClick={() => link.url && window.open(link.url, '_blank', 'noopener,noreferrer')}
            >
              <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
              Abrir
            </Button>
          </div>
        </div>

        <div className="flex min-w-[150px] flex-col items-center justify-center gap-3 rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5">
          {link.qr_value ? (
            <>
              <QRCodeSVG value={link.qr_value} size={112} bgColor="#ffffff" fgColor="#0f172a" />
              <div className="flex items-center gap-1 text-xs text-muted-foreground">
                <QrCode className="h-3.5 w-3.5" />
                QR pronto para uso
              </div>
            </>
          ) : (
            <div className="text-center text-xs text-muted-foreground">
              QR indisponivel enquanto o link nao estiver ativo.
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
