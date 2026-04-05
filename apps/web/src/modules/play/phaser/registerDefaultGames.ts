import { GameRegistry } from './core/GameRegistry';

let registered = false;

export function registerDefaultGames() {
  if (registered) return;

  GameRegistry.registerLoader('memory', async () => {
    const { MemoryGame } = await import('./memory/MemoryGame');
    return MemoryGame;
  });

  GameRegistry.registerLoader('puzzle', async () => {
    const { PuzzleGame } = await import('./puzzle/PuzzleGame');
    return PuzzleGame;
  });

  registered = true;
}

export function preloadPlayableGame(gameKey: string | null | undefined) {
  if (!gameKey) {
    return Promise.resolve(null);
  }

  registerDefaultGames();

  return GameRegistry.preload(gameKey);
}
