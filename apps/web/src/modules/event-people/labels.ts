import type {
  EventPersonAssignmentStatus,
  EventPersonReviewQueueStatus,
  EventPersonSide,
  EventPersonStatus,
  EventPersonType,
} from './types';

export const EVENT_PERSON_TYPE_OPTIONS: Array<{ value: EventPersonType; label: string }> = [
  { value: 'guest', label: 'Convidado' },
  { value: 'bride', label: 'Noiva' },
  { value: 'groom', label: 'Noivo' },
  { value: 'mother', label: 'Mae' },
  { value: 'father', label: 'Pai' },
  { value: 'sibling', label: 'Irmao(irma)' },
  { value: 'friend', label: 'Amigo(a)' },
  { value: 'groomsman', label: 'Padrinho' },
  { value: 'bridesmaid', label: 'Madrinha' },
  { value: 'vendor', label: 'Fornecedor' },
  { value: 'staff', label: 'Equipe' },
  { value: 'speaker', label: 'Palestrante' },
  { value: 'artist', label: 'Artista' },
  { value: 'executive', label: 'Executivo' },
];

export const EVENT_PERSON_SIDE_OPTIONS: Array<{ value: EventPersonSide; label: string }> = [
  { value: 'neutral', label: 'Sem lado' },
  { value: 'bride_side', label: 'Lado da noiva' },
  { value: 'groom_side', label: 'Lado do noivo' },
  { value: 'host_side', label: 'Lado anfitriao' },
  { value: 'company_side', label: 'Lado da empresa' },
];

export const EVENT_PERSON_STATUS_OPTIONS: Array<{ value: EventPersonStatus | 'all'; label: string }> = [
  { value: 'all', label: 'Todos' },
  { value: 'active', label: 'Ativos' },
  { value: 'draft', label: 'Rascunhos' },
  { value: 'hidden', label: 'Ocultos' },
];

export function formatEventPersonType(value?: string | null) {
  const match = EVENT_PERSON_TYPE_OPTIONS.find((option) => option.value === value);

  return match?.label ?? null;
}

export function formatEventPersonSide(value?: string | null) {
  const match = EVENT_PERSON_SIDE_OPTIONS.find((option) => option.value === value);

  return match?.label ?? null;
}

export function formatEventPersonStatus(value?: string | null) {
  switch (value) {
    case 'active':
      return 'Ativa';
    case 'draft':
      return 'Rascunho';
    case 'hidden':
      return 'Oculta';
    default:
      return value ?? 'Sem status';
  }
}

export function formatEventPersonMeta(input: { type?: string | null; side?: string | null }, fallback = 'Pessoa do evento') {
  const parts = [formatEventPersonType(input.type), formatEventPersonSide(input.side)].filter(Boolean);

  return parts.length > 0 ? parts.join(' - ') : fallback;
}

export function formatEventPersonRoleFamily(value?: string | null) {
  switch (value) {
    case 'principal':
      return 'Pessoas principais';
    case 'familia':
      return 'Familia';
    case 'corte':
      return 'Corte ou padrinhos';
    case 'amigos':
      return 'Amigos';
    case 'fornecedor':
      return 'Equipe e fornecedores';
    case 'equipe':
      return 'Equipe';
    case 'academico':
      return 'Academico';
    case 'corporativo':
      return 'Corporativo';
    default:
      return 'Outros papeis';
  }
}

export function formatEventPersonRelationType(value?: string | null) {
  switch (value) {
    case 'spouse_of':
      return 'Conjuge de';
    case 'mother_of':
      return 'Mae de';
    case 'father_of':
      return 'Pai de';
    case 'sibling_of':
      return 'Irmao de';
    case 'child_of':
      return 'Filho(a) de';
    case 'friend_of':
      return 'Amigo de';
    case 'vendor_of_event':
      return 'Fornecedor do evento';
    case 'photographer_of_event':
      return 'Fotografo do evento';
    case 'ceremonialist_of_event':
      return 'Cerimonialista do evento';
    case 'works_with':
      return 'Trabalha com';
    case 'teammate_of':
      return 'Colega de equipe';
    case 'manager_of':
      return 'Gestor de';
    case 'speaker_with':
      return 'Participa com';
    case 'sponsor_of':
      return 'Patrocinador de';
    default:
      return value ?? 'Relacao';
  }
}

export function formatEventPersonSyncStatus(value?: string | null) {
  switch (value) {
    case 'synced':
      return 'Sincronizado';
    case 'pending':
      return 'Na fila';
    case 'failed':
      return 'Falhou';
    case 'skipped':
      return 'Ignorado';
    default:
      return value ?? 'Sem status';
  }
}

export function formatEventPersonReferencePurpose(value?: string | null) {
  switch (value) {
    case 'avatar':
      return 'Avatar';
    case 'matching':
      return 'Matching';
    case 'both':
      return 'Avatar e matching';
    default:
      return value ?? 'Referencia';
  }
}

export function formatEventPersonReferenceStatus(value?: string | null) {
  switch (value) {
    case 'active':
      return 'Ativa';
    case 'archived':
      return 'Arquivada';
    case 'invalid':
      return 'Invalida';
    default:
      return value ?? 'Sem status';
  }
}

export function formatEventPersonReviewStatus(value?: EventPersonReviewQueueStatus | string | null) {
  switch (value) {
    case 'pending':
      return 'Pendente';
    case 'conflict':
      return 'Conflito';
    case 'resolved':
      return 'Resolvido';
    case 'ignored':
      return 'Ignorado';
    default:
      return value ?? 'Em revisao';
  }
}

export function formatEventPersonAssignmentStatus(value?: EventPersonAssignmentStatus | string | null) {
  switch (value) {
    case 'suggested':
      return 'Sugerida';
    case 'confirmed':
      return 'Confirmada';
    case 'rejected':
      return 'Corrigida';
    default:
      return value ?? 'Sem status';
  }
}

export function formatEventPersonQualityTier(value?: string | null) {
  switch (value) {
    case 'search_priority':
      return 'Busca prioritaria';
    case 'index_only':
      return 'Somente indice';
    case 'rejected':
      return 'Rejeitada';
    default:
      return value ?? 'Qualidade nao informada';
  }
}
