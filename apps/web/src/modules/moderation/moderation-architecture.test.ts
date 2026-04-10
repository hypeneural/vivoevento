import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

describe('moderation architecture', () => {
  it('does not keep the legacy numbered pagination component in the active moderation module', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));

    expect(existsSync(resolve(currentDirectory, 'components/ModerationPagination.tsx'))).toBe(false);
  });

  it('prefetches the next moderation detail from the focused item', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const source = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');

    expect(source).toContain('resolveNextModerationDetailPrefetchItem')
      .toContain('queryClient.prefetchQuery')
      .toContain('queryKeys.media.detail(String(nextDetailPrefetchItem.id))');
  });

  it('wires quick reject reasons and approve-and-next into the moderation page flow', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const source = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');

    expect(source)
      .toContain('MODERATION_REJECT_REASON_PRESETS')
      .toContain('submitReject')
      .toContain('runActionForCurrentTarget(\'approve\', { advanceAfterSuccess: event.shiftKey })')
      .toContain('onApproveAndNext={handleFocusedApproveAndNext}');
  });

  it('wires duplicate cluster queries and undo toast actions into the moderation page flow', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const source = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');

    expect(source)
      .toContain('queryKeys.media.duplicateCluster(String(focusedMediaId ?? \'\'))')
      .toContain('moderationService.listDuplicateCluster')
      .toContain('ToastAction')
      .toContain('moderationService.undoDecision');
  });

  it('wires operational quick filters for media type, ai review, duplicates and error into the moderation page flow', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const pageSource = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');
    const typesSource = readFileSync(resolve(currentDirectory, 'types.ts'), 'utf8');

    expect(typesSource)
      .toContain("'error'")
      .toContain("'images'")
      .toContain("'videos'")
      .toContain("'ai_review'")
      .toContain("'duplicates'")
      .toContain('media_type?: ModerationMediaTypeFilter')
      .toContain('duplicates?: boolean')
      .toContain('ai_review?: boolean');

    expect(pageSource)
      .toContain('mediaTypeFilter')
      .toContain('duplicatesOnly')
      .toContain('aiReviewOnly')
      .toContain("setStatusFilter('error')")
      .toContain("setMediaTypeFilter('image')")
      .toContain("setMediaTypeFilter('video')")
      .toContain('setDuplicatesOnly(true)')
      .toContain('setAiReviewOnly(true)');
  });

  it('wires queue progress into the moderation page and review panel', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const pageSource = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');
    const feedUtilsSource = readFileSync(resolve(currentDirectory, 'feed-utils.ts'), 'utf8');

    expect(feedUtilsSource)
      .toContain('resolveModerationQueueProgress')
      .toContain('pendingRemainingAfterCurrent');

    expect(pageSource)
      .toContain('queueProgress')
      .toContain('resolveModerationQueueProgress(media, focusedMediaId')
      .toContain('Pendentes restantes')
      .toContain('Posicao atual');
  });

  it('keeps "Nao moderadas" as the default operational recorte and restores it when clearing filters', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const source = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');

    expect(source)
      .toContain("const DEFAULT_STATUS_FILTER: ModerationStatusFilter = 'pending_moderation'")
      .toContain("useState<string>(DEFAULT_STATUS_FILTER)")
      .toContain("setStatusFilter(DEFAULT_STATUS_FILTER)");
  });

  it('wires moderation route telemetry for feed, detail, pagination and incoming queue churn', () => {
    const currentDirectory = dirname(fileURLToPath(import.meta.url));
    const source = readFileSync(resolve(currentDirectory, 'ModerationPage.tsx'), 'utf8');

    expect(source)
      .toContain('moderationService.trackTelemetry')
      .toContain("event: 'feed_first_page_loaded'")
      .toContain("event: 'filters_stabilized'")
      .toContain("event: 'feed_next_page_loaded'")
      .toContain("event: 'detail_loaded'")
      .toContain("event: 'incoming_queue_changed'");
  });
});
