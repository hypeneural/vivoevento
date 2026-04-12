import { useDeferredValue, useEffect, useMemo, useState, useTransition } from 'react';
import { Download, Loader2, RefreshCcw, Save, ShieldCheck, TriangleAlert } from 'lucide-react';
import { useForm, useWatch, type Control } from 'react-hook-form';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useIsMobile } from '@/hooks/use-mobile';
import { QrCodeAdvancedPanel } from '@/modules/qr-code/components/QrCodeAdvancedPanel';
import { QrCodeContentPanel } from '@/modules/qr-code/components/QrCodeContentPanel';
import { QrCodeEditorDialog } from '@/modules/qr-code/components/QrCodeEditorDialog';
import { QrCodeEditorDrawer } from '@/modules/qr-code/components/QrCodeEditorDrawer';
import { QrCodeExportPanel } from '@/modules/qr-code/components/QrCodeExportPanel';
import { QrCodeLogoPanel } from '@/modules/qr-code/components/QrCodeLogoPanel';
import { QrCodePreviewPane } from '@/modules/qr-code/components/QrCodePreviewPane';
import { QrCodeStylePanel } from '@/modules/qr-code/components/QrCodeStylePanel';
import { downloadEventPublicLinkQrCode } from '@/modules/qr-code/support/qrDownload';
import { resolveQrCascadeExplanation } from '@/modules/qr-code/support/qrCascadeExplanation';
import { applyQrCopyStyle } from '@/modules/qr-code/support/qrCopyStyle';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { buildQrCascadeDefaults } from '@/modules/qr-code/support/qrPresetCascade';
import { getQrReadabilityReport } from '@/modules/qr-code/support/qrReadability';
import { resetQrSection, type QrSectionKey } from '@/modules/qr-code/support/qrSectionReset';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

import type { EventPublicLinkQrEditorState } from './api';

interface EventPublicLinkQrEditorShellProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  state: EventPublicLinkQrEditorState;
  availableStyles?: EventPublicLinkQrEditorState[];
  isLoading?: boolean;
  isSaving?: boolean;
  isResetting?: boolean;
  isUploadingLogo?: boolean;
  onSave: (config: EventPublicLinkQrConfig) => Promise<unknown> | unknown;
  onResetToDefault: () => Promise<unknown> | unknown;
  onUploadCustomLogo?: (file: File, previousPath?: string | null) => Promise<{ path: string; url: string }> | PromiseLike<{ path: string; url: string }> | { path: string; url: string };
}

function useEventPublicLinkQrPreviewDraft(
  control: Control<EventPublicLinkQrConfig>,
  initialConfig: EventPublicLinkQrConfig,
) {
  const usagePreset = useWatch({
    control,
    name: 'usage_preset',
  });
  const skinPreset = useWatch({
    control,
    name: 'skin_preset',
  });
  const render = useWatch({
    control,
    name: 'render',
  });
  const style = useWatch({
    control,
    name: 'style',
  });
  const logo = useWatch({
    control,
    name: 'logo',
  });
  const advanced = useWatch({
    control,
    name: 'advanced',
  });

  return useMemo<EventPublicLinkQrConfig>(() => ({
    ...initialConfig,
    usage_preset: usagePreset ?? initialConfig.usage_preset,
    skin_preset: skinPreset ?? initialConfig.skin_preset,
    render: render ?? initialConfig.render,
    style: style ?? initialConfig.style,
    logo: logo ?? initialConfig.logo,
    advanced: advanced ?? initialConfig.advanced,
  }), [advanced, initialConfig, logo, render, skinPreset, style, usagePreset]);
}

