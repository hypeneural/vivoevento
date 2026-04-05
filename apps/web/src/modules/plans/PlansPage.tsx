import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  AlertTriangle,
  Building2,
  CalendarClock,
  Check,
  CreditCard,
  Loader2,
  Receipt,
  Sparkles,
} from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useToast } from '@/hooks/use-toast';
import type { ApiBillingInvoice, ApiBillingSubscription, ApiPlan, ApiPlanFeature } from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';

import { plansService } from './api';

const PLAN_FEATURE_LABELS: Record<string, (value: string | null) => string | null> = {
  'events.max_active': (value) => value ? `Ate ${value} eventos ativos` : null,
  'media.retention_days': (value) => value ? `Retencao de ${value} dias` : null,
  'play.enabled': (value) => value === 'true' ? 'Experiencias Play' : null,
  'wall.enabled': (value) => value === 'true' ? 'Telao ao vivo' : null,
  'channels.whatsapp': (value) => value === 'true' ? 'Ingestao via WhatsApp' : null,
  'white_label.enabled': (value) => value === 'true' ? 'White label' : null,
};

function formatMoney(amountCents: number, currency = 'BRL') {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format(amountCents / 100);
}

function formatDate(value?: string | null) {
  if (!value) {
    return 'Sem data';
  }

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function formatCycle(cycle?: string | null) {
  switch (cycle) {
    case 'yearly':
      return 'ano';
    case 'monthly':
      return 'mes';
    default:
      return cycle || 'ciclo';
  }
}

function formatSubscriptionStatus(status?: string | null) {
  switch (status) {
    case 'active':
      return 'Ativa';
    case 'trialing':
    case 'trial':
      return 'Trial';
    case 'past_due':
      return 'Em atraso';
    case 'canceled':
      return 'Cancelada';
    case 'expired':
      return 'Expirada';
    case 'paid':
      return 'Pago';
    case 'open':
      return 'Em aberto';
    case 'void':
      return 'Cancelada';
    case 'refunded':
      return 'Estornada';
    case 'failed':
      return 'Falhou';
    default:
      return status || 'Sem assinatura';
  }
}

function formatSubscriptionBadgeLabel(subscription?: ApiBillingSubscription | null) {
  if (!subscription) {
    return 'Sem assinatura';
  }

  if (subscription.cancel_at_period_end) {
    return 'Cancelamento agendado';
  }

  return formatSubscriptionStatus(subscription.status);
}

function subscriptionStatusClasses(status?: string | null) {
  switch (status) {
    case 'active':
    case 'paid':
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200';
    case 'trialing':
    case 'trial':
      return 'border-amber-500/30 bg-amber-500/10 text-amber-100';
    case 'past_due':
    case 'failed':
      return 'border-rose-500/30 bg-rose-500/10 text-rose-200';
    case 'open':
      return 'border-sky-500/30 bg-sky-500/10 text-sky-100';
    case 'refunded':
      return 'border-orange-500/30 bg-orange-500/10 text-orange-100';
    case 'canceled':
    case 'expired':
    case 'void':
      return 'border-border/60 bg-muted/50 text-muted-foreground';
    default:
      return 'border-border/60 bg-muted/50 text-muted-foreground';
  }
}

function subscriptionBadgeClasses(subscription?: ApiBillingSubscription | null) {
  if (subscription?.cancel_at_period_end) {
    return 'border-amber-500/30 bg-amber-500/10 text-amber-100';
  }

  return subscriptionStatusClasses(subscription?.status);
}

function getDefaultPrice(plan: ApiPlan) {
  return plan.prices.find((price) => price.is_default) ?? plan.prices[0] ?? null;
}

function getPlanFeatureLabels(plan: ApiPlan) {
  return plan.features
    .map((feature) => mapPlanFeature(feature))
    .filter((value): value is string => Boolean(value));
}

function mapPlanFeature(feature: ApiPlanFeature) {
  const formatter = PLAN_FEATURE_LABELS[feature.feature_key];

  if (formatter) {
    return formatter(feature.feature_value);
  }

  if (feature.feature_value === 'true') {
    return feature.feature_key;
  }

  return null;
}

function getPlanModules(plan: ApiPlan) {
  const featureMap = new Map(plan.features.map((feature) => [feature.feature_key, feature.feature_value]));

  return [
    featureMap.get('wall.enabled') === 'true' ? 'Wall' : null,
    featureMap.get('play.enabled') === 'true' ? 'Play' : null,
    featureMap.get('channels.whatsapp') === 'true' ? 'WhatsApp' : null,
    featureMap.get('white_label.enabled') === 'true' ? 'White label' : null,
  ].filter((module): module is string => Boolean(module));
}

function getInvoiceDescription(invoice: ApiBillingInvoice) {
  if (invoice.plan?.name) {
    return invoice.plan.name;
  }

  if (invoice.package?.name && invoice.event?.title) {
    return `${invoice.package.name} · ${invoice.event.title}`;
  }

  if (invoice.package?.name) {
    return invoice.package.name;
  }

  if (invoice.event?.title) {
    return invoice.event.title;
  }

  return invoice.order?.mode === 'subscription' ? 'Assinatura da conta' : 'Cobranca do evento';
}

function BillingSummaryCard({
  icon: Icon,
  label,
  title,
  description,
}: {
  icon: typeof Building2;
  label: string;
  title: string;
  description: string;
}) {
  return (
    <div className="glass rounded-2xl border border-border/60 p-5">
      <div className="flex items-center gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-border/60 bg-background/40">
          <Icon className="h-5 w-5 text-primary" />
        </div>
        <div className="min-w-0">
          <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
          <p className="truncate text-base font-semibold text-foreground">{title}</p>
        </div>
      </div>
      <p className="mt-4 text-sm text-muted-foreground">{description}</p>
    </div>
  );
}

function PlansSkeleton() {
  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      {Array.from({ length: 3 }).map((_, index) => (
        <div key={index} className="glass rounded-2xl border border-border/60 p-5">
          <Skeleton className="h-5 w-28" />
          <Skeleton className="mt-4 h-9 w-40" />
          <Skeleton className="mt-6 h-4 w-full" />
          <Skeleton className="mt-2 h-4 w-5/6" />
          <Skeleton className="mt-2 h-4 w-3/4" />
          <Skeleton className="mt-8 h-10 w-full" />
        </div>
      ))}
    </div>
  );
}

