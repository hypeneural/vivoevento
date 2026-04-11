import type { ReactNode } from 'react';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

interface QrCodeEditorDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  children: ReactNode;
  footer?: ReactNode;
}

export function QrCodeEditorDialog({
  open,
  onOpenChange,
  title,
  description,
  children,
  footer,
}: QrCodeEditorDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        data-testid="event-public-link-qr-editor-dialog"
        className="max-h-[92vh] max-w-6xl overflow-y-auto p-0"
      >
        <div className="grid gap-0">
          <DialogHeader className="border-b border-slate-200 px-6 py-5">
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
          </DialogHeader>

          <div className="px-6 py-5">
            {children}
          </div>

          {footer ? (
            <DialogFooter className="border-t border-slate-200 px-6 py-4">
              {footer}
            </DialogFooter>
          ) : null}
        </div>
      </DialogContent>
    </Dialog>
  );
}
