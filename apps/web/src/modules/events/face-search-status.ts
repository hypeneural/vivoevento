import type { ApiEventFaceSearchSettings } from '@/lib/api-types';

export interface EventFaceSearchOperationalStatus {
  label: string;
  description: string;
  notes: string[];
  tone: 'neutral' | 'info' | 'warning' | 'success';
}

export function resolveEventFaceSearchOperationalStatus(
  settings?: ApiEventFaceSearchSettings | null,
): EventFaceSearchOperationalStatus {
  const summary = settings?.operational_summary;

  if (summary) {
    const pendingCount = summary.counts.queued_media + summary.counts.processing_media;
    const notes: string[] = [];

    if (pendingCount > 0) {
      notes.push(`${pendingCount} foto(s) antiga(s) ainda estao em preparacao para a busca.`);
    }

    if (summary.search_mode === 'users' && summary.counts.distinct_ready_users > 0) {
      notes.push(`${summary.counts.distinct_ready_users} pessoa(s) ja estao prontas na busca principal da AWS.`);
    } else if (summary.counts.searchable_records > 0) {
      notes.push(`${summary.counts.searchable_records} foto(s) ja estao prontas para busca.`);
    }

    if (summary.requires_attention && summary.counts.failed_media > 0) {
      notes.push(`${summary.counts.failed_media} item(ns) tiveram falha e precisam de nova preparacao ou conferencia tecnica.`);
    }

    switch (summary.status) {
      case 'disabled':
        return {
          label: 'Desligado',
          description: 'O reconhecimento facial ainda nao foi ativado para este evento.',
          notes,
          tone: 'neutral',
        };
      case 'local_only':
        return {
          label: 'Ligado localmente',
          description: settings?.allow_public_selfie_search
            ? 'A busca ja pode ser usada por convidados, mas a estrutura principal da AWS ainda nao entrou neste evento.'
            : 'A busca esta ativa em modo interno e ainda nao depende da estrutura principal da AWS.',
          notes,
          tone: 'info',
        };
      case 'provisioning':
        return {
          label: 'Preparando estrutura',
          description: 'A estrutura da AWS ainda esta sendo criada para receber as fotos deste evento.',
          notes,
          tone: 'info',
        };
      case 'converging':
        return {
          label: 'Indexando fotos antigas',
          description: settings?.allow_public_selfie_search
            ? 'O acervo antigo ainda esta convergindo antes de a busca ficar totalmente estavel para convidados.'
            : 'O acervo antigo ainda esta sendo preparado enquanto a equipe termina a validacao interna.',
          notes,
          tone: 'warning',
        };
      case 'ready_for_internal_validation':
        return {
          label: 'Pronto para validacao interna',
          description: 'O acervo antigo ja terminou de convergir. Falta apenas liberar a busca para convidados.',
          notes,
          tone: 'info',
        };
      case 'ready_for_guests':
        return {
          label: 'Pronto para convidados',
          description: 'O acervo antigo ja terminou de convergir e a busca para convidados pode ser usada.',
          notes,
          tone: 'success',
        };
      default:
        break;
    }
  }

  if (!settings?.enabled) {
    return {
      label: 'Desligado',
      description: 'O reconhecimento facial ainda nao foi ativado para este evento.',
      notes: [],
      tone: 'neutral',
    };
  }

  if (settings.search_backend_key !== 'aws_rekognition' || !settings.recognition_enabled) {
    return {
      label: 'Ligado localmente',
      description: settings.allow_public_selfie_search
        ? 'A busca ja pode ser usada por convidados, mas a estrutura principal da AWS ainda nao entrou neste evento.'
        : 'A busca esta ativa em modo interno e ainda nao depende da estrutura principal da AWS.',
      notes: [],
      tone: 'info',
    };
  }

  if (!settings.aws_collection_id) {
    return {
      label: 'Preparando estrutura',
      description: 'A estrutura da AWS ainda esta sendo criada para receber as fotos deste evento.',
      notes: [],
      tone: 'info',
    };
  }

  if (!settings.allow_public_selfie_search) {
    return {
      label: 'Pronto para validacao interna',
      description: 'A busca ja esta pronta para testes da equipe, mas ainda nao foi liberada para convidados.',
      notes: [],
      tone: 'info',
    };
  }

  return {
    label: 'Pronto para convidados',
    description: 'Convidados ja podem enviar uma selfie para encontrar fotos publicadas deste evento.',
    notes: [],
    tone: 'success',
  };
}
