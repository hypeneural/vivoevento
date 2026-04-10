import type { ReactNode } from 'react';

import type { WallRuntimeItem } from '../../types';
import type { WallBoardSlotState } from './types';

interface BoardSlotProps {
  slot: WallBoardSlotState;
  className?: string;
  children: (item: WallRuntimeItem | null, slot: WallBoardSlotState) => ReactNode;
}

export function BoardSlot({ slot, className, children }: BoardSlotProps) {
  return (
    <div
      className={className}
      data-board-slot-index={slot.index}
      data-board-slot-item-id={slot.item?.id ?? ''}
    >
      {children(slot.item, slot)}
    </div>
  );
}

export default BoardSlot;
