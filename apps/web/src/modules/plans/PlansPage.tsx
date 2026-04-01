import { useState } from 'react';
import { motion } from 'framer-motion';
import { Check, CreditCard, Zap } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { mockPlans } from '@/shared/mock/data';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';

export default function PlansPage() {
  const { toast } = useToast();

  const subscriptions = [
    { id: 's-1', partner: 'Studio Lumière', plan: 'Pro Parceiro', status: 'active', nextBill: '2026-05-01', amount: 297 },
    { id: 's-2', partner: 'Agência Celebra', plan: 'Enterprise', status: 'active', nextBill: '2026-05-01', amount: 897 },
    { id: 's-3', partner: 'Eventos Prime', plan: 'Evento Play', status: 'active', nextBill: '2026-04-25', amount: 97 },
    { id: 's-4', partner: 'Click Moment', plan: 'Teste Grátis', status: 'trial', nextBill: '-', amount: 0 },
  ];

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Planos & Billing" description="Gerencie planos, assinaturas e cobranças" />

      <Tabs defaultValue="plans">
        <TabsList className="bg-muted/50">
          <TabsTrigger value="plans">Planos</TabsTrigger>
          <TabsTrigger value="subscriptions">Assinaturas</TabsTrigger>
          <TabsTrigger value="billing">Cobranças</TabsTrigger>
        </TabsList>

        <TabsContent value="plans" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {mockPlans.map(plan => (
              <div key={plan.id} className={`glass rounded-xl p-5 relative card-hover ${plan.popular ? 'ring-1 ring-primary glow-primary' : ''}`}>
                {plan.popular && <Badge className="absolute -top-2 left-1/2 -translate-x-1/2 gradient-primary border-0 text-[10px]">Mais Popular</Badge>}
                <h3 className="text-lg font-bold">{plan.name}</h3>
                <div className="mt-2 mb-4">
                  <span className="text-3xl font-bold">R$ {plan.price}</span>
                  <span className="text-sm text-muted-foreground">/{plan.cycle === 'monthly' ? 'mês' : plan.cycle === 'yearly' ? 'ano' : 'evento'}</span>
                </div>
                <div className="space-y-2 mb-5">
                  {plan.features.map(f => (
                    <div key={f} className="flex items-center gap-2 text-sm">
                      <Check className="h-3.5 w-3.5 text-success shrink-0" />
                      <span className="text-muted-foreground">{f}</span>
                    </div>
                  ))}
                </div>
                <div className="flex flex-wrap gap-1 mb-4">
                  {plan.modules.map(m => <Badge key={m} variant="outline" className="text-[10px]">{m}</Badge>)}
                </div>
                <Button className={`w-full ${plan.popular ? 'gradient-primary border-0' : ''}`} variant={plan.popular ? 'default' : 'outline'} onClick={() => toast({ title: 'Plano selecionado', description: `${plan.name} (mock)` })}>
                  Selecionar
                </Button>
              </div>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="subscriptions" className="mt-6">
          <div className="glass rounded-xl overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="border-border/50">
                  <TableHead>Parceiro</TableHead>
                  <TableHead>Plano</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="hidden md:table-cell">Próxima Cobrança</TableHead>
                  <TableHead>Valor</TableHead>
                  <TableHead className="w-20"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {subscriptions.map(s => (
                  <TableRow key={s.id} className="border-border/30">
                    <TableCell className="font-medium text-sm">{s.partner}</TableCell>
                    <TableCell><Badge variant="outline" className="text-xs">{s.plan}</Badge></TableCell>
                    <TableCell>
                      <Badge variant="outline" className={`text-xs ${s.status === 'active' ? 'text-success bg-success/10 border-success/20' : 'text-warning bg-warning/10 border-warning/20'}`}>
                        {s.status === 'active' ? 'Ativa' : 'Trial'}
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden md:table-cell text-sm text-muted-foreground">{s.nextBill}</TableCell>
                    <TableCell className="text-sm font-medium">R$ {s.amount}</TableCell>
                    <TableCell><Button variant="ghost" size="sm">Gerenciar</Button></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </TabsContent>

        <TabsContent value="billing" className="mt-6">
          <div className="glass rounded-xl overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="border-border/50">
                  <TableHead>Data</TableHead>
                  <TableHead>Parceiro</TableHead>
                  <TableHead>Descrição</TableHead>
                  <TableHead>Valor</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {[
                  { date: '01/04/2026', partner: 'Studio Lumière', desc: 'Pro Parceiro - Abril', amount: 297, status: 'paid' },
                  { date: '01/04/2026', partner: 'Agência Celebra', desc: 'Enterprise - Abril', amount: 897, status: 'paid' },
                  { date: '25/03/2026', partner: 'Eventos Prime', desc: 'Evento Play - Workshop', amount: 97, status: 'paid' },
                  { date: '01/03/2026', partner: 'Studio Lumière', desc: 'Pro Parceiro - Março', amount: 297, status: 'paid' },
                  { date: '01/03/2026', partner: 'Agência Celebra', desc: 'Enterprise - Março', amount: 897, status: 'paid' },
                ].map((b, i) => (
                  <TableRow key={i} className="border-border/30">
                    <TableCell className="text-sm text-muted-foreground">{b.date}</TableCell>
                    <TableCell className="text-sm font-medium">{b.partner}</TableCell>
                    <TableCell className="text-sm text-muted-foreground">{b.desc}</TableCell>
                    <TableCell className="text-sm font-medium">R$ {b.amount}</TableCell>
                    <TableCell><Badge variant="outline" className="text-xs text-success bg-success/10 border-success/20">Pago</Badge></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