function EditorBody({
  state,
  availableStyles,
  previewOptions,
  previewDraft,
  cascadeExplanation,
  readability,
  onDownload,
  onUsagePresetChange,
  onSkinPresetChange,
  onCopyStyle,
  onResetSection,
  onUploadCustomLogo,
  isUploadingLogo = false,
  isLoading = false,
  isSaving = false,
  isResetting = false,
  isDownloading = false,
}: {
  state: EventPublicLinkQrEditorState;
  availableStyles: EventPublicLinkQrEditorState[];
  previewOptions: ReturnType<typeof buildQrCodeStylingOptions>;
  previewDraft: EventPublicLinkQrConfig;
  cascadeExplanation: ReturnType<typeof resolveQrCascadeExplanation>;
  readability: ReturnType<typeof getQrReadabilityReport>;
  onDownload: () => void;
  onUsagePresetChange: (preset: EventPublicLinkQrConfig['usage_preset']) => void;
  onSkinPresetChange: (preset: EventPublicLinkQrConfig['skin_preset']) => void;
  onCopyStyle: (linkKey: EventPublicLinkQrEditorState['linkKey']) => void;
  onResetSection: (section: QrSectionKey) => void;
  onUploadCustomLogo?: (file: File, previousPath?: string | null) => Promise<{ path: string; url: string }> | PromiseLike<{ path: string; url: string }> | { path: string; url: string };
  isUploadingLogo?: boolean;
  isLoading?: boolean;
  isSaving?: boolean;
  isResetting?: boolean;
  isDownloading?: boolean;
}) {
  const previewValue = state.link.qr_value ?? state.link.url ?? '';
  const statusLabel = isSaving
    ? 'Salvando'
    : isDownloading
      ? 'Gerando arquivo'
    : isResetting
      ? 'Restaurando'
    : isLoading
      ? 'Sincronizando'
        : 'Visual pronto';

  const readabilityToneClass = readability.tone === 'success'
    ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
    : readability.tone === 'warning'
      ? 'border-amber-200 bg-amber-50 text-amber-950'
      : 'border-rose-200 bg-rose-50 text-rose-900';

  return (
    <div className="grid gap-5 lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
      <Card className="border-slate-200 bg-slate-50/90 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between gap-3">
            <CardTitle className="text-base">Pre-visualizacao ao vivo</CardTitle>
            <Badge variant="outline">
              {statusLabel}
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <QrCodePreviewPane
            options={previewOptions}
            unavailable={!previewValue}
            unavailableLabel="QR indisponivel"
          />

          <div className={`rounded-2xl border px-3 py-3 text-sm ${readabilityToneClass}`}>
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                {readability.tone === 'danger' ? (
                  <TriangleAlert className="h-4 w-4" />
                ) : (
                  <ShieldCheck className="h-4 w-4" />
                )}
                <p className="font-medium">{readability.label}</p>
              </div>
              <Badge variant="outline">{readability.score}/100</Badge>
            </div>
            <p className="mt-2 text-xs leading-5">{readability.reasons[0]}</p>
          </div>

          <div className="space-y-2 text-sm text-muted-foreground">
            <div className="flex items-center justify-between gap-2">
              <span className="font-medium text-foreground">{state.link.label}</span>
              <Badge variant="secondary">{previewOptions.type}</Badge>
            </div>
            <p className="break-all rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs">
              {previewValue || 'Link ainda indisponivel.'}
            </p>
            <p className="text-xs">
              Voce salva a aparencia deste QR so neste link. O destino que ele abre continua sendo o link publico real do evento.
            </p>
          </div>

          <Button
            type="button"
            className="w-full"
            onClick={onDownload}
            disabled={!previewValue || readability.blocksExport || isLoading || isSaving || isResetting || isDownloading}
          >
            <Download className="mr-2 h-4 w-4" />
            Baixar QR agora
          </Button>
        </CardContent>
      </Card>

      <Tabs defaultValue="content" className="space-y-4">
        <TabsList className="grid h-auto grid-cols-2 gap-2 bg-muted/50 p-1 sm:grid-cols-5">
          <TabsTrigger value="content">Conteudo</TabsTrigger>
          <TabsTrigger value="style">Estilo</TabsTrigger>
          <TabsTrigger value="logo">Logo</TabsTrigger>
          <TabsTrigger value="export">Baixar</TabsTrigger>
          <TabsTrigger value="advanced">Avancado</TabsTrigger>
        </TabsList>

        <TabsContent value="content" className="space-y-4" forceMount>
          <QrCodeContentPanel
            link={state.link}
            config={previewDraft}
            explanation={cascadeExplanation}
            onUsagePresetChange={onUsagePresetChange}
            availableStyles={availableStyles.map((item) => ({
              linkKey: item.linkKey,
              link: item.link,
            }))}
            onCopyStyle={onCopyStyle}
            effectiveBranding={state.effectiveBranding}
            onResetSection={() => onResetSection('content')}
          />
        </TabsContent>

        <TabsContent value="style" className="space-y-4" forceMount>
          <QrCodeStylePanel
            config={previewDraft}
            explanation={cascadeExplanation}
            onSkinPresetChange={onSkinPresetChange}
            onResetSection={() => onResetSection('style')}
          />
        </TabsContent>

        <TabsContent value="logo" className="space-y-4" forceMount>
          <QrCodeLogoPanel
            effectiveBranding={state.effectiveBranding}
            explanation={cascadeExplanation}
            onResetSection={() => onResetSection('logo')}
            onUploadCustomLogo={onUploadCustomLogo}
            isUploadingLogo={isUploadingLogo}
          />
        </TabsContent>

        <TabsContent value="export" className="space-y-4" forceMount>
          <QrCodeExportPanel
            explanation={cascadeExplanation}
            readability={readability}
            onDownload={onDownload}
            onResetSection={() => onResetSection('export')}
          />
        </TabsContent>

        <TabsContent value="advanced" className="space-y-4" forceMount>
          <QrCodeAdvancedPanel onResetSection={() => onResetSection('advanced')} />
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function EventPublicLinkQrEditorShell({
  open,
  onOpenChange,
  state,
  availableStyles = [],
  isLoading = false,
  isSaving = false,
  isResetting = false,
  isUploadingLogo = false,
  onSave,
  onResetToDefault,
  onUploadCustomLogo,
}: EventPublicLinkQrEditorShellProps) {
  const isMobile = useIsMobile();
  const form = useForm<EventPublicLinkQrConfig>({
    defaultValues: state.config,
    mode: 'onBlur',
    reValidateMode: 'onChange',
  });
  const [isLocalResetPending, startLocalResetTransition] = useTransition();
  const [isDownloading, setIsDownloading] = useState(false);

  useEffect(() => {
    form.reset(state.config);
  }, [form, state.config]);

  const previewDraft = useEventPublicLinkQrPreviewDraft(form.control, state.config);
  const deferredPreviewDraft = useDeferredValue(previewDraft);
  const previewValue = state.link.qr_value ?? state.link.url ?? '';
  const previewOptions = useMemo(() => buildQrCodeStylingOptions({
    config: deferredPreviewDraft,
    data: previewValue,
  }), [deferredPreviewDraft, previewValue]);
  const cascadeExplanation = useMemo(() => resolveQrCascadeExplanation({
    config: previewDraft,
    linkKey: state.linkKey,
    branding: state.effectiveBranding ?? undefined,
  }), [previewDraft, state.effectiveBranding, state.linkKey]);
  const readability = useMemo(() => getQrReadabilityReport(previewDraft), [previewDraft]);

  const isBusy = isSaving || isResetting || isDownloading;
  const canReset = state.hasSavedConfig || form.formState.isDirty;
  const title = 'Editar QR Code';
  const description = `${state.link.label} do evento`;
  const footer = (
    <>
      <Button variant="outline" autoFocus onClick={() => onOpenChange(false)}>
        Fechar
      </Button>
      <Button
        type="button"
        variant="ghost"
        onClick={() => {
          if (state.hasSavedConfig) {
            void onResetToDefault();
            return;
          }

          startLocalResetTransition(() => form.reset(state.config));
        }}
        disabled={isBusy || !canReset || isLocalResetPending}
      >
        <RefreshCcw className="mr-2 h-4 w-4" />
        {isResetting || isLocalResetPending ? 'Restaurando...' : 'Restaurar padrao'}
      </Button>
      <Button
        type="button"
        variant="secondary"
        onClick={() => {
          const values = form.getValues();

          setIsDownloading(true);
          void downloadEventPublicLinkQrCode({
            config: values,
            data: previewValue,
            eventId: state.eventId,
            linkKey: state.linkKey,
          }).finally(() => {
            setIsDownloading(false);
          });
        }}
        disabled={!previewValue || readability.blocksExport || isBusy}
      >
        {isDownloading ? (
          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
        ) : (
          <Download className="mr-2 h-4 w-4" />
        )}
        {isDownloading ? 'Gerando arquivo...' : 'Baixar QR'}
      </Button>
      <Button
        type="submit"
        form="event-public-link-qr-editor-form"
        disabled={isBusy || !form.formState.isDirty || readability.blocksSave}
      >
        {isSaving ? (
          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
        ) : (
          <Save className="mr-2 h-4 w-4" />
        )}
        {isSaving ? 'Salvando...' : 'Salvar'}
      </Button>
    </>
  );

  const applyConfigToForm = (nextConfig: EventPublicLinkQrConfig) => {
    form.setValue('usage_preset', nextConfig.usage_preset, { shouldDirty: true });
    form.setValue('skin_preset', nextConfig.skin_preset, { shouldDirty: true });
    form.setValue('render', nextConfig.render, { shouldDirty: true });
    form.setValue('style', nextConfig.style, { shouldDirty: true });
    form.setValue('logo', nextConfig.logo, { shouldDirty: true });
    form.setValue('advanced', nextConfig.advanced, { shouldDirty: true });
    form.setValue('export_defaults', nextConfig.export_defaults, { shouldDirty: true });
  };

  const body = (
    <Form {...form}>
      <form
        id="event-public-link-qr-editor-form"
        className="space-y-4"
        aria-busy={isBusy}
        onSubmit={form.handleSubmit(async (values) => {
          await onSave(values);
        })}
      >
        <EditorBody
          state={state}
          availableStyles={availableStyles}
          previewOptions={previewOptions}
          previewDraft={previewDraft}
          cascadeExplanation={cascadeExplanation}
          readability={readability}
          onDownload={() => {
            setIsDownloading(true);
            void downloadEventPublicLinkQrCode({
              config: form.getValues(),
              data: previewValue,
              eventId: state.eventId,
              linkKey: state.linkKey,
            }).finally(() => {
              setIsDownloading(false);
            });
          }}
          onUsagePresetChange={(preset) => {
            const nextConfig = buildQrCascadeDefaults({
              linkKey: state.linkKey,
              usagePreset: preset,
              skinPreset: previewDraft.skin_preset,
              branding: state.effectiveBranding ?? undefined,
            });

            startLocalResetTransition(() => applyConfigToForm(nextConfig));
          }}
          onSkinPresetChange={(preset) => {
            const nextConfig = buildQrCascadeDefaults({
              linkKey: state.linkKey,
              usagePreset: previewDraft.usage_preset,
              skinPreset: preset,
              branding: state.effectiveBranding ?? undefined,
            });

            startLocalResetTransition(() => applyConfigToForm(nextConfig));
          }}
          onCopyStyle={(linkKey) => {
            const source = availableStyles.find((item) => item.linkKey === linkKey);
            if (!source) {
              return;
            }

            const nextConfig = applyQrCopyStyle(source.config, form.getValues());

            startLocalResetTransition(() => applyConfigToForm(nextConfig));
          }}
          onResetSection={(section) => {
            const defaults = buildQrCascadeDefaults({
              linkKey: state.linkKey,
              usagePreset: section === 'content' ? undefined : previewDraft.usage_preset,
              skinPreset: previewDraft.skin_preset,
              branding: state.effectiveBranding ?? undefined,
            });
            const nextConfig = resetQrSection(form.getValues(), defaults, section);

            startLocalResetTransition(() => applyConfigToForm(nextConfig));
          }}
          onUploadCustomLogo={onUploadCustomLogo}
          isUploadingLogo={isUploadingLogo}
          isLoading={isLoading}
          isSaving={isSaving}
          isResetting={isResetting || isLocalResetPending}
          isDownloading={isDownloading}
        />
      </form>
    </Form>
  );

  if (isMobile) {
    return (
      <QrCodeEditorDrawer
        open={open}
        onOpenChange={onOpenChange}
        title={title}
        description={description}
        footer={footer}
      >
        {body}
      </QrCodeEditorDrawer>
    );
  }

  return (
    <QrCodeEditorDialog
      open={open}
      onOpenChange={onOpenChange}
      title={title}
      description={description}
      footer={footer}
    >
      {body}
    </QrCodeEditorDialog>
  );
}
