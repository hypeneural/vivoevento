export type LoginInitialStep = 'method' | 'forgot';

export type InvitationReturnContext = {
  kind: 'organization' | 'event';
  badge: string;
  title: string;
  description: string;
};

function getSearchParams(search: string) {
  return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
}

export function resolveLoginReturnPath(search: string, fallback = '/') {
  const params = getSearchParams(search);
  const returnTo = params.get('returnTo');

  if (!returnTo || !returnTo.startsWith('/') || returnTo.startsWith('//')) {
    return fallback;
  }

  return returnTo;
}

export function resolveLoginInitialStep(search: string): LoginInitialStep {
  const flow = getSearchParams(search).get('flow');

  return flow === 'forgot' ? 'forgot' : 'method';
}

export function resolveInvitationReturnContext(returnPath: string): InvitationReturnContext | null {
  if (returnPath.startsWith('/convites/equipe/')) {
    return {
      kind: 'organization',
      badge: 'Convite da equipe',
      title: 'Você está entrando para continuar um convite da equipe.',
      description: 'Depois de entrar ou redefinir a senha, você voltará para este convite. Essa mesma conta pode ser usada em vários eventos e convites.',
    };
  }

  if (returnPath.startsWith('/convites/eventos/')) {
    return {
      kind: 'event',
      badge: 'Convite de evento',
      title: 'Você está entrando para continuar um convite de evento.',
      description: 'Depois de entrar ou redefinir a senha, você voltará para este convite. Essa mesma conta pode ser usada em vários eventos e convites.',
    };
  }

  return null;
}

export function buildLoginPath(returnTo: string, options?: { flow?: 'forgot' }) {
  const params = new URLSearchParams();
  params.set('returnTo', returnTo);

  if (options?.flow === 'forgot') {
    params.set('flow', 'forgot');
  }

  return `/login?${params.toString()}`;
}
