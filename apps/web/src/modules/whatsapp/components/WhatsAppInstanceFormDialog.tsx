import { useEffect } from 'react';
import { useForm } from 'react-hook-form';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

import type {
  WhatsAppInstanceFormPayload,
  WhatsAppInstanceItem,
  WhatsAppProviderKey,
} from '../types';
import { WHATSAPP_PROVIDER_OPTIONS } from '../types';

type FormMode = 'create' | 'edit';
type EvolutionAuthType = 'global_apikey' | 'instance_apikey';
type EvolutionIntegration = 'WHATSAPP-BAILEYS' | 'WHATSAPP-BUSINESS';

interface WhatsAppInstanceFormDialogProps {
  open: boolean;
  mode: FormMode;
  instance: WhatsAppInstanceItem | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: WhatsAppInstanceFormPayload) => void;
}

interface WhatsAppInstanceFormValues {
  provider_key: WhatsAppProviderKey;
  name: string;
  instance_name: string;
  phone_number: string;
  is_active: boolean;
  is_default: boolean;
  notes: string;
  timeout_seconds: string;
  webhook_url: string;
  tags: string;
  zapi_instance_id: string;
  zapi_instance_token: string;
  zapi_client_token: string;
  zapi_base_url: string;
  evolution_server_url: string;
  evolution_auth_type: EvolutionAuthType;
  evolution_api_key: string;
  evolution_integration: EvolutionIntegration;
  evolution_external_instance_name: string;
  evolution_instance_token: string;
  evolution_phone_e164: string;
}

const DEFAULT_VALUES: WhatsAppInstanceFormValues = {
  provider_key: 'zapi',
  name: '',
  instance_name: '',
  phone_number: '',
  is_active: true,
  is_default: false,
  notes: '',
  timeout_seconds: '',
  webhook_url: '',
  tags: '',
  zapi_instance_id: '',
  zapi_instance_token: '',
  zapi_client_token: '',
  zapi_base_url: '',
  evolution_server_url: '',
  evolution_auth_type: 'global_apikey',
  evolution_api_key: '',
  evolution_integration: 'WHATSAPP-BAILEYS',
  evolution_external_instance_name: '',
  evolution_instance_token: '',
  evolution_phone_e164: '',
};

function buildDefaultValues(instance: WhatsAppInstanceItem | null): WhatsAppInstanceFormValues {
  if (!instance) {
    return DEFAULT_VALUES;
  }

  return {
    provider_key: instance.provider_key,
    name: instance.name ?? '',
    instance_name: instance.instance_name ?? '',
    phone_number: instance.phone_number ?? '',
    is_active: instance.is_active,
    is_default: instance.is_default,
    notes: instance.notes ?? '',
    timeout_seconds: instance.settings.timeout_seconds ? String(instance.settings.timeout_seconds) : '',
    webhook_url: instance.settings.webhook_url ?? '',
    tags: (instance.settings.tags ?? []).join(', '),
    zapi_instance_id: instance.provider_config.instance_id ?? '',
    zapi_instance_token: '',
    zapi_client_token: '',
    zapi_base_url: instance.provider_config.base_url ?? '',
    evolution_server_url: instance.provider_config.server_url ?? '',
    evolution_auth_type: instance.provider_config.auth_type ?? 'global_apikey',
    evolution_api_key: '',
    evolution_integration: instance.provider_config.integration ?? 'WHATSAPP-BAILEYS',
    evolution_external_instance_name: instance.provider_config.external_instance_name ?? '',
    evolution_instance_token: '',
    evolution_phone_e164: instance.provider_config.phone_e164 ?? '',
  };
}

function parseTags(raw: string): string[] {
  return raw
    .split(/[\n,]/)
    .map((entry) => entry.trim())
    .filter(Boolean);
}

function normalizeOptional(value: string): string | null {
  const normalized = value.trim();

  return normalized !== '' ? normalized : null;
}

