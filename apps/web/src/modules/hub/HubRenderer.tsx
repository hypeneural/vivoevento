import { useEffect, useState } from 'react';
import { CalendarDays, MapPin, Sparkles } from 'lucide-react';

import type {
  ApiHubButton,
  ApiHubBuilderConfig,
  ApiHubButtonStyle,
} from '@/lib/api-types';
import { resolveAssetUrl } from '@/lib/assets';
import { cn } from '@/lib/utils';

import { trackPublicHubButtonClick } from './api';
import { getHubIcon } from './hub-icons';

interface HubRendererEvent {
  title: string;
  slug?: string | null;
  starts_at?: string | null;
  location_name?: string | null;
  description?: string | null;
  logo_url?: string | null;
  primary_color?: string | null;
  secondary_color?: string | null;
}

interface HubRendererHub {
  headline?: string | null;
  subheadline?: string | null;
  welcome_text?: string | null;
  hero_image_url?: string | null;
  button_style: ApiHubButtonStyle;
  buttons: ApiHubButton[];
  builder_config: ApiHubBuilderConfig;
  is_enabled?: boolean;
}

interface HubRendererProps {
  event: HubRendererEvent;
  hub: HubRendererHub;
  className?: string;
  innerClassName?: string;
  previewMode?: boolean;
}

const heroHeights = {
  sm: 'min-h-[220px]',
  md: 'min-h-[270px]',
  lg: 'min-h-[330px]',
} satisfies Record<ApiHubBuilderConfig['blocks']['hero']['height'], string>;

const ctaSizes = {
  sm: 'min-h-[56px]',
  md: 'min-h-[64px]',
  lg: 'min-h-[72px]',
} satisfies Record<ApiHubBuilderConfig['blocks']['cta_list']['size'], string>;

