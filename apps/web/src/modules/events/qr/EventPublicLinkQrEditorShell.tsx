import { useMemo } from 'react';
import { ExternalLink, Image as ImageIcon, Palette, QrCode, Settings, Sparkles } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useIsMobile } from '@/hooks/use-mobile';
import { QrCodeEditorDialog } from '@/modules/qr-code/components/QrCodeEditorDialog';
import { QrCodeEditorDrawer } from '@/modules/qr-code/components/QrCodeEditorDrawer';

import { buildEventPublicLinkQrPreviewOptions, type EventPublicLinkQrEditorState } from './api';

interface EventPublicLinkQrEditorShellProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  state: EventPublicLinkQrEditorState;
  isLoading?: boolean;
}

function EditorBody({
  state,
  isLoading = false,
}: {
  state: EventPublicLinkQrEditorState;
  isLoading?: boolean;
}) {
  const previewOptions = useMemo(() => buildEventPublicLinkQrPreviewOptions(state), [state]);
  const previewValue = state.link.qr_value ?? state.link.url ?? '';
  const previewColor = previewOptions.dotsOptions?.color ?? '#0f172a';
  const previewBackground = previewOptions.backgroundOptions?.color ?? '#ffffff';

  return (
    <div className="grid gap-5 lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
      <Card className="border-slate-200 bg-slate-50/90 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between gap-3">
            <CardTitle className="text-base">Preview inicial</CardTitle>
            <Badge variant="outline">{isLoading ? 'Preparando' : 'Shell pronta'}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex justify-center rounded-3xl border border-dashed border-slate-200 bg-white p-6">
            {previewValue ? (
              <QRCodeSVG
                value={previewValue}
                size={180}
                fgColor={previewColor}
                bgColor={previewBackground}
              />
            ) : (
              <div className="flex h-[180px] w-[180px] items-center justify-center rounded-2xl border border-dashed border-slate-200 text-sm text-muted-foreground">
                QR indisponivel
              </div>
            )}
          </div>

          <div className="space-y-2 text-sm text-muted-foreground">
            <div className="flex items-center gap-2">
              <QrCode className="h-4 w-4" />
              <span>{state.link.label}</span>
            </div>
            <p className="break-all rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs">
              {state.link.qr_value ?? state.link.url ?? 'Link ainda indisponivel.'}
            </p>
            <p className="text-xs">
              O preview ao vivo com `qr-code-styling` entra na proxima fase. Esta shell ja usa o schema semantico e o adapter para derivar os defaults.
            </p>
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="content" className="space-y-4">
        <TabsList className="grid h-auto grid-cols-2 gap-2 bg-muted/50 p-1 sm:grid-cols-5">
          <TabsTrigger value="content">Conteudo</TabsTrigger>
          <TabsTrigger value="style">Estilo</TabsTrigger>
          <TabsTrigger value="logo">Logo</TabsTrigger>
          <TabsTrigger value="export">Exportacao</TabsTrigger>
          <TabsTrigger value="advanced">Avancado</TabsTrigger>
        </TabsList>

        <TabsContent value="content" className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <QrCode className="h-4 w-4 text-primary" />
                <p className="text-sm font-medium">Link publico usado como fonte de verdade</p>
              </div>
              <p className="break-all rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-muted-foreground">
                {state.link.qr_value ?? state.link.url ?? 'Link indisponivel'}
              </p>
              <p className="text-xs text-muted-foreground">
                Nesta fase o conteudo do QR continua vindo do link publico do evento. A edicao livre do payload nao entra na V1.
              </p>
              {state.link.url ? (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => window.open(state.link.url!, '_blank', 'noopener,noreferrer')}
                  >
                    <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                    Abrir link
                  </Button>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="style" className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <Palette className="h-4 w-4 text-primary" />
                <p className="text-sm font-medium">Preset de uso e skin visual</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Badge variant="outline">{state.config.usage_preset}</Badge>
                <Badge variant="secondary">{state.config.skin_preset}</Badge>
              </div>
              <p className="text-xs text-muted-foreground">
                A shell ja abre orientada por cenario de uso. Os ajustes finos entram na fase de formulario com preview performatico.
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="logo" className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <ImageIcon className="h-4 w-4 text-primary" />
                <p className="text-sm font-medium">Logo efetiva do evento</p>
              </div>
              <p className="text-xs text-muted-foreground">
                Modo atual: <span className="font-medium text-foreground">{state.config.logo.mode}</span>
              </p>
              <p className="text-xs text-muted-foreground">
                URL atual: {state.config.logo.asset_url ?? 'sem logo configurada'}
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="export" className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                <p className="text-sm font-medium">Defaults de exportacao</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Badge variant="outline">{state.config.export_defaults.extension}</Badge>
                <Badge variant="outline">{state.config.export_defaults.size}px</Badge>
              </div>
              <p className="text-xs text-muted-foreground">
                O export pesado e sob demanda e sera conectado ao fluxo real depois da fase do preview.
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="advanced" className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-4">
              <div className="flex items-center gap-2">
                <Settings className="h-4 w-4 text-primary" />
                <p className="text-sm font-medium">Avancado recolhido por padrao</p>
              </div>
              <p className="text-xs text-muted-foreground">
                ECC atual: <span className="font-medium text-foreground">{state.config.advanced.error_correction_level}</span>
              </p>
              <p className="text-xs text-muted-foreground">
                Shape: <span className="font-medium text-foreground">{state.config.advanced.shape}</span>
              </p>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function EventPublicLinkQrEditorShell({
  open,
  onOpenChange,
  state,
  isLoading = false,
}: EventPublicLinkQrEditorShellProps) {
  const isMobile = useIsMobile();

  const title = 'Editar QR Code';
  const description = `${state.link.label} do evento`;
  const footer = (
    <>
      <Button variant="outline" onClick={() => onOpenChange(false)}>
        Fechar
      </Button>
      <Button disabled>
        Salvar em breve
      </Button>
    </>
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
        <EditorBody state={state} isLoading={isLoading} />
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
      <EditorBody state={state} isLoading={isLoading} />
    </QrCodeEditorDialog>
  );
}
