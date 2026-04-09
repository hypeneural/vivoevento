import { QrCode } from 'lucide-react';

type PixPaymentPanelProps = {
  amountLabel?: string | null;
};

export function PixPaymentPanel({ amountLabel }: PixPaymentPanelProps) {
  return (
    <div className="rounded-2xl border border-sky-200 bg-sky-50 p-5">
      <div className="flex items-start gap-3">
        <div className="rounded-full bg-white p-2 shadow-sm">
          <QrCode className="h-5 w-5 text-sky-600" />
        </div>
        <div className="space-y-2">
          <h3 className="text-base font-semibold text-slate-950">Pague com Pix</h3>
          <p className="text-sm leading-6 text-slate-600">
            Voce recebe o QR Code na hora e acompanha a confirmacao aqui mesmo.
          </p>
          {amountLabel ? (
            <p className="text-sm font-medium text-slate-950">Valor desta compra: {amountLabel}</p>
          ) : null}
        </div>
      </div>
    </div>
  );
}
