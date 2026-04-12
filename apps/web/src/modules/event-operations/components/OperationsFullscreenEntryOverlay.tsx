interface OperationsFullscreenEntryOverlayProps {
  onEnterRoom: () => void;
  fullscreenError: string | null;
}

export function OperationsFullscreenEntryOverlay({
  onEnterRoom,
  fullscreenError,
}: OperationsFullscreenEntryOverlayProps) {
  return (
    <section
      className="rounded-3xl border border-white/15 bg-slate-950/80 p-6 text-white shadow-2xl"
      aria-labelledby="control-room-entry-title"
    >
      <div className="space-y-2">
        <p className="text-xs font-semibold uppercase tracking-[0.32em] text-cyan-200">
          Modo sala
        </p>
        <h2 id="control-room-entry-title" className="text-2xl font-semibold">
          Preparar control room
        </h2>
        <p className="max-w-2xl text-sm text-slate-300">
          Entre em fullscreen quando estiver pronto para acompanhar a operacao do evento.
        </p>
      </div>

      <ul className="mt-5 grid gap-3 text-sm text-slate-200 sm:grid-cols-3">
        <li className="rounded-2xl bg-white/10 p-4">Leia a saude global primeiro.</li>
        <li className="rounded-2xl bg-white/10 p-4">Procure a estacao dominante quando houver gargalo.</li>
        <li className="rounded-2xl bg-white/10 p-4">Pressione Esc para sair do fullscreen.</li>
      </ul>

      {fullscreenError ? (
        <div className="mt-5 rounded-2xl border border-amber-300/40 bg-amber-300/10 p-4 text-sm text-amber-100">
          <p>{fullscreenError}</p>
          <p>A sala continua disponivel em modo janela.</p>
        </div>
      ) : null}

      <button
        type="button"
        onClick={onEnterRoom}
        className="mt-6 rounded-full bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-100 focus:ring-offset-2 focus:ring-offset-slate-950"
      >
        Entrar em modo sala
      </button>
    </section>
  );
}
