import { Badge } from '@/components/ui/badge';

export function CheckoutHeroSimple() {
  return (
    <div className="space-y-4">
      <Badge variant="secondary" className="rounded-full px-3 py-1">
        Compra do seu evento
      </Badge>
      <div className="space-y-3">
        <h1 className="max-w-3xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
          Reserve seu pacote em poucos minutos
        </h1>
        <p className="max-w-2xl text-base leading-7 text-slate-600">
          Escolha o pacote ideal, informe seus dados e finalize com Pix ou cartao
          de forma simples e segura.
        </p>
      </div>
      <div className="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
        <Badge variant="outline" className="rounded-full">Pagamento seguro</Badge>
        <Badge variant="outline" className="rounded-full">Confirmacao automatica</Badge>
        <Badge variant="outline" className="rounded-full">Suporte no WhatsApp</Badge>
      </div>
    </div>
  );
}
