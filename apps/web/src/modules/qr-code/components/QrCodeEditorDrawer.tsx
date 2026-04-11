import type { ReactNode } from 'react';

import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerFooter,
  DrawerHeader,
  DrawerTitle,
} from '@/components/ui/drawer';

interface QrCodeEditorDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  children: ReactNode;
  footer?: ReactNode;
}

export function QrCodeEditorDrawer({
  open,
  onOpenChange,
  title,
  description,
  children,
  footer,
}: QrCodeEditorDrawerProps) {
  return (
    <Drawer open={open} onOpenChange={onOpenChange} shouldScaleBackground={false}>
      <DrawerContent
        data-testid="event-public-link-qr-editor-drawer"
        className="max-h-[94vh] min-h-[88vh] overflow-y-auto"
      >
        <DrawerHeader className="border-b border-slate-200 pb-4 text-left">
          <DrawerTitle>{title}</DrawerTitle>
          <DrawerDescription>{description}</DrawerDescription>
        </DrawerHeader>

        <div className="px-4 py-5">
          {children}
        </div>

        {footer ? (
          <DrawerFooter className="border-t border-slate-200 bg-background/95">
            {footer}
          </DrawerFooter>
        ) : null}
      </DrawerContent>
    </Drawer>
  );
}
