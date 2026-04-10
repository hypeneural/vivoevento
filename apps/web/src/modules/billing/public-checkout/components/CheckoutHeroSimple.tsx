import { Badge } from '@/components/ui/badge';

export function CheckoutHeroSimple() {
  return (
    <div data-testid="public-checkout-hero" className="space-y-2 sm:space-y-4">
      <Badge variant="secondary" className="rounded-full px-3 py-1">
        Compra do seu evento
      </Badge>
      <div className="space-y-2 sm:space-y-3">
        <h1 className="max-w-3xl text-2xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
          Contrate seu evento em poucos minutos
        </h1>
        <p className="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base sm:leading-7">
          Escolha o pacote, informe os dados principais e finalize com Pix ou
          cartao de forma simples e segura.
        </p>
      </div>
      <div data-testid="public-checkout-trust-row" className="hidden flex-wrap gap-2 text-xs font-medium text-slate-600 sm:flex">
        <Badge variant="outline" className="rounded-full">Pagamento seguro</Badge>
        <Badge variant="outline" className="rounded-full">Confirmacao automatica</Badge>
        <Badge variant="outline" className="rounded-full">Suporte no WhatsApp</Badge>
      </div>
    </div>
  );
}
