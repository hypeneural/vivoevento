import { forwardRef, type ReactNode } from 'react';
import { motion } from 'framer-motion';

import type { WallRuntimeItem } from '../../types';
import type { WallBoardSlotState } from './types';

interface BoardSlotProps {
  slot: WallBoardSlotState;
  className?: string;
  children: (item: WallRuntimeItem | null, slot: WallBoardSlotState) => ReactNode;
}

export const BoardSlot = forwardRef<HTMLDivElement, BoardSlotProps>(function BoardSlot({
  slot,
  className,
  children,
}, ref) {
  return (
    <motion.div
      ref={ref}
      layout="position"
      className={className}
      data-board-slot-index={slot.index}
      data-board-slot-item-id={slot.item?.id ?? ''}
    >
      {children(slot.item, slot)}
    </motion.div>
  );
});

export default BoardSlot;
