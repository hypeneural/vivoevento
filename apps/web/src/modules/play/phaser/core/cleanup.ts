import type Phaser from 'phaser';

export function destroyGameInstance(game?: Phaser.Game | null) {
  if (!game) return;
  game.destroy(true);
}

export function removeTextureKeys(scene: Phaser.Scene, keys: string[]) {
  keys.forEach((key) => {
    if (scene.textures.exists(key)) {
      scene.textures.remove(key);
    }
  });
}
