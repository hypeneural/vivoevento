import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

type PublicCheckoutShellProps = {
  hero: React.ReactNode;
  main: React.ReactNode;
  sidebar: React.ReactNode;
  mobileFooter?: React.ReactNode;
};

export function PublicCheckoutShell({
  hero,
  main,
  sidebar,
  mobileFooter,
}: PublicCheckoutShellProps) {
  const isMobile = useIsMobile();

  return (
    <div className="min-h-screen bg-[linear-gradient(180deg,#f8fafc_0%,#eff6ff_100%)]">
      <div className={cn(
        'mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-10 sm:px-6 lg:px-8',
        mobileFooter ? 'pb-32 lg:pb-10' : undefined,
      )}>
        {hero}
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
          <div className="min-w-0">{main}</div>
          {!isMobile ? <div className="min-w-0">{sidebar}</div> : null}
        </div>
      </div>
      {isMobile ? mobileFooter : null}
    </div>
  );
}
