import { useState } from 'react';
import { CheckCircle2, Copy, ExternalLink, Loader2, RefreshCcw } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { Link } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PublicCheckoutStatusViewModel } from '../mappers/checkoutStatusViewModel';
import { PixDeliveryNotice } from './PixDeliveryNotice';

type PaymentStatusCardProps = {
  status: PublicCheckoutStatusViewModel;
  isFetching?: boolean;
  isAuthenticated?: boolean;
  onRefresh: () => void;
};

const TONE_CLASS: Record<PublicCheckoutStatusViewModel['tone'], string> = {
  idle: 'border-slate-200 bg-white',
  info: 'border-sky-200 bg-sky-50',
  success: 'border-emerald-200 bg-emerald-50',
  warning: 'border-amber-200 bg-amber-50',
  error: 'border-rose-200 bg-rose-50',
};

export function PaymentStatusCard({
  status,
  isFetching = false,
  isAuthenticated = false,
  onRefresh,
}: PaymentStatusCardProps) {
  const [copied, setCopied] = useState(false);

  async function handleCopyPixCode() {
    if (!status.qrCode || !navigator.clipboard?.writeText) {
      return;
    }

    await navigator.clipboard.writeText(status.qrCode);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2500);
  }

  return (
    <Card className={TONE_CLASS[status.tone]}>
      <CardHeader className="space-y-3">
        <div className="flex items-center justify-between gap-3">
          <div className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Acompanhe seu pagamento</p>
            <CardTitle className="text-2xl text-slate-950">{status.title}</CardTitle>
          </div>
          {isFetching ? <Loader2 className="h-5 w-5 animate-spin text-slate-500" /> : null}
        </div>
        <p className="text-sm leading-6 text-slate-600">{status.description}</p>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="rounded-2xl border border-white/70 bg-white/80 p-4">
          <p className="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Status do pagamento</p>
          <p className="mt-2 text-lg font-semibold text-slate-950">{status.statusLabel}</p>
          {status.pixExpiresLabel ? (
            <p className="mt-1 text-sm text-slate-600">Expira em {status.pixExpiresLabel}</p>
          ) : null}
        </div>

        {status.paymentMethod === 'pix' && status.qrCode ? (
          <div className="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
            <div className="flex justify-center rounded-2xl border border-slate-200 bg-white p-4">
              <QRCodeSVG value={status.qrCode} size={180} />
            </div>
            <div className="space-y-4">
              <div className="space-y-2">
                <p className="text-sm font-medium text-slate-950">Codigo Pix</p>
                <p className="break-all rounded-2xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                  {status.qrCode}
                </p>
              </div>
              <div className="flex flex-col gap-3 sm:flex-row">
                <Button onClick={() => void handleCopyPixCode()}>
                  <Copy className="mr-2 h-4 w-4" />
                  {copied ? 'Codigo Pix copiado' : 'Copiar codigo Pix'}
                </Button>
                {status.qrCodeUrl ? (
                  <Button variant="outline" asChild>
                    <a href={status.qrCodeUrl} target="_blank" rel="noreferrer">
                      <ExternalLink className="mr-2 h-4 w-4" />
                      Abrir QR em nova aba
                    </a>
                  </Button>
                ) : null}
              </div>
            </div>
          </div>
        ) : null}

        <PixDeliveryNotice notification={status.whatsappPixNotice} />

        <div className="flex flex-col gap-3 sm:flex-row">
          <Button variant="outline" onClick={onRefresh}>
            <RefreshCcw className="mr-2 h-4 w-4" />
            Atualizar pagamento
          </Button>
          {status.onboardingPath ? (
            <Button asChild>
              <Link to={status.onboardingPath}>
                <CheckCircle2 className="mr-2 h-4 w-4" />
                Abrir meu evento
              </Link>
            </Button>
          ) : null}
          {isAuthenticated ? (
            <Button variant="outline" asChild>
              <Link to="/plans">Ver cobrancas e faturas</Link>
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}