export function WhatsAppInstanceFormDialog({
  open,
  mode,
  instance,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: WhatsAppInstanceFormDialogProps) {
  const form = useForm<WhatsAppInstanceFormValues>({
    defaultValues: buildDefaultValues(instance),
  });

  const providerKey = form.watch('provider_key');

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset(buildDefaultValues(instance));
  }, [form, instance, open]);

  const handleSubmit = form.handleSubmit((values) => {
    if (values.provider_key === 'zapi') {
      if (!values.zapi_instance_id.trim()) {
        form.setError('zapi_instance_id', { message: 'Informe o instance ID.' });
        return;
      }

      if (mode === 'create' && !values.zapi_instance_token.trim()) {
        form.setError('zapi_instance_token', { message: 'Informe o token da instancia.' });
        return;
      }
    }

    if (values.provider_key === 'evolution') {
      if (!values.evolution_server_url.trim()) {
        form.setError('evolution_server_url', { message: 'Informe a URL do servidor.' });
        return;
      }

      if (mode === 'create' && !values.evolution_api_key.trim()) {
        form.setError('evolution_api_key', { message: 'Informe a API key.' });
        return;
      }

      if (!values.evolution_external_instance_name.trim()) {
        form.setError('evolution_external_instance_name', { message: 'Informe o nome externo da instancia.' });
        return;
      }
    }

    const settings: WhatsAppInstanceFormPayload['settings'] = {};
    const timeoutSeconds = Number(values.timeout_seconds);
    const tags = parseTags(values.tags);

    if (Number.isFinite(timeoutSeconds) && timeoutSeconds > 0) {
      settings.timeout_seconds = timeoutSeconds;
    }

    if (values.webhook_url.trim()) {
      settings.webhook_url = values.webhook_url.trim();
    }

    if (tags.length > 0) {
      settings.tags = tags;
    }

    const payload: WhatsAppInstanceFormPayload = {
      provider_key: values.provider_key,
      name: values.name.trim(),
      instance_name: values.instance_name.trim(),
      phone_number: normalizeOptional(values.phone_number),
      is_active: values.is_active,
      is_default: values.is_default,
      notes: normalizeOptional(values.notes),
      settings: Object.keys(settings).length > 0 ? settings : undefined,
      provider_config: values.provider_key === 'zapi'
        ? {
            instance_id: values.zapi_instance_id.trim(),
            instance_token: values.zapi_instance_token.trim(),
            client_token: normalizeOptional(values.zapi_client_token),
            base_url: normalizeOptional(values.zapi_base_url) ?? undefined,
          }
        : {
            server_url: values.evolution_server_url.trim(),
            auth_type: values.evolution_auth_type,
            api_key: values.evolution_api_key.trim(),
            integration: values.evolution_integration,
            external_instance_name: values.evolution_external_instance_name.trim(),
            instance_token: normalizeOptional(values.evolution_instance_token),
            phone_e164: normalizeOptional(values.evolution_phone_e164),
          },
    };

    onSubmit(payload);
  });

  const title = mode === 'create' ? 'Nova instancia WhatsApp' : 'Editar instancia WhatsApp';
  const description = mode === 'create'
    ? 'Cadastre uma nova instancia com o provider e as credenciais corretas.'
    : 'Atualize os dados operacionais da instancia sem expor segredos sensiveis.';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <form className="space-y-6" onSubmit={handleSubmit}>
          <section className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="provider_key">Provider</Label>
              <Select
                value={providerKey}
                onValueChange={(value) => form.setValue('provider_key', value as WhatsAppProviderKey, { shouldDirty: true })}
                disabled={mode === 'edit'}
              >
                <SelectTrigger id="provider_key">
                  <SelectValue placeholder="Selecione o provider" />
                </SelectTrigger>
                <SelectContent>
                  {WHATSAPP_PROVIDER_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="name">Nome interno</Label>
              <Input id="name" {...form.register('name', { required: true })} placeholder="Atendimento principal" />
            </div>

            <div className="space-y-2">
              <Label htmlFor="instance_name">Slug da instancia</Label>
              <Input id="instance_name" {...form.register('instance_name', { required: true })} placeholder="atendimento_principal" />
            </div>

            <div className="space-y-2">
              <Label htmlFor="phone_number">Telefone</Label>
              <Input id="phone_number" {...form.register('phone_number')} placeholder="+5511999999999" />
            </div>
          </section>

          <section className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="timeout_seconds">Timeout (segundos)</Label>
              <Input id="timeout_seconds" type="number" min={5} max={120} {...form.register('timeout_seconds')} placeholder="30" />
            </div>

            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="webhook_url">Webhook URL</Label>
              <Input id="webhook_url" {...form.register('webhook_url')} placeholder="https://api.seudominio.com/webhook" />
            </div>

            <div className="space-y-2 md:col-span-3">
              <Label htmlFor="tags">Tags</Label>
              <Input id="tags" {...form.register('tags')} placeholder="suporte, comercial, evento-sp" />
            </div>

            <div className="space-y-2 md:col-span-3">
              <Label htmlFor="notes">Observacoes internas</Label>
              <Textarea id="notes" {...form.register('notes')} rows={4} placeholder="Notas operacionais, dono da instancia, cuidados de uso..." />
            </div>
          </section>

          <section className="grid gap-4 rounded-3xl border border-border/60 bg-muted/20 p-4 md:grid-cols-2">
            <div className="flex items-center justify-between rounded-2xl border border-border/60 bg-background/80 px-4 py-3">
              <div>
                <p className="text-sm font-medium">Instancia ativa</p>
                <p className="text-xs text-muted-foreground">Permite uso operacional desta conexao.</p>
              </div>
              <Switch
                checked={form.watch('is_active')}
                onCheckedChange={(checked) => form.setValue('is_active', checked, { shouldDirty: true })}
              />
            </div>

            <div className="flex items-center justify-between rounded-2xl border border-border/60 bg-background/80 px-4 py-3">
              <div>
                <p className="text-sm font-medium">Instancia favorita</p>
                <p className="text-xs text-muted-foreground">Usa esta conexao como padrao da organizacao.</p>
              </div>
              <Switch
                checked={form.watch('is_default')}
                onCheckedChange={(checked) => form.setValue('is_default', checked, { shouldDirty: true })}
              />
            </div>
          </section>

          {providerKey === 'zapi' ? (
            <section className="space-y-4 rounded-3xl border border-border/60 p-4">
              <div>
                <p className="text-sm font-semibold">Configuracao Z-API</p>
                <p className="text-sm text-muted-foreground">Use os dados reais da instancia e deixe segredos em branco na edicao se nao quiser trocar.</p>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="zapi_instance_id">Instance ID</Label>
                  <Input id="zapi_instance_id" {...form.register('zapi_instance_id')} placeholder="3E9F..." />
                  {form.formState.errors.zapi_instance_id ? <p className="text-xs text-destructive">{form.formState.errors.zapi_instance_id.message}</p> : null}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="zapi_base_url">Base URL</Label>
                  <Input id="zapi_base_url" {...form.register('zapi_base_url')} placeholder="https://api.z-api.io" />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="zapi_instance_token">Token da instancia</Label>
                  <Input id="zapi_instance_token" {...form.register('zapi_instance_token')} type="password" placeholder={mode === 'edit' ? 'Preencha so para trocar' : 'Obrigatorio'} />
                  {form.formState.errors.zapi_instance_token ? <p className="text-xs text-destructive">{form.formState.errors.zapi_instance_token.message}</p> : null}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="zapi_client_token">Client token</Label>
                  <Input id="zapi_client_token" {...form.register('zapi_client_token')} type="password" placeholder="Opcional" />
                </div>
              </div>
            </section>
          ) : (
            <section className="space-y-4 rounded-3xl border border-border/60 p-4">
              <div>
                <p className="text-sm font-semibold">Configuracao Evolution</p>
                <p className="text-sm text-muted-foreground">Preencha a URL segura do servidor, autenticacao e metadados da sessao.</p>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="evolution_server_url">Server URL</Label>
                  <Input id="evolution_server_url" {...form.register('evolution_server_url')} placeholder="https://evolution.seudominio.com" />
                  {form.formState.errors.evolution_server_url ? <p className="text-xs text-destructive">{form.formState.errors.evolution_server_url.message}</p> : null}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_external_instance_name">Instancia externa</Label>
                  <Input id="evolution_external_instance_name" {...form.register('evolution_external_instance_name')} placeholder="evento_vivo_main" />
                  {form.formState.errors.evolution_external_instance_name ? <p className="text-xs text-destructive">{form.formState.errors.evolution_external_instance_name.message}</p> : null}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_auth_type">Auth type</Label>
                  <Select
                    value={form.watch('evolution_auth_type')}
                    onValueChange={(value) => form.setValue('evolution_auth_type', value as EvolutionAuthType, { shouldDirty: true })}
                  >
                    <SelectTrigger id="evolution_auth_type">
                      <SelectValue placeholder="Tipo de autenticacao" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="global_apikey">Global API key</SelectItem>
                      <SelectItem value="instance_apikey">Instance API key</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_integration">Integracao</Label>
                  <Select
                    value={form.watch('evolution_integration')}
                    onValueChange={(value) => form.setValue('evolution_integration', value as EvolutionIntegration, { shouldDirty: true })}
                  >
                    <SelectTrigger id="evolution_integration">
                      <SelectValue placeholder="Integracao" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="WHATSAPP-BAILEYS">WHATSAPP-BAILEYS</SelectItem>
                      <SelectItem value="WHATSAPP-BUSINESS">WHATSAPP-BUSINESS</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_api_key">API key</Label>
                  <Input id="evolution_api_key" {...form.register('evolution_api_key')} type="password" placeholder={mode === 'edit' ? 'Preencha so para trocar' : 'Obrigatoria'} />
                  {form.formState.errors.evolution_api_key ? <p className="text-xs text-destructive">{form.formState.errors.evolution_api_key.message}</p> : null}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_instance_token">Token da instancia</Label>
                  <Input id="evolution_instance_token" {...form.register('evolution_instance_token')} type="password" placeholder="Opcional" />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="evolution_phone_e164">Telefone E.164</Label>
                  <Input id="evolution_phone_e164" {...form.register('evolution_phone_e164')} placeholder="+5511999999999" />
                </div>
              </div>
            </section>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancelar
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Salvando...' : mode === 'create' ? 'Criar instancia' : 'Salvar alteracoes'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
