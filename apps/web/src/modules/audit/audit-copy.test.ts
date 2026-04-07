import { describe, expect, it } from 'vitest';

import { formatAuditDescription, formatAuditEventLabel, formatAuditFieldLabel } from './audit-copy';

describe('audit-copy', () => {
  it('traduz descricoes passivas em ingles para portugues simples', () => {
    expect(formatAuditDescription('Event was updated', 'event')).toBe('Evento atualizado');
    expect(formatAuditDescription('User was Created', 'user')).toBe('Usuario criado');
    expect(formatAuditDescription('organization was creted', 'organization')).toBe('Organizacao criada');
  });

  it('traduz descricoes tecnicas mistas para termos mais claros', () => {
    expect(formatAuditDescription('Hero image do hub atualizada')).toBe('Imagem principal da pagina de links atualizada');
    expect(formatAuditDescription('Configuracao de FaceSearch atualizada')).toBe('Configuracao da busca por selfie atualizada');
    expect(formatAuditDescription('Cadastro concluido com OTP via WhatsApp')).toBe('Cadastro concluido com codigo de verificacao via WhatsApp');
  });

  it('traduz codigos de evento da auditoria', () => {
    expect(formatAuditEventLabel('media.approved')).toBe('Midia aprovada');
    expect(formatAuditEventLabel('auth.register')).toBe('Cadastro realizado');
    expect(formatAuditEventLabel('wall.settings.updated')).toBe('Configuracoes do telao atualizadas');
  });

  it('traduz nomes de campos tecnicos', () => {
    expect(formatAuditFieldLabel('organization_id')).toBe('ID da organizacao');
    expect(formatAuditFieldLabel('contact_email')).toBe('E-mail de contato');
    expect(formatAuditFieldLabel('published_at')).toBe('Publicado em');
  });
});
