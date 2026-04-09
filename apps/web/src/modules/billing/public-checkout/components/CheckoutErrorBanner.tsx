import { AlertCircle, LogIn } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type CheckoutErrorBannerProps = {
  title: string;
  description: string;
  actionLabel?: string | null;
  actionHref?: string | null;
};

export function CheckoutErrorBanner({
  title,
  description,
  actionLabel,
  actionHref,
}: CheckoutErrorBannerProps) {
  return (
    <Alert variant="destructive" className="border-rose-200 bg-rose-50 text-rose-950 [&>svg]:text-rose-600">
      <AlertCircle className="h-4 w-4" />
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription className="space-y-3">
        <p>{description}</p>
        {actionHref && actionLabel ? (
          <a
            href={actionHref}
            className="inline-flex items-center gap-2 text-sm font-medium underline underline-offset-4"
          >
            <LogIn className="h-4 w-4" />
            {actionLabel}
          </a>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}
