import type { PuzzleGameSettings } from '@/modules/play/types';

import {
  isPieceCorrectForSlot,
  isPuzzleSolved,
  markPiecePlaced,
} from '../domain/PuzzleBoard';
import { PuzzleScore } from '../domain/PuzzleScore';
import type {
  PuzzleBoardState,
  PuzzleDropResolution,
  PuzzlePieceModel,
  PuzzleProgressSnapshot,
  PuzzleSlotModel,
} from '../types/puzzle.types';
import { PuzzleAudioSystem } from './PuzzleAudioSystem';
import { PuzzleFeedbackSystem } from './PuzzleFeedbackSystem';

export class PuzzlePlacementSystem {
  constructor(
    private readonly board: PuzzleBoardState,
    private readonly score: PuzzleScore,
    private readonly feedback: PuzzleFeedbackSystem,
    private readonly audio: PuzzleAudioSystem,
    private readonly settings: PuzzleGameSettings,
  ) {}

  resolveDrop(piece: PuzzlePieceModel, slot: PuzzleSlotModel | null, elapsedMs: number): {
    resolution: PuzzleDropResolution;
    progress: PuzzleProgressSnapshot;
    solved: boolean;
  } {
    const distance = slot ? Math.hypot(piece.sprite.x - slot.x, piece.sprite.y - slot.y) : null;
    const snapped = Boolean(
      slot
      && !slot.placedPieceId
      && isPieceCorrectForSlot(piece, slot)
      && (this.settings.snapEnabled ?? true)
      && distance !== null
      && distance <= (this.settings.dragTolerance ?? 18),
    );

    if (snapped && slot) {
      this.score.registerCorrect();
      markPiecePlaced(this.board, piece, slot);
      this.audio.playSnap();
      this.feedback.animateCorrectPlacement(piece, slot);
    } else {
      this.score.registerWrong();
      this.audio.playError();
      this.feedback.animateWrongPlacement(piece);
    }

    const progress = this.score.buildProgress(this.board.totalPieces, this.board.placedCount, elapsedMs);

    return {
      resolution: {
        piece,
        slot,
        snapped,
        distance,
      },
      progress,
      solved: isPuzzleSolved(this.board),
    };
  }
}
