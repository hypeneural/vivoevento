import { LockKeyhole, RefreshCcw } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type ResumeNoticeBannerProps = {
  title: string;
  description: string;
  mode: 'auto' | 'manual';
  loading?: boolean;
};

export function ResumeNoticeBanner({
  title,
  description,
  mode,
  loading = false,
}: ResumeNoticeBannerProps) {
  const Icon = mode === 'auto' ? RefreshCcw : LockKeyhole;

  return (
    <Alert className="border-sky-200 bg-sky-50 text-sky-950 [&>svg]:text-sky-600">
      <Icon className={loading && mode === 'auto' ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>{description}</AlertDescription>
    </Alert>
  );
}
