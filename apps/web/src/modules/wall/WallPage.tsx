/**
 * WallPage — Admin wall settings and controls.
 *
 * Integrates with the real API for settings CRUD and wall status controls.
 * Provides a preview area with a link to the public wall player.
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Monitor, Play, Pause, Square, Settings, QrCode,
  LayoutGrid, Maximize, Image, Sparkles, ExternalLink,
  Copy, Check, RefreshCw, Power, AlertTriangle, Loader2,
  ChevronDown,
} from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { api, ApiError } from '@/lib/api';

// ─── Types (matching backend) ──────────────────────────────

interface WallSettingsData {
  id: number;
  event_id: number;
  wall_code: string;
  is_enabled: boolean;
  status: string;
  status_label: string;
  public_url: string;
  settings: {
    interval_ms: number;
    queue_limit: number;
    layout: string;
    transition_effect: string;
    background_url: string | null;
    partner_logo_url: string | null;
    show_qr: boolean;
    show_branding: boolean;
    show_neon: boolean;
    neon_text: string | null;
    neon_color: string | null;
    show_sender_credit: boolean;
    instructions_text: string | null;
  };
}

interface WallOptions {
  layouts: { value: string; label: string }[];
  transitions: { value: string; label: string }[];
  statuses: { value: string; label: string }[];
}

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-neutral-500/20 text-neutral-300 border-neutral-500/30',
  live: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
  paused: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
  stopped: 'bg-red-500/20 text-red-300 border-red-500/30',
  expired: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/30',
};

// TODO: Replace with real event ID from context/route params
const CURRENT_EVENT_ID = 1;

export default function WallPage() {
  const { toast } = useToast();
  const [settings, setSettings] = useState<WallSettingsData | null>(null);
  const [options, setOptions] = useState<WallOptions | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isUpdating, setIsUpdating] = useState(false);
  const [copied, setCopied] = useState(false);

  // ─── Fetch Data ────────────────────────────────────────

  const fetchSettings = useCallback(async () => {
    try {
      const data = await api.get<WallSettingsData>(`/events/${CURRENT_EVENT_ID}/wall/settings`);
      setSettings(data);
    } catch (err) {
      toast({ title: 'Erro', description: 'Falha ao carregar configurações do wall.', variant: 'destructive' });
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  const fetchOptions = useCallback(async () => {
    try {
      const data = await api.get<WallOptions>('/wall/options');
      setOptions(data);
    } catch {
      // Silently fail — options are nice-to-have
    }
  }, []);

  useEffect(() => {
    void fetchSettings();
    void fetchOptions();
  }, [fetchSettings, fetchOptions]);

  // ─── Update Settings ──────────────────────────────────

  const updateSetting = async (patch: Record<string, unknown>) => {
    setIsUpdating(true);
    try {
      const data = await api.patch<WallSettingsData>(`/events/${CURRENT_EVENT_ID}/wall/settings`, { body: patch });
      setSettings(data);
      toast({ title: 'Salvo', description: 'Configuração atualizada.' });
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Falha ao salvar.';
      toast({ title: 'Erro', description: msg, variant: 'destructive' });
    } finally {
      setIsUpdating(false);
    }
  };

  // ─── Status Controls ──────────────────────────────────

  const runStatusAction = async (action: string, label: string) => {
    setIsUpdating(true);
    try {
      await api.post(`/events/${CURRENT_EVENT_ID}/wall/${action}`);
      await fetchSettings();
      toast({ title: label, description: `Ação "${label}" executada.` });
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Falha na ação.';
      toast({ title: 'Erro', description: msg, variant: 'destructive' });
    } finally {
      setIsUpdating(false);
    }
  };

  // ─── Helpers ──────────────────────────────────────────

  const copyWallCode = () => {
    if (settings?.wall_code) {
      navigator.clipboard.writeText(settings.wall_code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const openPlayerUrl = () => {
    if (settings?.wall_code) {
      window.open(`/wall/player/${settings.wall_code}`, '_blank');
    }
  };

  // ─── Loading State ────────────────────────────────────

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[50vh]">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  const s = settings?.settings;
  const status = settings?.status || 'draft';
  const isLive = status === 'live';
  const isPaused = status === 'paused';
  const isTerminal = status === 'expired' || status === 'stopped';

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Wall / Telão"
        description="Configure e controle o slideshow para telão em tempo real"
        actions={
          <div className="flex items-center gap-2">
            {/* Status badge */}
            <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-wider ${STATUS_COLORS[status] || STATUS_COLORS.draft}`}>
              <span className={`h-1.5 w-1.5 rounded-full ${isLive ? 'animate-pulse bg-emerald-400' : 'bg-current opacity-50'}`} />
              {settings?.status_label || status}
            </span>

            {/* Control buttons */}
            {isLive ? (
              <Button variant="destructive" size="sm" onClick={() => runStatusAction('pause', 'Pausar')} disabled={isUpdating}>
                <Pause className="h-4 w-4 mr-1" /> Pausar
              </Button>
            ) : isPaused ? (
              <Button className="gradient-primary border-0" size="sm" onClick={() => runStatusAction('start', 'Resumir')} disabled={isUpdating}>
                <Play className="h-4 w-4 mr-1" /> Resumir
              </Button>
            ) : !isTerminal ? (
              <Button className="gradient-primary border-0" size="sm" onClick={() => runStatusAction('start', 'Iniciar')} disabled={isUpdating}>
                <Play className="h-4 w-4 mr-1" /> Iniciar Wall
              </Button>
            ) : (
              <Button variant="outline" size="sm" onClick={() => runStatusAction('reset', 'Resetar')} disabled={isUpdating}>
                <RefreshCw className="h-4 w-4 mr-1" /> Resetar
              </Button>
            )}
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* ─── Preview + Link ──────────────────────── */}
        <div className="lg:col-span-2 space-y-4">
          {/* Preview */}
          <div className="glass rounded-xl overflow-hidden">
            <div className="aspect-video bg-muted/30 relative flex items-center justify-center">
              {isLive || isPaused ? (
                <div className="absolute inset-0 bg-gradient-to-br from-neutral-900 via-neutral-950 to-neutral-900 flex items-center justify-center">
                  <div className="text-center space-y-3">
                    <Monitor className="h-16 w-16 text-orange-400/80 mx-auto" />
                    <p className="text-lg font-medium text-white/80">
                      {isLive ? 'Telão ativo — exibindo slides' : 'Telão pausado'}
                    </p>
                    <Button variant="outline" size="sm" onClick={openPlayerUrl}>
                      <ExternalLink className="h-3.5 w-3.5 mr-1.5" />
                      Abrir player em nova aba
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="text-center space-y-3">
                  <Monitor className="h-12 w-12 text-muted-foreground mx-auto" />
                  <p className="text-sm text-muted-foreground">Clique em "Iniciar Wall" para visualizar</p>
                </div>
              )}
            </div>
          </div>

          {/* Wall Code + URL */}
          <div className="glass rounded-xl p-4 flex flex-wrap items-center gap-4">
            <div className="flex-1 min-w-[200px]">
              <p className="text-xs uppercase tracking-wider text-muted-foreground mb-1">Código do Telão</p>
              <div className="flex items-center gap-2">
                <code className="text-xl font-mono font-bold tracking-[0.15em]">{settings?.wall_code || '—'}</code>
                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={copyWallCode}>
                  {copied ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5" />}
                </Button>
              </div>
            </div>
            <Button variant="outline" size="sm" onClick={openPlayerUrl} disabled={!settings?.wall_code}>
              <ExternalLink className="h-3.5 w-3.5 mr-1.5" />
              Abrir Player
            </Button>
          </div>

          {/* Danger zone */}
          {!isTerminal && (isLive || isPaused) ? (
            <div className="glass rounded-xl p-4 border border-destructive/20">
              <h3 className="text-sm font-semibold flex items-center gap-2 text-destructive">
                <AlertTriangle className="h-4 w-4" /> Ações avançadas
              </h3>
              <div className="flex flex-wrap gap-2 mt-3">
                <Button variant="destructive" size="sm" onClick={() => runStatusAction('full-stop', 'Parar')} disabled={isUpdating}>
                  <Square className="h-3.5 w-3.5 mr-1" /> Parar completamente
                </Button>
                <Button variant="destructive" size="sm" onClick={() => runStatusAction('expire', 'Expirar')} disabled={isUpdating}>
                  <Power className="h-3.5 w-3.5 mr-1" /> Encerrar (expirar)
                </Button>
              </div>
            </div>
          ) : null}
        </div>

        {/* ─── Settings Panel ──────────────────────── */}
        <div className="space-y-4">
          {/* Slide settings */}
          <div className="glass rounded-xl p-5 space-y-4">
            <h3 className="text-sm font-semibold flex items-center gap-2"><Settings className="h-4 w-4" /> Configurações</h3>

            <div>
              <Label className="text-xs">Tempo por Slide</Label>
              <div className="flex items-center gap-3 mt-2">
                <Slider
                  value={[Math.round((s?.interval_ms || 8000) / 1000)]}
                  onValueChange={([val]) => updateSetting({ interval_ms: val * 1000 })}
                  min={2} max={60} step={1}
                  className="flex-1"
                />
                <span className="text-sm font-medium w-10 text-right">{Math.round((s?.interval_ms || 8000) / 1000)}s</span>
              </div>
            </div>

            <div>
              <Label className="text-xs">Limite da Fila</Label>
              <div className="flex items-center gap-3 mt-2">
                <Slider
                  value={[s?.queue_limit || 100]}
                  onValueChange={([val]) => updateSetting({ queue_limit: val })}
                  min={5} max={500} step={5}
                  className="flex-1"
                />
                <span className="text-sm font-medium w-10 text-right">{s?.queue_limit || 100}</span>
              </div>
            </div>

            <div className="flex items-center justify-between">
              <div><Label className="text-xs">QR Code Overlay</Label><p className="text-[10px] text-muted-foreground">Exibir QR no canto da tela</p></div>
              <Switch checked={s?.show_qr ?? true} onCheckedChange={(v) => updateSetting({ show_qr: v })} />
            </div>

            <div className="flex items-center justify-between">
              <div><Label className="text-xs">Crédito do Remetente</Label><p className="text-[10px] text-muted-foreground">Mostrar quem enviou a foto</p></div>
              <Switch checked={s?.show_sender_credit ?? false} onCheckedChange={(v) => updateSetting({ show_sender_credit: v })} />
            </div>

            <div className="flex items-center justify-between">
              <div><Label className="text-xs">Efeito Neon</Label><p className="text-[10px] text-muted-foreground">Texto neon no canto do telão</p></div>
              <Switch checked={s?.show_neon ?? false} onCheckedChange={(v) => updateSetting({ show_neon: v })} />
            </div>

            {s?.show_neon ? (
              <div className="space-y-2 pl-2 border-l-2 border-orange-500/30">
                <Input
                  placeholder="Texto do neon"
                  defaultValue={s?.neon_text || ''}
                  onBlur={(e) => updateSetting({ neon_text: e.target.value })}
                  className="h-8 text-xs"
                />
                <Input
                  type="color"
                  defaultValue={s?.neon_color || '#ffffff'}
                  onChange={(e) => updateSetting({ neon_color: e.target.value })}
                  className="h-8 w-20"
                />
              </div>
            ) : null}
          </div>

          {/* Layout */}
          <div className="glass rounded-xl p-5 space-y-3">
            <h3 className="text-sm font-semibold">Layout do Wall</h3>
            <Select
              value={s?.layout || 'auto'}
              onValueChange={(v) => updateSetting({ layout: v })}
            >
              <SelectTrigger className="h-9 text-xs">
                <SelectValue placeholder="Layout" />
              </SelectTrigger>
              <SelectContent>
                {(options?.layouts || [
                  { value: 'auto', label: 'Automático' },
                  { value: 'fullscreen', label: 'Tela Cheia' },
                  { value: 'polaroid', label: 'Polaroid' },
                  { value: 'split', label: 'Split' },
                  { value: 'cinematic', label: 'Cinematográfico' },
                ]).map((l) => (
                  <SelectItem key={l.value} value={l.value}>{l.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <h3 className="text-sm font-semibold mt-4">Transição</h3>
            <Select
              value={s?.transition_effect || 'fade'}
              onValueChange={(v) => updateSetting({ transition_effect: v })}
            >
              <SelectTrigger className="h-9 text-xs">
                <SelectValue placeholder="Transição" />
              </SelectTrigger>
              <SelectContent>
                {(options?.transitions || [
                  { value: 'fade', label: 'Fade' },
                  { value: 'slide', label: 'Slide' },
                  { value: 'zoom', label: 'Zoom' },
                  { value: 'flip', label: 'Flip' },
                  { value: 'none', label: 'Nenhuma' },
                ]).map((t) => (
                  <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Instructions */}
          <div className="glass rounded-xl p-5 space-y-3">
            <h3 className="text-sm font-semibold">Instruções (tela idle)</h3>
            <Textarea
              placeholder="Texto exibido quando o telão está sem fotos..."
              defaultValue={s?.instructions_text || ''}
              onBlur={(e) => updateSetting({ instructions_text: e.target.value })}
              className="text-xs min-h-[80px]"
            />
          </div>
        </div>
      </div>
    </motion.div>
  );
}
