import { type ChangeEvent, useEffect, useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { AlertTriangle, Camera, CheckCircle2, ImagePlus, Loader2, Send, Sparkles, X } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/hooks/use-toast';
import api, { ApiError } from '@/lib/api';
import { resolveAssetUrl } from '@/lib/assets';
import type { PublicEventUploadBootstrap, PublicEventUploadResult } from '@/lib/api-types';
import { cn } from '@/lib/utils';

type SelectedUpload = {
  id: string;
  file: File;
  previewUrl: string;
};

function formatBytes(bytes: number) {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function moderationFlowLabel(mode: string) {
  switch (mode) {
    case 'none':
      return 'Entrada sem fila';
    case 'ai':
      return 'Moderacao por IA';
    default:
      return 'Fila manual';
  }
}

export default function PublicEventUploadPage() {
  const { code } = useParams<{ code: string }>();
  const cameraInputRef = useRef<HTMLInputElement>(null);
  const galleryInputRef = useRef<HTMLInputElement>(null);
  const selectedRef = useRef<SelectedUpload[]>([]);

  const [selectedFiles, setSelectedFiles] = useState<SelectedUpload[]>([]);
  const [senderName, setSenderName] = useState('');
  const [caption, setCaption] = useState('');
  const [lastResult, setLastResult] = useState<PublicEventUploadResult | null>(null);

  useEffect(() => {
    selectedRef.current = selectedFiles;
  }, [selectedFiles]);

  useEffect(() => {
    return () => {
      selectedRef.current.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    };
  }, []);

  const uploadQuery = useQuery({
    queryKey: ['public-event-upload', code],
    enabled: !!code,
    retry: false,
    queryFn: () => api.get<PublicEventUploadBootstrap>(`/public/events/${code}/upload`),
  });

  const uploadMutation = useMutation({
    mutationFn: async () => {
      if (!code) {
        throw new Error('Link de envio invalido.');
      }

      const formData = new FormData();

      selectedFiles.forEach((item) => {
        formData.append('files[]', item.file);
      });

      if (senderName.trim()) {
        formData.append('sender_name', senderName.trim());
      }

      if (caption.trim()) {
        formData.append('caption', caption.trim());
      }

      return api.upload<PublicEventUploadResult>(`/public/events/${code}/upload`, formData);
    },
    onSuccess: (result) => {
      selectedFiles.forEach((item) => URL.revokeObjectURL(item.previewUrl));
      setSelectedFiles([]);
      setCaption('');
      setLastResult(result);

      toast({
        title: 'Fotos enviadas',
        description: result.message,
      });
    },
    onError: (error) => {
      const message = error instanceof ApiError
        ? error.message
        : 'Nao foi possivel enviar suas fotos agora.';

      toast({
        title: 'Falha no envio',
        description: message,
        variant: 'destructive',
      });
    },
  });

  const uploadData = uploadQuery.data;
  const coverUrl = uploadData?.event.cover_image_url || resolveAssetUrl(uploadData?.event.cover_image_path);
  const logoUrl = uploadData?.event.logo_url || resolveAssetUrl(uploadData?.event.logo_path);
  const primaryColor = uploadData?.event.primary_color || '#5b3df5';
  const secondaryColor = uploadData?.event.secondary_color || '#1f8fff';
  const uploadEnabled = uploadData?.upload.enabled ?? false;

  function openCamera() {
    if (!uploadEnabled) return;
    cameraInputRef.current?.click();
  }

  function openGallery() {
    if (!uploadEnabled) return;
    galleryInputRef.current?.click();
  }

  function appendFiles(fileList: FileList | null) {
    if (!fileList || fileList.length === 0) return;

    const incoming = Array.from(fileList).filter((file) => file.type.startsWith('image/'));

    if (incoming.length === 0) {
      toast({
        title: 'Formato invalido',
        description: 'Selecione apenas imagens do seu celular.',
        variant: 'destructive',
      });
      return;
    }

    const maxFiles = uploadData?.upload.max_files ?? 10;

    setLastResult(null);
    setSelectedFiles((current) => {
      const signatures = new Set(current.map((item) => `${item.file.name}-${item.file.size}-${item.file.lastModified}`));
      const next = [...current];

      incoming.forEach((file) => {
        const signature = `${file.name}-${file.size}-${file.lastModified}`;

        if (signatures.has(signature) || next.length >= maxFiles) {
          return;
        }

        signatures.add(signature);
        next.push({
          id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
          file,
          previewUrl: URL.createObjectURL(file),
        });
      });

      if (next.length === current.length) {
        toast({
          title: 'Limite atingido',
          description: `Voce pode enviar ate ${maxFiles} imagens por vez.`,
          variant: 'destructive',
        });
      }

      return next;
    });
  }

  function handleCameraChange(event: ChangeEvent<HTMLInputElement>) {
    appendFiles(event.target.files);
    event.target.value = '';
  }

  function handleGalleryChange(event: ChangeEvent<HTMLInputElement>) {
    appendFiles(event.target.files);
    event.target.value = '';
  }

  function removeFile(id: string) {
    setSelectedFiles((current) => {
      const target = current.find((item) => item.id === id);
      if (target) {
        URL.revokeObjectURL(target.previewUrl);
      }

      return current.filter((item) => item.id !== id);
    });
  }

  function clearSelection() {
    selectedFiles.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    setSelectedFiles([]);
  }

  if (!code) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6 text-center">
        <div className="max-w-sm space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-warning" />
          <h1 className="text-xl font-semibold">Link de envio invalido</h1>
          <p className="text-sm text-muted-foreground">Confirme o link recebido e tente abrir novamente.</p>
        </div>
      </div>
    );
  }

  if (uploadQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
          <p className="text-sm text-muted-foreground">Preparando seu envio...</p>
        </div>
      </div>
    );
  }

  if (uploadQuery.isError || !uploadData) {
    const message = uploadQuery.error instanceof ApiError
      ? uploadQuery.error.message
      : 'Nao foi possivel abrir esta pagina agora.';

    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6 text-center">
        <div className="max-w-sm space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-destructive" />
          <h1 className="text-xl font-semibold">Pagina indisponivel</h1>
          <p className="text-sm text-muted-foreground">{message}</p>
        </div>
      </div>
    );
  }

  return (
    <div
      className="min-h-[100dvh] bg-background pb-32"
      style={{
        backgroundImage: `radial-gradient(circle at top, ${primaryColor}1f 0%, transparent 45%), linear-gradient(180deg, #ffffff 0%, #f4f7fb 100%)`,
      }}
    >
      <input
        ref={cameraInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={handleCameraChange}
      />
      <input
        ref={galleryInputRef}
        type="file"
        accept="image/*"
        multiple
        className="hidden"
        onChange={handleGalleryChange}
      />

      <div className="mx-auto flex min-h-[100dvh] w-full max-w-md flex-col gap-4 px-4 pb-8 pt-4">
        <Card className="overflow-hidden border-0 shadow-xl shadow-slate-200/70">
          <div
            className="relative min-h-[220px] px-5 pb-5 pt-6 text-white"
            style={{
              background: `linear-gradient(145deg, ${primaryColor}, ${secondaryColor})`,
            }}
          >
            {coverUrl ? (
              <img
                src={coverUrl}
                alt={uploadData.event.title}
                className="absolute inset-0 h-full w-full object-cover opacity-20"
              />
            ) : null}

            <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/10 to-black/50" />

            <div className="relative flex h-full flex-col justify-between gap-6">
              <div className="flex items-start justify-between gap-4">
                <div className="space-y-2">
                  <span className="inline-flex w-fit items-center rounded-full bg-white/18 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/90 backdrop-blur">
                    Evento ativo
                  </span>
                  <div className="space-y-1">
                    <h1 className="text-2xl font-semibold leading-tight">{uploadData.event.title}</h1>
                    <p className="max-w-[24rem] text-sm text-white/82">{uploadData.upload.message}</p>
                  </div>
                </div>

                {logoUrl ? (
                  <img
                    src={logoUrl}
                    alt="Logo do evento"
                    className="h-14 w-14 rounded-2xl border border-white/20 bg-white/90 object-cover p-2 shadow-lg"
                  />
                ) : (
                  <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                    <Sparkles className="h-6 w-6" />
                  </div>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-2xl bg-white/12 p-3 backdrop-blur">
                  <p className="text-[11px] uppercase tracking-[0.16em] text-white/70">Fluxo</p>
                  <p className="mt-1 text-sm font-medium">
                    {moderationFlowLabel(uploadData.upload.moderation_mode)}
                  </p>
                </div>
                <div className="rounded-2xl bg-white/12 p-3 backdrop-blur">
                  <p className="text-[11px] uppercase tracking-[0.16em] text-white/70">Limite</p>
                  <p className="mt-1 text-sm font-medium">
                    {uploadData.upload.max_files} imagens de ate {uploadData.upload.max_file_size_mb} MB
                  </p>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <Card className="glass border-white/70 bg-white/90 shadow-lg shadow-slate-200/60">
          <CardContent className="space-y-4 p-4">
            <div className="space-y-1">
              <p className="text-sm font-semibold">Enviar pelo celular</p>
              <p className="text-sm text-muted-foreground">{uploadData.upload.instructions}</p>
            </div>

            <div className="grid grid-cols-1 gap-3">
              <button
                type="button"
                onClick={openCamera}
                disabled={!uploadEnabled}
                className={cn(
                  'flex min-h-[84px] items-center justify-between rounded-3xl border px-4 py-4 text-left transition-transform active:scale-[0.99]',
                  uploadEnabled
                    ? 'border-transparent bg-slate-950 text-white shadow-lg shadow-slate-300'
                    : 'cursor-not-allowed border-dashed border-border bg-muted text-muted-foreground'
                )}
              >
                <div className="space-y-1">
                  <p className="text-base font-semibold">Tirar foto agora</p>
                  <p className={cn('text-sm', uploadEnabled ? 'text-white/72' : 'text-muted-foreground')}>
                    Abra a camera do telefone e envie na hora.
                  </p>
                </div>
                <Camera className="h-6 w-6" />
              </button>

              <button
                type="button"
                onClick={openGallery}
                disabled={!uploadEnabled}
                className={cn(
                  'flex min-h-[84px] items-center justify-between rounded-3xl border px-4 py-4 text-left transition-transform active:scale-[0.99]',
                  uploadEnabled
                    ? 'border-primary/15 bg-primary/5 text-foreground shadow-lg shadow-primary/10'
                    : 'cursor-not-allowed border-dashed border-border bg-muted text-muted-foreground'
                )}
              >
                <div className="space-y-1">
                  <p className="text-base font-semibold">Escolher da galeria</p>
                  <p className="text-sm text-muted-foreground">
                    Selecione uma ou mais imagens que ja estao salvas.
                  </p>
                </div>
                <ImagePlus className="h-6 w-6" />
              </button>
            </div>

            {!uploadEnabled ? (
              <div className="rounded-2xl border border-warning/25 bg-warning/10 px-4 py-3 text-sm text-slate-800">
                {uploadData.upload.message}
              </div>
            ) : null}
          </CardContent>
        </Card>

        {lastResult ? (
          <Card className="border-success/20 bg-emerald-50 shadow-sm">
            <CardContent className="flex items-start gap-3 p-4">
              <CheckCircle2 className="mt-0.5 h-5 w-5 text-emerald-600" />
              <div className="space-y-1">
                <p className="text-sm font-semibold text-emerald-900">Envio concluido</p>
                <p className="text-sm text-emerald-800">
                  {lastResult.uploaded_count} {lastResult.uploaded_count > 1 ? 'imagens foram recebidas.' : 'imagem foi recebida.'}
                </p>
              </div>
            </CardContent>
          </Card>
        ) : null}

        <Card className="glass border-white/70 bg-white/90 shadow-sm">
          <CardContent className="space-y-4 p-4">
            <div className="space-y-1">
              <p className="text-sm font-semibold">Se identificar e opcional</p>
              <p className="text-sm text-muted-foreground">
                Seu nome e uma legenda ajudam a equipe a organizar melhor as imagens.
              </p>
            </div>

            <div className="space-y-3">
              <Input
                value={senderName}
                onChange={(event) => setSenderName(event.target.value)}
                placeholder="Seu nome (opcional)"
                maxLength={120}
                className="h-12 rounded-2xl border-white bg-slate-50"
              />
              <Textarea
                value={caption}
                onChange={(event) => setCaption(event.target.value)}
                placeholder="Escreva uma mensagem curta se quiser"
                maxLength={500}
                className="min-h-[104px] rounded-2xl border-white bg-slate-50"
              />
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-white/70 bg-white/90 shadow-sm">
          <CardContent className="space-y-4 p-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-semibold">Imagens selecionadas</p>
                <p className="text-sm text-muted-foreground">
                  {selectedFiles.length > 0
                    ? `${selectedFiles.length} pronta(s) para envio`
                    : 'Nenhuma imagem selecionada ainda'}
                </p>
              </div>

              {selectedFiles.length > 0 ? (
                <Button variant="ghost" size="sm" onClick={clearSelection}>
                  Limpar
                </Button>
              ) : null}
            </div>

            {selectedFiles.length > 0 ? (
              <div className="grid grid-cols-2 gap-3">
                {selectedFiles.map((item) => (
                  <div key={item.id} className="relative overflow-hidden rounded-3xl border border-slate-200 bg-slate-100">
                    <img
                      src={item.previewUrl}
                      alt={item.file.name}
                      className="h-36 w-full object-cover"
                    />
                    <button
                      type="button"
                      onClick={() => removeFile(item.id)}
                      className="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/70 text-white backdrop-blur"
                    >
                      <X className="h-4 w-4" />
                    </button>
                    <div className="space-y-1 p-3">
                      <p className="truncate text-sm font-medium">{item.file.name}</p>
                      <p className="text-xs text-muted-foreground">{formatBytes(item.file.size)}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="rounded-3xl border border-dashed border-border bg-slate-50 px-4 py-10 text-center">
                <ImagePlus className="mx-auto h-8 w-8 text-muted-foreground" />
                <p className="mt-3 text-sm font-medium">Escolha fotos para comecar</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Voce pode combinar imagens da camera e da galeria.
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/92 backdrop-blur-xl">
        <div className="mx-auto flex w-full max-w-md items-center gap-3 px-4 py-4">
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold">
              {selectedFiles.length > 0 ? `${selectedFiles.length} imagem(ns) pronta(s)` : 'Selecione suas imagens'}
            </p>
            <p className="truncate text-xs text-muted-foreground">
              {uploadEnabled
                ? 'Toque em enviar quando terminar de selecionar.'
                : uploadData.upload.message}
            </p>
          </div>

          <Button
            size="lg"
            className="h-12 rounded-2xl px-5"
            disabled={!uploadEnabled || selectedFiles.length === 0 || uploadMutation.isPending}
            onClick={() => uploadMutation.mutate()}
          >
            {uploadMutation.isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Enviando
              </>
            ) : (
              <>
                <Send className="h-4 w-4" />
                Enviar
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  );
}
