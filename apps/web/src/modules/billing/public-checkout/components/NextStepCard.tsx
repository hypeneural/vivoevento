import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type NextStepCardProps = {
  currentStep: 'package' | 'details' | 'payment' | 'status';
};

const STEP_COPY: Record<NextStepCardProps['currentStep'], string> = {
  package: 'Escolha o pacote ideal para avancar para os seus dados.',
  details: 'Preencha so o essencial para destravar a etapa de pagamento.',
  payment: 'Escolha Pix ou cartao para concluir a compra com seguranca.',
  status: 'Acompanhe aqui a confirmacao do pagamento e os proximos passos.',
};

export function NextStepCard({ currentStep }: NextStepCardProps) {
  return (
    <Card className="border-slate-200 bg-white">
      <CardHeader>
        <CardTitle className="text-lg">Proximo passo</CardTitle>
      </CardHeader>
      <CardContent className="text-sm text-slate-600">
        {STEP_COPY[currentStep]}
      </CardContent>
    </Card>
  );
}