export function HubRenderer({
  event,
  hub,
  className,
  innerClassName,
  previewMode = false,
}: HubRendererProps) {
  const { builder_config: builder } = hub;
  const tokens = builder.theme_tokens;
  const dateLabel = formatDateLabel(event.starts_at);
  const subtitle = hub.subheadline || [dateLabel, event.location_name].filter(Boolean).join(' - ');
  const primary = event.primary_color ?? tokens.page_accent;
  const secondary = event.secondary_color ?? tokens.page_accent;
  const badgeLabel = builder.blocks.hero.show_badge
    ? previewMode
      ? (hub.is_enabled ? 'Links ativos' : 'Links pausados')
      : 'Evento Vivo'
    : null;

  const visibleSections = builder.block_order.filter((blockKey) => {
    if (blockKey === 'hero') return builder.blocks.hero.enabled;
    if (blockKey === 'meta_cards') return builder.blocks.meta_cards.enabled;
    if (blockKey === 'welcome') return builder.blocks.welcome.enabled;
    if (blockKey === 'countdown') return builder.blocks.countdown.enabled;
    if (blockKey === 'info_grid') return builder.blocks.info_grid.enabled;
    if (blockKey === 'cta_list') return builder.blocks.cta_list.enabled;
    if (blockKey === 'social_strip') return builder.blocks.social_strip.enabled;
    return builder.blocks.sponsor_strip.enabled;
  });

  return (
    <div
      className={cn('min-h-full', className)}
      style={{
        backgroundColor: tokens.page_background,
        backgroundImage: `radial-gradient(circle at top, ${hexToRgba(tokens.page_accent, 0.34)} 0%, transparent 42%), linear-gradient(180deg, ${tokens.page_background} 0%, ${mixHex(tokens.page_background, '#000000', 0.2)} 100%)`,
      }}
    >
      <div className={cn('mx-auto flex min-h-full w-full max-w-md flex-col gap-4 px-4 pb-10 pt-4', innerClassName)}>
        <div
          className="overflow-hidden rounded-[2rem] border shadow-2xl shadow-slate-950/20"
          style={{
            borderColor: hexToRgba(tokens.surface_border, tokens.page_background === '#f8fafc' ? 0.9 : 0.4),
            backgroundColor: hexToRgba(tokens.surface_background, tokens.page_background === '#f8fafc' ? 0.94 : 0.4),
            color: tokens.text_primary,
            backdropFilter: 'blur(18px)',
          }}
        >
          {visibleSections.map((sectionKey) => {
            if (sectionKey === 'hero') {
              return (
                <HeroSection
                  key={sectionKey}
                  event={event}
                  hub={hub}
                  primary={primary}
                  secondary={secondary}
                  subtitle={subtitle}
                  dateLabel={dateLabel}
                  badgeLabel={badgeLabel}
                />
              );
            }

            if (sectionKey === 'meta_cards') {
              return (
                <div key={sectionKey} className="px-4 pt-4">
                  <MetaCardsSection event={event} builder={builder} dateLabel={dateLabel} />
                </div>
              );
            }

            if (sectionKey === 'welcome') {
              return (
                <div key={sectionKey} className="px-4 pt-4">
                  <WelcomeSection event={event} hub={hub} />
                </div>
              );
            }

            if (sectionKey === 'countdown') {
              return (
                <div key={sectionKey} className="px-4 pt-4">
                  <CountdownSection event={event} hub={hub} previewMode={previewMode} />
                </div>
              );
            }

            if (sectionKey === 'info_grid') {
              return (
                <div key={sectionKey} className="px-4 pt-4">
                  <InfoGridSection event={event} hub={hub} previewMode={previewMode} />
                </div>
              );
            }

            if (sectionKey === 'social_strip') {
              return (
                <div key={sectionKey} className="px-4 pb-4 pt-1">
                  <SocialStripSection event={event} hub={hub} previewMode={previewMode} />
                </div>
              );
            }

            if (sectionKey === 'sponsor_strip') {
              return (
                <div key={sectionKey} className="px-4 pb-4 pt-1">
                  <SponsorStripSection event={event} hub={hub} previewMode={previewMode} />
                </div>
              );
            }

            return (
              <div key={sectionKey} className="px-4 pb-4 pt-4">
                <CtaSection event={event} hub={hub} previewMode={previewMode} />
              </div>
            );
          })}

          {builder.blocks.cta_list.enabled && hub.buttons.length === 0 ? (
            <div className="p-4 pt-0">
              <div
                className="rounded-3xl border border-dashed px-4 py-8 text-center text-sm"
                style={{
                  borderColor: hexToRgba(tokens.surface_border, 0.5),
                  color: tokens.text_secondary,
                  backgroundColor: hexToRgba(tokens.surface_background, 0.3),
                }}
              >
                Nenhum link publico foi configurado ainda nesta pagina.
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function HeroSection({
  event,
  hub,
  primary,
  secondary,
  subtitle,
  dateLabel,
  badgeLabel,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  primary: string;
  secondary: string;
  subtitle: string;
  dateLabel: string | null;
  badgeLabel: string | null;
}) {
  const { builder_config: builder } = hub;
  const tokens = builder.theme_tokens;

  return (
    <div
      className={cn('relative overflow-hidden px-5 pb-5 pt-6', heroHeights[builder.blocks.hero.height])}
      style={{
        background: `linear-gradient(155deg, ${primary}, ${secondary})`,
        color: tokens.text_primary,
      }}
    >
      {hub.hero_image_url ? (
        <img
          src={hub.hero_image_url}
          alt={hub.headline ?? event.title}
          className="absolute inset-0 h-full w-full object-cover opacity-30"
        />
      ) : null}

      <div
        className="absolute inset-0"
        style={{
          background: `linear-gradient(180deg, ${hexToRgba(tokens.hero_overlay_color, 0.16)} 0%, ${hexToRgba(tokens.hero_overlay_color, builder.blocks.hero.overlay_opacity / 100)} 100%)`,
        }}
      />

      <div className="relative flex h-full flex-col justify-between gap-6">
        <div className="flex items-start justify-between gap-4">
          <div className="space-y-3">
            {badgeLabel ? (
              <span
                className="inline-flex w-fit items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                style={{
                  backgroundColor: hexToRgba('#ffffff', 0.16),
                  color: tokens.text_primary,
                }}
              >
                {badgeLabel}
              </span>
            ) : null}

            <div className="space-y-1">
              <h1 className={cn('font-semibold leading-tight', builder.layout_key === 'minimal-center' ? 'text-[2rem] text-center sm:text-[2.15rem]' : 'text-3xl')}>
                {hub.headline || event.title}
              </h1>
              {subtitle ? (
                <p
                  className={cn('text-sm', builder.layout_key === 'minimal-center' ? 'text-center' : '')}
                  style={{ color: hexToRgba(tokens.text_primary, 0.82) }}
                >
                  {subtitle}
                </p>
              ) : null}
            </div>
          </div>

          {builder.blocks.hero.show_logo ? (
            event.logo_url ? (
              <img
                src={event.logo_url}
                alt="Logo do evento"
                className="h-14 w-14 rounded-2xl border bg-white/90 object-cover p-2"
                style={{ borderColor: hexToRgba('#ffffff', 0.2) }}
              />
            ) : (
              <div
                className="flex h-14 w-14 items-center justify-center rounded-2xl"
                style={{ backgroundColor: hexToRgba('#ffffff', 0.14) }}
              >
                <Sparkles className="h-6 w-6" />
              </div>
            )
          ) : null}
        </div>

        {builder.blocks.hero.show_meta_cards && builder.blocks.meta_cards.enabled ? (
          <MetaCardsSection event={event} builder={builder} dateLabel={dateLabel} insideHero />
        ) : null}
      </div>
    </div>
  );
}

function MetaCardsSection({
  event,
  builder,
  dateLabel,
  insideHero = false,
}: {
  event: HubRendererEvent;
  builder: ApiHubBuilderConfig;
  dateLabel: string | null;
  insideHero?: boolean;
}) {
  const tokens = builder.theme_tokens;

  if (!builder.blocks.meta_cards.enabled) {
    return null;
  }

  const cardClassName = builder.blocks.meta_cards.style === 'minimal'
    ? 'rounded-2xl border px-3 py-3'
    : 'rounded-2xl px-3 py-3 backdrop-blur';

  const cardStyle = builder.blocks.meta_cards.style === 'solid'
    ? {
        backgroundColor: tokens.surface_background,
        borderColor: tokens.surface_border,
        color: tokens.text_primary,
      }
    : builder.blocks.meta_cards.style === 'minimal'
      ? {
          backgroundColor: 'transparent',
          borderColor: hexToRgba(tokens.surface_border, insideHero ? 0.4 : 0.55),
          color: tokens.text_primary,
        }
      : {
          backgroundColor: insideHero ? hexToRgba('#ffffff', 0.12) : hexToRgba(tokens.surface_background, 0.64),
          borderColor: insideHero ? 'transparent' : hexToRgba(tokens.surface_border, 0.5),
          color: tokens.text_primary,
        };

  return (
    <div className="grid grid-cols-2 gap-3">
      {builder.blocks.meta_cards.show_date && dateLabel ? (
        <div className={cardClassName} style={cardStyle}>
          <p className="text-[11px] uppercase tracking-[0.16em]" style={{ color: hexToRgba(tokens.text_primary, 0.7) }}>
            Data
          </p>
          <p className="mt-1 flex items-center gap-2 text-sm font-medium">
            <CalendarDays className="h-4 w-4" />
            {dateLabel}
          </p>
        </div>
      ) : null}

      {builder.blocks.meta_cards.show_location && event.location_name ? (
        <div className={cardClassName} style={cardStyle}>
          <p className="text-[11px] uppercase tracking-[0.16em]" style={{ color: hexToRgba(tokens.text_primary, 0.7) }}>
            Local
          </p>
          <p className="mt-1 flex items-center gap-2 text-sm font-medium">
            <MapPin className="h-4 w-4" />
            {event.location_name}
          </p>
        </div>
      ) : null}
    </div>
  );
}

function WelcomeSection({
  event,
  hub,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
}) {
  const text = hub.welcome_text || event.description || 'Acesse os links oficiais deste evento.';
  const tokens = hub.builder_config.theme_tokens;
  const variant = hub.builder_config.blocks.welcome.style;

  const className = variant === 'inline'
    ? 'px-1 py-1 text-sm'
    : variant === 'bubble'
      ? 'rounded-[1.75rem] border px-4 py-4 text-sm'
      : 'rounded-3xl border px-4 py-4 text-sm';

  const style = variant === 'inline'
    ? { color: tokens.text_secondary }
    : {
        backgroundColor: hexToRgba(tokens.surface_background, variant === 'bubble' ? 0.42 : 0.68),
        borderColor: hexToRgba(tokens.surface_border, 0.55),
        color: variant === 'bubble' ? tokens.text_primary : tokens.text_secondary,
      };

  return (
    <div className={className} style={style}>
      {text}
    </div>
  );
}

function CountdownSection({
  event,
  hub,
  previewMode,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  previewMode: boolean;
}) {
  const countdown = hub.builder_config.blocks.countdown;
  const tokens = hub.builder_config.theme_tokens;
  const targetAt = countdown.target_mode === 'event_start'
    ? (event.starts_at ?? countdown.target_at)
    : countdown.target_at;
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!countdown.enabled || !targetAt) {
      return undefined;
    }

    const timer = window.setInterval(() => {
      setNow(Date.now());
    }, 1000);

    return () => window.clearInterval(timer);
  }, [countdown.enabled, targetAt]);

  if (!countdown.enabled) {
    return null;
  }

  if (!targetAt) {
    if (!previewMode) {
      return null;
    }

    return (
      <div
        className="rounded-3xl border border-dashed px-4 py-5 text-sm"
        style={{
          borderColor: hexToRgba(tokens.surface_border, 0.5),
          color: tokens.text_secondary,
          backgroundColor: hexToRgba(tokens.surface_background, 0.35),
        }}
      >
        Defina uma data para ativar a contagem regressiva.
      </div>
    );
  }

  const remaining = countdownBreakdown(targetAt, now);

  if (remaining.completed && countdown.hide_after_start && !previewMode) {
    return null;
  }

  const targetLabel = formatDateTimeLabel(targetAt);
  const wrapperStyle = countdown.style === 'cards'
    ? {
        backgroundColor: hexToRgba(tokens.surface_background, 0.55),
        borderColor: hexToRgba(tokens.surface_border, 0.5),
      }
    : countdown.style === 'inline'
      ? {
          backgroundColor: hexToRgba(tokens.page_accent, 0.12),
          borderColor: hexToRgba(tokens.surface_border, 0.35),
        }
      : {
          backgroundColor: 'transparent',
          borderColor: hexToRgba(tokens.surface_border, 0.4),
        };

  if (remaining.completed) {
    return (
      <div className="rounded-3xl border px-4 py-4" style={wrapperStyle}>
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.text_secondary }}>
          {countdown.title}
        </p>
        <p className="mt-2 text-lg font-semibold" style={{ color: tokens.text_primary }}>
          {countdown.completed_message}
        </p>
        {targetLabel ? (
          <p className="mt-1 text-sm" style={{ color: tokens.text_secondary }}>
            {targetLabel}
          </p>
        ) : null}
      </div>
    );
  }

  return (
    <div className="rounded-3xl border px-4 py-4" style={wrapperStyle}>
      <div className={countdown.style === 'inline' ? 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between' : 'space-y-4'}>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.text_secondary }}>
            {countdown.title}
          </p>
          {targetLabel ? (
            <p className="mt-1 text-sm" style={{ color: tokens.text_secondary }}>
              {targetLabel}
            </p>
          ) : null}
        </div>

        {countdown.style === 'inline' ? (
          <p className="text-lg font-semibold" style={{ color: tokens.text_primary }}>
            {remaining.days > 0 ? `${remaining.days}d ` : ''}{padCountdown(remaining.hours)}h {padCountdown(remaining.minutes)}m {padCountdown(remaining.seconds)}s
          </p>
        ) : (
          <div className={countdown.style === 'minimal' ? 'grid grid-cols-4 gap-2' : 'grid grid-cols-4 gap-3'}>
            {[
              ['Dias', remaining.days],
              ['Horas', remaining.hours],
              ['Min', remaining.minutes],
              ['Seg', remaining.seconds],
            ].map(([label, value]) => (
              <div
                key={label}
                className={countdown.style === 'minimal' ? 'rounded-2xl border px-2 py-3 text-center' : 'rounded-2xl border px-3 py-4 text-center'}
                style={{
                  backgroundColor: countdown.style === 'cards'
                    ? hexToRgba(tokens.page_accent, 0.12)
                    : hexToRgba(tokens.surface_background, 0.45),
                  borderColor: hexToRgba(tokens.surface_border, 0.35),
                }}
              >
                <p className="text-xl font-semibold" style={{ color: tokens.text_primary }}>{padCountdown(value as number)}</p>
                <p className="mt-1 text-[11px] uppercase tracking-[0.12em]" style={{ color: tokens.text_secondary }}>{label}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function InfoGridSection({
  event,
  hub,
  previewMode,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  previewMode: boolean;
}) {
  const infoGrid = hub.builder_config.blocks.info_grid;
  const tokens = hub.builder_config.theme_tokens;
  const items = infoGrid.items.filter((item) => item.is_visible);
  const displayItems = items.length > 0 || !previewMode
    ? items
    : [
        { id: 'preview-dress', title: 'Dress code', value: 'Esporte fino', description: 'Chegue pronto para as fotos da noite.', icon: 'sparkles', is_visible: true },
        { id: 'preview-hashtag', title: 'Hashtag', value: '#EventoVivo', description: 'Use nas redes para aparecer no wall.', icon: 'instagram', is_visible: true },
        { id: 'preview-parking', title: 'Estacionamento', value: 'Valet liberado', description: 'Entrada principal a partir das 18h.', icon: 'map-pin', is_visible: true },
      ];

  if (!infoGrid.enabled || displayItems.length === 0) {
    return null;
  }

  const title = infoGrid.title.trim() || 'Informacoes importantes';
  const gridClassName = infoGrid.columns === 3 ? 'grid gap-3 sm:grid-cols-3' : 'grid gap-3 sm:grid-cols-2';

  return (
    <div className="space-y-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.text_secondary }}>
        {title}
      </p>
      <div className={gridClassName}>
        {displayItems.map((item) => {
          const Icon = getHubIcon(item.icon);
          const cardStyle = infoGrid.style === 'highlight'
            ? {
                backgroundColor: hexToRgba(tokens.page_accent, 0.14),
                borderColor: hexToRgba(tokens.surface_border, 0.45),
                color: tokens.text_primary,
              }
            : infoGrid.style === 'minimal'
              ? {
                  backgroundColor: 'transparent',
                  borderColor: hexToRgba(tokens.surface_border, 0.35),
                  color: tokens.text_primary,
                }
              : {
                  backgroundColor: hexToRgba(tokens.surface_background, 0.58),
                  borderColor: hexToRgba(tokens.surface_border, 0.45),
                  color: tokens.text_primary,
                };

          return (
            <div key={item.id} className="rounded-3xl border px-4 py-4" style={cardStyle}>
              <div className="inline-flex h-10 w-10 items-center justify-center rounded-2xl" style={{ backgroundColor: hexToRgba(tokens.page_accent, 0.18) }}>
                <Icon className="h-4 w-4" />
              </div>
              <p className="mt-3 text-[11px] uppercase tracking-[0.14em]" style={{ color: tokens.text_secondary }}>
                {item.title}
              </p>
              <p className="mt-1 text-base font-semibold">{item.value}</p>
              {item.description ? <p className="mt-2 text-sm" style={{ color: tokens.text_secondary }}>{item.description}</p> : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function CtaSection({
  event,
  hub,
  previewMode,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  previewMode: boolean;
}) {
  const tokens = hub.builder_config.theme_tokens;
  const ctaConfig = hub.builder_config.blocks.cta_list;

  return (
    <div className="space-y-3">
      {hub.buttons.map((button) => {
        const Icon = getHubIcon(button.icon);
        const href = button.resolved_url ?? button.href ?? '#';
        const backgroundColor = button.background_color ?? hub.button_style.background_color;
        const textColor = button.text_color ?? hub.button_style.text_color;
        const borderColor = button.outline_color ?? hub.button_style.outline_color;

        const buttonStyle = ctaConfig.style === 'outline'
          ? {
              backgroundColor: 'transparent',
              color: textColor,
              borderColor,
            }
          : ctaConfig.style === 'soft'
            ? {
                backgroundColor: hexToRgba(backgroundColor, 0.18),
                color: textColor,
                borderColor: hexToRgba(borderColor, 0.5),
              }
            : {
                backgroundColor,
                color: textColor,
                borderColor,
              };

        const content = (
          <>
            <span className={cn('flex flex-1 items-center gap-3', ctaConfig.icon_position === 'top' ? 'flex-col text-center' : '')}>
              <span
                className="inline-flex h-10 w-10 items-center justify-center rounded-2xl"
                style={{
                  backgroundColor: ctaConfig.style === 'solid'
                    ? hexToRgba('#000000', 0.12)
                    : hexToRgba(tokens.page_accent, 0.16),
                }}
              >
                <Icon className="h-5 w-5" />
              </span>
              <span className={cn('font-medium', ctaConfig.icon_position === 'top' ? 'text-center text-base' : 'text-base')}>
                {button.label}
              </span>
            </span>
            <span className="text-xs opacity-70">
              {previewMode ? (button.is_available ? 'Abrir' : 'Off') : 'Abrir'}
            </span>
          </>
        );

        const sharedClassName = cn(
          'w-full rounded-3xl border px-4 py-3 shadow-lg shadow-slate-950/10 transition-transform active:scale-[0.99]',
          ctaSizes[ctaConfig.size],
          ctaConfig.icon_position === 'top' ? 'flex flex-col gap-2 text-center' : 'flex items-center justify-between text-left',
        );

        if (previewMode) {
          return (
            <button
              key={button.id}
              type="button"
              className={sharedClassName}
              style={buttonStyle}
            >
              {content}
            </button>
          );
        }

        return (
          <a
            key={button.id}
            href={href}
            target={button.opens_in_new_tab ? '_blank' : undefined}
            rel={button.opens_in_new_tab ? 'noreferrer' : undefined}
            className={sharedClassName}
            style={buttonStyle}
            onClick={() => {
              if (event.slug) {
                trackPublicHubButtonClick(event.slug, button.id);
              }
            }}
          >
            {content}
          </a>
        );
      })}
    </div>
  );
}

function SocialStripSection({
  event,
  hub,
  previewMode,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  previewMode: boolean;
}) {
  const tokens = hub.builder_config.theme_tokens;
  const socialConfig = hub.builder_config.blocks.social_strip;
  const items = socialConfig.items.filter((item) => item.is_visible && (previewMode || item.href));
  const displayItems = items.length > 0 || !previewMode
    ? items
    : [
        { id: 'preview-instagram', provider: 'instagram', label: 'Instagram', href: '#', icon: 'instagram', is_visible: true, opens_in_new_tab: true },
        { id: 'preview-whatsapp', provider: 'whatsapp', label: 'WhatsApp', href: '#', icon: 'message-circle', is_visible: true, opens_in_new_tab: true },
        { id: 'preview-map', provider: 'map', label: 'Mapa', href: '#', icon: 'map-pin', is_visible: true, opens_in_new_tab: true },
      ];

  if (!socialConfig.enabled || displayItems.length === 0) {
    return null;
  }

  const sizeClassName = socialConfig.size === 'sm'
    ? 'min-h-[44px]'
    : socialConfig.size === 'lg'
      ? 'min-h-[64px]'
      : 'min-h-[52px]';

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.text_secondary }}>
          Redes e atalhos
        </p>
        {previewMode ? <span className="text-[11px]" style={{ color: tokens.text_secondary }}>Bloco social</span> : null}
      </div>
      <div className={cn(
        socialConfig.style === 'cards'
          ? 'grid gap-3'
          : 'flex flex-wrap gap-2',
      )}>
        {displayItems.map((item) => {
          const Icon = getHubIcon(item.icon);
          const href = item.href ?? '#';
          const commonStyle = socialConfig.style === 'cards'
            ? {
                backgroundColor: hexToRgba(tokens.surface_background, 0.58),
                borderColor: hexToRgba(tokens.surface_border, 0.55),
                color: tokens.text_primary,
              }
            : socialConfig.style === 'chips'
              ? {
                  backgroundColor: hexToRgba(tokens.page_accent, 0.15),
                  borderColor: hexToRgba(tokens.surface_border, 0.4),
                  color: tokens.text_primary,
                }
              : {
                  backgroundColor: 'transparent',
                  borderColor: hexToRgba(tokens.surface_border, 0.45),
                  color: tokens.text_primary,
                };

          const content = socialConfig.style === 'icons' ? (
            <span className="inline-flex items-center gap-2">
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-2xl" style={{ backgroundColor: hexToRgba(tokens.page_accent, 0.18) }}>
                <Icon className="h-4 w-4" />
              </span>
              <span className="text-sm font-medium">{item.label}</span>
            </span>
          ) : socialConfig.style === 'chips' ? (
            <span className="inline-flex items-center gap-2">
              <Icon className="h-4 w-4" />
              <span className="text-sm font-medium">{item.label}</span>
            </span>
          ) : (
            <span className="flex items-center justify-between gap-3">
              <span className="inline-flex items-center gap-3">
                <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl" style={{ backgroundColor: hexToRgba(tokens.page_accent, 0.16) }}>
                  <Icon className="h-4 w-4" />
                </span>
                <span>
                  <span className="block text-sm font-medium">{item.label}</span>
                  <span className="block text-xs" style={{ color: tokens.text_secondary }}>{socialLabel(item.provider)}</span>
                </span>
              </span>
              <span className="text-xs" style={{ color: tokens.text_secondary }}>Abrir</span>
            </span>
          );

          const className = cn(
            'rounded-3xl border px-4 py-3 transition-transform active:scale-[0.99]',
            sizeClassName,
            socialConfig.style === 'cards' ? 'block w-full' : 'inline-flex items-center',
          );

          if (previewMode) {
            return (
              <button key={item.id} type="button" className={className} style={commonStyle}>
                {content}
              </button>
            );
          }

          return (
            <a
              key={item.id}
              href={href}
              target={item.opens_in_new_tab ? '_blank' : undefined}
              rel={item.opens_in_new_tab ? 'noreferrer' : undefined}
              className={className}
              style={commonStyle}
              onClick={() => {
                if (event.slug) {
                  trackPublicHubButtonClick(event.slug, item.id);
                }
              }}
            >
              {content}
            </a>
          );
        })}
      </div>
    </div>
  );
}

function SponsorStripSection({
  event,
  hub,
  previewMode,
}: {
  event: HubRendererEvent;
  hub: HubRendererHub;
  previewMode: boolean;
}) {
  const sponsors = hub.builder_config.blocks.sponsor_strip;
  const tokens = hub.builder_config.theme_tokens;
  const items = sponsors.items.filter((item) => item.is_visible);
  const displayItems = items.length > 0 || !previewMode
    ? items
    : [
        { id: 'preview-master', name: 'Marca One', subtitle: 'Patrocinador master', logo_path: null, href: '#', is_visible: true, opens_in_new_tab: true },
        { id: 'preview-support', name: 'Studio Two', subtitle: 'Parceiro visual', logo_path: null, href: '#', is_visible: true, opens_in_new_tab: true },
        { id: 'preview-audio', name: 'Audio Lab', subtitle: 'Experiencia sonora', logo_path: null, href: '#', is_visible: true, opens_in_new_tab: true },
      ];

  if (!sponsors.enabled || displayItems.length === 0) {
    return null;
  }

  const title = sponsors.title.trim() || 'Patrocinadores';

  return (
    <div className="space-y-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em]" style={{ color: tokens.text_secondary }}>
        {title}
      </p>
      <div className={cn(
        sponsors.style === 'cards'
          ? 'grid gap-3'
          : sponsors.style === 'compact'
            ? 'flex flex-wrap gap-2'
            : 'grid grid-cols-2 gap-3',
      )}>
        {displayItems.map((item) => {
          const logoUrl = resolveAssetUrl(item.logo_path) || item.logo_path || null;
          const sharedStyle = sponsors.style === 'cards'
            ? {
                backgroundColor: hexToRgba(tokens.surface_background, 0.58),
                borderColor: hexToRgba(tokens.surface_border, 0.45),
                color: tokens.text_primary,
              }
            : sponsors.style === 'compact'
              ? {
                  backgroundColor: hexToRgba(tokens.page_accent, 0.12),
                  borderColor: hexToRgba(tokens.surface_border, 0.35),
                  color: tokens.text_primary,
                }
              : {
                  backgroundColor: hexToRgba(tokens.surface_background, 0.4),
                  borderColor: hexToRgba(tokens.surface_border, 0.4),
                  color: tokens.text_primary,
                };

          const content = sponsors.style === 'cards' ? (
            <span className="flex items-center justify-between gap-3">
              <span className="inline-flex items-center gap-3">
                <SponsorLogo name={item.name} logoUrl={logoUrl} tokens={tokens} />
                <span>
                  <span className="block text-sm font-medium">{item.name}</span>
                  <span className="block text-xs" style={{ color: tokens.text_secondary }}>{item.subtitle ?? 'Parceiro oficial'}</span>
                </span>
              </span>
              {item.href ? <span className="text-xs" style={{ color: tokens.text_secondary }}>Abrir</span> : null}
            </span>
          ) : sponsors.style === 'compact' ? (
            <span className="inline-flex items-center gap-2">
              <SponsorLogo name={item.name} logoUrl={logoUrl} tokens={tokens} compact />
              <span className="text-sm font-medium">{item.name}</span>
            </span>
          ) : (
            <span className="flex min-h-[110px] flex-col items-center justify-center gap-3 px-3 py-4 text-center">
              <SponsorLogo name={item.name} logoUrl={logoUrl} tokens={tokens} large />
              <span className="text-sm font-medium">{item.name}</span>
            </span>
          );

          if (previewMode || !item.href) {
            return (
              <div key={item.id} className={cn('rounded-3xl border px-4 py-3', sponsors.style === 'compact' ? 'inline-flex items-center' : 'block')} style={sharedStyle}>
                {content}
              </div>
            );
          }

          return (
            <a
              key={item.id}
              href={item.href}
              target={item.opens_in_new_tab ? '_blank' : undefined}
              rel={item.opens_in_new_tab ? 'noreferrer' : undefined}
              className={cn('rounded-3xl border px-4 py-3 transition-transform active:scale-[0.99]', sponsors.style === 'compact' ? 'inline-flex items-center' : 'block')}
              style={sharedStyle}
              onClick={() => {
                if (event.slug) {
                  trackPublicHubButtonClick(event.slug, item.id);
                }
              }}
            >
              {content}
            </a>
          );
        })}
      </div>
    </div>
  );
}

function formatDateLabel(value?: string | null) {
  if (!value) return null;

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function formatDateTimeLabel(value?: string | null) {
  if (!value) return null;

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function countdownBreakdown(targetAt: string, now: number) {
  const target = new Date(targetAt).getTime();

  if (Number.isNaN(target) || target <= now) {
    return {
      completed: true,
      days: 0,
      hours: 0,
      minutes: 0,
      seconds: 0,
    };
  }

  const total = target - now;
  const totalSeconds = Math.floor(total / 1000);
  const days = Math.floor(totalSeconds / 86_400);
  const hours = Math.floor((totalSeconds % 86_400) / 3_600);
  const minutes = Math.floor((totalSeconds % 3_600) / 60);
  const seconds = totalSeconds % 60;

  return {
    completed: false,
    days,
    hours,
    minutes,
    seconds,
  };
}

function padCountdown(value: number) {
  return value.toString().padStart(2, '0');
}

function hexToRgba(hex: string, alpha: number) {
  const normalized = hex.replace('#', '');
  const value = normalized.length === 6 ? normalized : '000000';
  const number = Number.parseInt(value, 16);
  const red = (number >> 16) & 255;
  const green = (number >> 8) & 255;
  const blue = number & 255;

  return `rgba(${red}, ${green}, ${blue}, ${Math.max(0, Math.min(alpha, 1))})`;
}

function mixHex(primary: string, secondary: string, ratio: number) {
  const base = parseHex(primary);
  const overlay = parseHex(secondary);
  const weight = Math.max(0, Math.min(ratio, 1));

  const mixed = {
    red: Math.round(base.red * (1 - weight) + overlay.red * weight),
    green: Math.round(base.green * (1 - weight) + overlay.green * weight),
    blue: Math.round(base.blue * (1 - weight) + overlay.blue * weight),
  };

  return `#${toHex(mixed.red)}${toHex(mixed.green)}${toHex(mixed.blue)}`;
}

function parseHex(hex: string) {
  const value = hex.replace('#', '');
  const safe = value.length === 6 ? value : '000000';
  const number = Number.parseInt(safe, 16);

  return {
    red: (number >> 16) & 255,
    green: (number >> 8) & 255,
    blue: number & 255,
  };
}

function toHex(value: number) {
  return value.toString(16).padStart(2, '0');
}

function socialLabel(provider: string) {
  return ({
    instagram: 'Instagram oficial',
    whatsapp: 'Contato rapido',
    tiktok: 'Highlights do evento',
    youtube: 'Video e streaming',
    spotify: 'Playlist oficial',
    website: 'Site oficial',
    map: 'Como chegar',
    tickets: 'Ingressos',
  } as Record<string, string>)[provider] ?? 'Canal oficial';
}

function SponsorLogo({
  name,
  logoUrl,
  tokens,
  compact = false,
  large = false,
}: {
  name: string;
  logoUrl: string | null;
  tokens: ApiHubBuilderConfig['theme_tokens'];
  compact?: boolean;
  large?: boolean;
}) {
  const sizeClassName = large ? 'h-14 w-14' : compact ? 'h-8 w-8' : 'h-10 w-10';

  return (
    <span
      className={cn('inline-flex items-center justify-center overflow-hidden rounded-2xl border', sizeClassName)}
      style={{
        borderColor: hexToRgba(tokens.surface_border, 0.35),
        backgroundColor: hexToRgba(tokens.surface_background, 0.7),
      }}
    >
      {logoUrl ? (
        <img src={logoUrl} alt={name} className="h-full w-full object-cover" />
      ) : (
        <span className={cn('font-semibold', large ? 'text-base' : 'text-xs')} style={{ color: tokens.text_secondary }}>
          {name.slice(0, large ? 3 : 2).toUpperCase()}
        </span>
      )}
    </span>
  );
}
