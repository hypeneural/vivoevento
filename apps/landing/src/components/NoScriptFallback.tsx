/**
 * NoScript Fallback Component
 * Exibe conteúdo principal quando JavaScript está desabilitado
 * 
 * Requirement 30: Exibir conteúdo principal mesmo com JS desabilitado
 * Decision 0.2: Renderizar HTML principal das seções de conteúdo
 */

import styles from './NoScriptFallback.module.scss';

export function NoScriptFallback() {
  return (
    <noscript>
      <div className={styles.noScriptContainer}>
        <div className={styles.noScriptBanner}>
          <svg 
            width="24" 
            height="24" 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2"
            aria-hidden="true"
          >
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <p>
            Para a melhor experiência, habilite JavaScript no seu navegador.
            <br />
            <small>O conteúdo principal está disponível abaixo.</small>
          </p>
        </div>

        <div className={styles.noScriptContent}>
          <header className={styles.header}>
            <h1>Evento Vivo</h1>
            <p className={styles.tagline}>
              Transforme fotos dos convidados em experiências ao vivo
            </p>
          </header>

          <section className={styles.section}>
            <h2>Como funciona</h2>
            <ol className={styles.steps}>
              <li>
                <strong>Convidados enviam</strong> - Por QR Code, WhatsApp, Telegram ou link web
              </li>
              <li>
                <strong>IA organiza</strong> - Moderação inteligente e indexação automática
              </li>
              <li>
                <strong>Vira experiência</strong> - Galeria ao vivo, jogos, telão e busca facial
              </li>
            </ol>
          </section>

          <section className={styles.section}>
            <h2>Recursos principais</h2>
            <ul className={styles.features}>
              <li>
                <strong>Galeria ao vivo</strong> - Fotos aparecem em tempo real conforme chegam
              </li>
              <li>
                <strong>Telão dinâmico</strong> - Layouts profissionais com atualização contínua
              </li>
              <li>
                <strong>Jogos interativos</strong> - Puzzle e memória com fotos do evento
              </li>
              <li>
                <strong>Busca facial</strong> - Encontre suas fotos com uma selfie
              </li>
              <li>
                <strong>Moderação por IA</strong> - Aprovação inteligente antes de publicar
              </li>
            </ul>
          </section>

          <section className={styles.section}>
            <h2>Para quem é</h2>
            <div className={styles.audience}>
              <div>
                <h3>Assessoras e Cerimonialistas</h3>
                <p>Controle total e segurança para encantar sem riscos</p>
              </div>
              <div>
                <h3>Noivas, Debutantes e Famílias</h3>
                <p>Transforme fotos em experiência inesquecível</p>
              </div>
              <div>
                <h3>Produtores e Corporativos</h3>
                <p>Engajamento em escala com alto volume</p>
              </div>
            </div>
          </section>

          <section className={styles.section}>
            <h2>Perguntas frequentes</h2>
            <dl className={styles.faq}>
              <dt>Precisa instalar app?</dt>
              <dd>Não! Convidados entram por QR Code, WhatsApp, Telegram ou link web.</dd>
              
              <dt>Aceita vídeos?</dt>
              <dd>Sim! Fotos e vídeos são processados automaticamente.</dd>
              
              <dt>Como funciona a moderação?</dt>
              <dd>IA filtra conteúdo impróprio antes de publicar. Você escolhe o nível de moderação.</dd>
              
              <dt>Funciona com muito volume?</dt>
              <dd>Sim! Arquitetura preparada para centenas de convidados simultaneamente.</dd>
              
              <dt>Como funciona a busca facial?</dt>
              <dd>Convidado tira selfie e recebe suas fotos em segundos. Configurável por evento.</dd>
            </dl>
          </section>

          <section className={styles.cta}>
            <h2>Pronto para transformar seu evento?</h2>
            <div className={styles.ctaButtons}>
              <a 
                href="https://eventovivo.com/agendar" 
                className={styles.primaryButton}
              >
                Agendar demonstração
              </a>
              <a 
                href="https://wa.me/5511999999999?text=Olá!%20Quero%20conhecer%20a%20plataforma%20Evento%20Vivo." 
                className={styles.secondaryButton}
              >
                Falar no WhatsApp
              </a>
            </div>
          </section>

          <footer className={styles.footer}>
            <p>&copy; 2024 Evento Vivo. Todos os direitos reservados.</p>
            <nav>
              <a href="/privacidade">Privacidade</a>
              <a href="/termos">Termos de uso</a>
            </nav>
          </footer>
        </div>
      </div>
    </noscript>
  );
}
