type AuthFlowFooterProps = {
  prefix: string;
  actionLabel: string;
  onAction: () => void;
  actionDisabled?: boolean;
};

export function AuthFlowFooter({
  prefix,
  actionLabel,
  onAction,
  actionDisabled = false,
}: AuthFlowFooterProps) {
  return (
    <p className="pt-1 text-center text-xs text-muted-foreground/70">
      {prefix}{' '}
      <button
        type="button"
        onClick={onAction}
        disabled={actionDisabled}
        className="font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-60"
      >
        {actionLabel}
      </button>
    </p>
  );
}
