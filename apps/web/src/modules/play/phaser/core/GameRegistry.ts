import type { EventovivoPlayableGame } from './runtimeTypes';

export class GameRegistry {
  private static games = new Map<string, EventovivoPlayableGame>();
  private static loaders = new Map<string, () => Promise<EventovivoPlayableGame>>();
  private static pending = new Map<string, Promise<EventovivoPlayableGame>>();

  static register(game: EventovivoPlayableGame) {
    this.games.set(game.key, game);
  }

  static registerLoader(key: string, loader: () => Promise<EventovivoPlayableGame>) {
    if (!this.loaders.has(key)) {
      this.loaders.set(key, loader);
    }
  }

  static get(key: string) {
    return this.games.get(key);
  }

  static has(key: string) {
    return this.games.has(key) || this.loaders.has(key);
  }

  static async load(key: string) {
    const existing = this.games.get(key);
    if (existing) {
      return existing;
    }

    const inFlight = this.pending.get(key);
    if (inFlight) {
      return inFlight;
    }

    const loader = this.loaders.get(key);
    if (!loader) {
      throw new Error(`Nenhum runtime registrado para o jogo "${key}".`);
    }

    const promise = loader()
      .then((game) => {
        this.games.set(key, game);
        this.pending.delete(key);

        return game;
      })
      .catch((error) => {
        this.pending.delete(key);
        throw error;
      });

    this.pending.set(key, promise);

    return promise;
  }

  static preload(key: string) {
    if (!this.has(key)) {
      return Promise.resolve(null);
    }

    return this.load(key).catch(() => null);
  }
}
