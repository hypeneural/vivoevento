import type { PublicEventCheckoutResponse } from '@/lib/api-types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type PixDeliveryNoticeProps = {
  notification: PublicEventCheckoutResponse['checkout']['payment']['whatsapp']['pix_generated'] | null;
};

export function PixDeliveryNotice({ notification }: PixDeliveryNoticeProps) {
  if (!notification) {
    return null;
  }

  return (
    <Alert className="border-sky-200 bg-sky-50 text-sky-950">
      <AlertTitle>Tambem enviamos este Pix para o seu WhatsApp</AlertTitle>
      <AlertDescription className="space-y-1">
        {notification.recipient_phone ? (
          <p>Numero usado: {notification.recipient_phone}</p>
        ) : null}
        {notification.pix_button_enabled ? (
          <p>A mensagem tambem incluiu o botao de copiar o Pix.</p>
        ) : (
          <p>O codigo e o QR Code seguem disponiveis aqui na tela.</p>
        )}
      </AlertDescription>
    </Alert>
  );
}