function SectionError({ title, description }: { title: string; description: string }) {
  return (
    <div className="glass rounded-2xl border border-destructive/30 px-5 py-10">
      <p className="text-base font-semibold text-destructive">{title}</p>
      <p className="mt-2 text-sm text-destructive/80">{description}</p>
    </div>
  );
}

function InvoicesMobileList({ invoices }: { invoices: ApiBillingInvoice[] }) {
  return (
    <div className="space-y-3 md:hidden">
      {invoices.map((invoice) => (
        <div key={invoice.id} className="glass rounded-2xl border border-border/60 p-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-foreground">{getInvoiceDescription(invoice)}</p>
              <p className="mt-1 text-xs text-muted-foreground">
                {invoice.invoice_number || `Invoice #${invoice.id}`}
              </p>
            </div>
            <Badge variant="outline" className={subscriptionStatusClasses(invoice.status)}>
              {formatSubscriptionStatus(invoice.status)}
            </Badge>
          </div>
          <div className="mt-4 grid gap-2 text-sm text-muted-foreground">
            <div className="flex items-center justify-between gap-3">
              <span>Valor</span>
              <span className="font-medium text-foreground">{formatMoney(invoice.amount_cents, invoice.currency)}</span>
            </div>
            <div className="flex items-center justify-between gap-3">
              <span>Emissao</span>
              <span>{formatDate(invoice.issued_at)}</span>
            </div>
            <div className="flex items-center justify-between gap-3">
              <span>Pagamento</span>
              <span>{formatDate(invoice.paid_at || invoice.due_at)}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

function SubscriptionPanel({
  organizationName,
  subscription,
  canManageSubscription,
  isCancelling,
  onCancel,
}: {
  organizationName: string;
  subscription: ApiBillingSubscription | null;
  canManageSubscription: boolean;
  isCancelling: boolean;
  onCancel: (reason?: string) => void;
}) {
  const [confirmOpen, setConfirmOpen] = useState(false);

  if (!subscription) {
    return (
      <div className="glass rounded-2xl border border-border/60">
        <EmptyState
          icon={CreditCard}
          title="Nenhuma assinatura ativa"
          description="Escolha um plano na aba de catalogo para ativar o billing da conta atual."
        />
      </div>
    );
  }

  const enabledFeatures = Object.entries(subscription.features ?? {})
    .filter(([, value]) => value === 'true')
    .map(([key]) => key);
  const canCancelSubscription = canManageSubscription
    && !subscription.cancel_at_period_end
    && ['active', 'trialing', 'trial', 'past_due'].includes(subscription.status || '');
  const accessUntil = subscription.cancellation_effective_at || subscription.ends_at || subscription.renews_at;
  const isCanceledPendingEnd = subscription.cancel_at_period_end && Boolean(accessUntil);

  return (
    <>
      <div className="grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
        <div className="glass rounded-2xl border border-border/60 p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Conta atual</p>
              <h3 className="mt-2 text-xl font-semibold text-foreground">{organizationName}</h3>
              <p className="mt-2 text-sm text-muted-foreground">
                Billing recorrente da organizacao. O evento pode ter grant proprio, mas esta tela representa a conta.
              </p>
            </div>
            <Badge variant="outline" className={subscriptionBadgeClasses(subscription)}>
              {formatSubscriptionBadgeLabel(subscription)}
            </Badge>
          </div>

          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            <div className="rounded-2xl border border-border/60 bg-background/30 p-4">
              <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Plano</p>
              <p className="mt-2 text-lg font-semibold text-foreground">{subscription.plan_name || 'Sem plano'}</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Ciclo {formatCycle(subscription.billing_cycle)}
              </p>
            </div>
            <div className="rounded-2xl border border-border/60 bg-background/30 p-4">
              <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">
                {isCanceledPendingEnd ? 'Acesso ate' : subscription.status === 'canceled' ? 'Encerrada em' : 'Proxima renovacao'}
              </p>
              <p className="mt-2 text-lg font-semibold text-foreground">
                {formatDate(isCanceledPendingEnd ? accessUntil : subscription.status === 'canceled' ? subscription.canceled_at : subscription.renews_at)}
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                Inicio em {formatDate(subscription.starts_at)}
              </p>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap items-center gap-3">
            {canCancelSubscription ? (
              <Button
                variant="destructive"
                disabled={isCancelling}
                onClick={() => setConfirmOpen(true)}
              >
                {isCancelling ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <AlertTriangle className="mr-2 h-4 w-4" />}
                Cancelar ao fim do ciclo
              </Button>
            ) : null}

            {!canManageSubscription ? (
              <p className="text-sm text-muted-foreground">
                Sua sessao atual pode visualizar a assinatura, mas nao pode cancelar ou contratar planos.
              </p>
            ) : null}

            {subscription.status === 'canceled' ? (
              <p className="text-sm text-muted-foreground">
                {isCanceledPendingEnd
                  ? `A assinatura nao renova mais automaticamente. A cobertura da conta segue ativa ate ${formatDate(accessUntil)}.`
                  : 'A assinatura da conta ja foi encerrada e nao possui mais renovacao automatica.'}
              </p>
            ) : null}
          </div>
        </div>

        <div className="glass rounded-2xl border border-border/60 p-5">
          <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Features do plano</p>
          {enabledFeatures.length > 0 ? (
            <div className="mt-4 flex flex-wrap gap-2">
              {enabledFeatures.map((feature) => (
                <Badge key={feature} variant="secondary" className="border-border/60 bg-background/40 text-foreground">
                  {feature}
                </Badge>
              ))}
            </div>
          ) : (
            <p className="mt-4 text-sm text-muted-foreground">Nenhuma feature foi retornada para o plano vinculado a esta assinatura.</p>
          )}
        </div>
      </div>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancelar ao fim do ciclo</AlertDialogTitle>
            <AlertDialogDescription>
              Esta acao encerra a renovacao automatica da assinatura da organizacao atual. Por padrao, a cobertura
              comercial da conta segue valida ate o fim do ciclo atual. Eventos com grant proprio continuam respeitando
              sua propria origem comercial.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isCancelling}>Voltar</AlertDialogCancel>
            <AlertDialogAction
              disabled={isCancelling}
              onClick={(event) => {
                event.preventDefault();
                onCancel('Cancelamento ao fim do ciclo solicitado pelo painel de billing.');
                setConfirmOpen(false);
              }}
            >
              {isCancelling ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Confirmar cancelamento
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

export default function PlansPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { meOrganization, can } = useAuth();

  const canManageBilling = can('billing.manage');
  const canPurchaseBilling = can('billing.purchase') || canManageBilling;
  const canManageSubscription = can('billing.manage_subscription') || canManageBilling;
  const canViewBilling = can('billing.view') || canPurchaseBilling || canManageSubscription;

  const catalogQuery = useQuery({
    queryKey: [...queryKeys.plans.all(), 'catalog'],
    queryFn: () => plansService.listCatalog(),
    enabled: canViewBilling,
  });

  const subscriptionQuery = useQuery({
    queryKey: [...queryKeys.plans.billing(), 'subscription'],
    queryFn: () => plansService.getCurrentSubscription(),
    enabled: canViewBilling,
  });

  const invoicesQuery = useQuery({
    queryKey: [...queryKeys.plans.billing(), 'invoices', 1],
    queryFn: () => plansService.listInvoices(1),
    enabled: canViewBilling,
  });

  const checkoutMutation = useMutation({
    mutationFn: plansService.checkout,
    onSuccess: async (result) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.plans.billing() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.auth.me() }),
      ]);

      toast({
        title: 'Checkout de assinatura iniciado',
        description: result.checkout.checkout_url
          ? `Plano ${result.plan_name} pronto para pagamento.`
          : `Plano ${result.plan_name} ativado com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao iniciar checkout',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const cancelSubscriptionMutation = useMutation({
    mutationFn: plansService.cancelSubscription,
    onSuccess: async (result) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.plans.billing() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.auth.me() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.events.all() }),
      ]);

      toast({
        title: result.cancel_effective === 'immediately' ? 'Assinatura cancelada' : 'Cancelamento agendado',
        description: result.access_until
          ? `${result.message} A conta segue coberta ate ${formatDate(result.access_until)}.`
          : result.message,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao cancelar assinatura',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const plans = catalogQuery.data ?? [];
  const currentSubscription = subscriptionQuery.data ?? null;
  const invoices = invoicesQuery.data?.data ?? [];
  const invoicesMeta = invoicesQuery.data?.meta;

  const currentPlanKey = currentSubscription?.plan_key ?? null;
  const latestInvoice = invoices[0] ?? null;

  const plansWithPricing = useMemo(() => (
    plans.map((plan) => ({
      plan,
      defaultPrice: getDefaultPrice(plan),
      featureLabels: getPlanFeatureLabels(plan),
      modules: getPlanModules(plan),
    }))
  ), [plans]);

  if (!canViewBilling) {
    return (
      <EmptyState
        icon={CreditCard}
        title="Acesso indisponivel"
        description="Sua sessao atual nao possui permissao para visualizar billing."
      />
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Planos e Billing"
        description="Catalogo, assinatura da conta atual e historico financeiro reais."
      />

      <div className="grid gap-4 md:grid-cols-3">
        <BillingSummaryCard
          icon={Building2}
          label="Conta"
          title={meOrganization?.name || 'Organizacao atual'}
          description="Esta tela representa o billing da conta. Entitlements especificos de evento aparecem nas paginas de evento."
        />
        <BillingSummaryCard
          icon={Sparkles}
          label="Assinatura"
          title={currentSubscription?.plan_name || 'Sem assinatura ativa'}
          description={currentSubscription
            ? currentSubscription.cancel_at_period_end
              ? `${formatSubscriptionBadgeLabel(currentSubscription)} · acesso ate ${formatDate(currentSubscription.cancellation_effective_at)}`
              : `${formatSubscriptionBadgeLabel(currentSubscription)} · renovacao ${formatDate(currentSubscription.renews_at)}`
            : 'Escolha um plano recorrente para ativar a conta.'}
        />
        <BillingSummaryCard
          icon={Receipt}
          label="Ultima cobranca"
          title={latestInvoice ? formatMoney(latestInvoice.amount_cents, latestInvoice.currency) : 'Sem invoices'}
          description={latestInvoice
            ? `${getInvoiceDescription(latestInvoice)} · ${formatDate(latestInvoice.paid_at || latestInvoice.issued_at)}`
            : 'O historico financeiro aparece aqui assim que a conta gerar a primeira invoice.'}
        />
      </div>

      <Tabs defaultValue="plans">
        <TabsList className="bg-muted/50">
          <TabsTrigger value="plans">Catalogo</TabsTrigger>
          <TabsTrigger value="subscription">Assinatura</TabsTrigger>
          <TabsTrigger value="billing">Cobrancas</TabsTrigger>
        </TabsList>

        <TabsContent value="plans" className="mt-6">
          {catalogQuery.isLoading ? <PlansSkeleton /> : null}

          {catalogQuery.isError ? (
            <SectionError
              title="Falha ao carregar o catalogo"
              description="Nao foi possivel consultar os planos reais agora. Tente novamente em instantes."
            />
          ) : null}

          {!catalogQuery.isLoading && !catalogQuery.isError && plansWithPricing.length === 0 ? (
            <div className="glass rounded-2xl border border-border/60">
              <EmptyState
                icon={CreditCard}
                title="Nenhum plano disponivel"
                description="O catalogo ainda nao retornou planos ativos para esta conta."
              />
            </div>
          ) : null}

          {!catalogQuery.isLoading && !catalogQuery.isError ? (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {plansWithPricing.map(({ plan, defaultPrice, featureLabels, modules }) => {
                const isCurrentPlan = Boolean(currentPlanKey && currentPlanKey === plan.code);
                const yearlyPrice = plan.prices.find((price) => price.billing_cycle === 'yearly');

                return (
                  <div
                    key={plan.id}
                    className={`glass rounded-2xl border p-5 transition-colors ${
                      isCurrentPlan ? 'border-primary/60 shadow-[0_0_0_1px_rgba(255,255,255,0.06)]' : 'border-border/60'
                    }`}
                  >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                          {plan.description || 'Plano recorrente para operacao da conta.'}
                        </p>
                      </div>
                      {isCurrentPlan ? (
                        <Badge className="border-0 bg-primary text-primary-foreground">Plano atual</Badge>
                      ) : null}
                    </div>

                    <div className="mt-6 flex items-end gap-2">
                      <span className="text-3xl font-bold text-foreground">
                        {defaultPrice ? formatMoney(defaultPrice.amount_cents, defaultPrice.currency) : 'Sob consulta'}
                      </span>
                      <span className="pb-1 text-sm text-muted-foreground">
                        {defaultPrice ? `/${formatCycle(defaultPrice.billing_cycle)}` : ''}
                      </span>
                    </div>

                    {yearlyPrice ? (
                      <p className="mt-2 text-xs text-muted-foreground">
                        Opcao anual: {formatMoney(yearlyPrice.amount_cents, yearlyPrice.currency)}
                      </p>
                    ) : null}

                    <div className="mt-6 space-y-2">
                      {featureLabels.map((feature) => (
                        <div key={feature} className="flex items-start gap-2 text-sm text-muted-foreground">
                          <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-300" />
                          <span>{feature}</span>
                        </div>
                      ))}
                    </div>

                    {modules.length > 0 ? (
                      <div className="mt-5 flex flex-wrap gap-2">
                        {modules.map((module) => (
                          <Badge key={module} variant="outline" className="border-border/60 bg-background/30">
                            {module}
                          </Badge>
                        ))}
                      </div>
                    ) : null}

                    <Button
                      className="mt-6 w-full"
                      variant={isCurrentPlan ? 'secondary' : 'default'}
                      disabled={!defaultPrice || isCurrentPlan || checkoutMutation.isPending || !canPurchaseBilling}
                      onClick={() => {
                        if (!defaultPrice) {
                          return;
                        }

                        checkoutMutation.mutate({
                          plan_id: plan.id,
                          billing_cycle: defaultPrice.billing_cycle === 'yearly' ? 'yearly' : 'monthly',
                        });
                      }}
                    >
                      {checkoutMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                      {isCurrentPlan ? 'Plano atual' : canPurchaseBilling ? 'Ativar plano' : 'Sem permissao para contratar'}
                    </Button>
                  </div>
                );
              })}
            </div>
          ) : null}
        </TabsContent>

        <TabsContent value="subscription" className="mt-6">
          {subscriptionQuery.isLoading ? <PlansSkeleton /> : null}
          {subscriptionQuery.isError ? (
            <SectionError
              title="Falha ao carregar a assinatura"
              description="A assinatura atual da conta nao pode ser consultada agora."
            />
          ) : null}
          {!subscriptionQuery.isLoading && !subscriptionQuery.isError ? (
            <SubscriptionPanel
              organizationName={meOrganization?.name || 'Organizacao atual'}
              subscription={currentSubscription}
              canManageSubscription={canManageSubscription}
              isCancelling={cancelSubscriptionMutation.isPending}
              onCancel={(reason) => cancelSubscriptionMutation.mutate({
                effective: 'period_end',
                reason,
              })}
            />
          ) : null}
        </TabsContent>

        <TabsContent value="billing" className="mt-6">
          {invoicesQuery.isLoading ? (
            <PlansSkeleton />
          ) : null}

          {invoicesQuery.isError ? (
            <SectionError
              title="Falha ao carregar as cobrancas"
              description="O historico financeiro da conta nao pode ser carregado agora."
            />
          ) : null}

          {!invoicesQuery.isLoading && !invoicesQuery.isError && invoices.length === 0 ? (
            <div className="glass rounded-2xl border border-border/60">
              <EmptyState
                icon={Receipt}
                title="Nenhuma cobranca registrada"
                description="As invoices reais da conta vao aparecer aqui assim que houver checkout ou renovacao."
              />
            </div>
          ) : null}

          {!invoicesQuery.isLoading && !invoicesQuery.isError && invoices.length > 0 ? (
            <>
              <InvoicesMobileList invoices={invoices} />

              <div className="hidden overflow-hidden rounded-2xl border border-border/60 glass md:block">
                <Table>
                  <TableHeader>
                    <TableRow className="border-border/50">
                      <TableHead>Invoice</TableHead>
                      <TableHead>Descricao</TableHead>
                      <TableHead>Valor</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Emissao</TableHead>
                      <TableHead>Pagamento</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {invoices.map((invoice) => (
                      <TableRow key={invoice.id} className="border-border/30">
                        <TableCell className="font-medium text-sm text-foreground">
                          {invoice.invoice_number || `Invoice #${invoice.id}`}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {getInvoiceDescription(invoice)}
                        </TableCell>
                        <TableCell className="text-sm font-medium text-foreground">
                          {formatMoney(invoice.amount_cents, invoice.currency)}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className={subscriptionStatusClasses(invoice.status)}>
                            {formatSubscriptionStatus(invoice.status)}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">{formatDate(invoice.issued_at)}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDate(invoice.paid_at || invoice.due_at)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="flex items-center justify-between gap-3 rounded-2xl border border-border/60 bg-background/30 px-4 py-3 text-sm text-muted-foreground">
                <div className="flex items-center gap-2">
                  <CalendarClock className="h-4 w-4" />
                  <span>Historico real de invoices da conta atual.</span>
                </div>
                <span>
                  {invoicesMeta?.total ? `${invoicesMeta.total} registro(s)` : `${invoices.length} registro(s)`}
                </span>
              </div>
            </>
          ) : null}
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
