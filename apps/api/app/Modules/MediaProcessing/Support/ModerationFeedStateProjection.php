<?php

namespace App\Modules\MediaProcessing\Support;

class ModerationFeedStateProjection
{
    public function effectiveStateExpression(): string
    {
        $safetyBlocking = $this->safetyBlockingExpression();
        $contextBlocking = $this->contextBlockingExpression();
        $operatorRejected = $this->operatorRejectedExpression();

        return <<<SQL
case
    when {$operatorRejected} then 'rejected'
    when {$safetyBlocking} and event_media.safety_status = 'block' then 'rejected'
    when {$contextBlocking} and event_media.vlm_status = 'rejected' then 'rejected'
    when (
        {$safetyBlocking}
        and (
            event_media.safety_status is null
            or event_media.safety_status in ('queued', 'review', 'failed')
        )
    ) or (
        {$contextBlocking}
        and (
            event_media.vlm_status is null
            or event_media.vlm_status in ('queued', 'review', 'failed')
        )
    ) or event_media.moderation_status = 'pending' then 'pending_moderation'
    when event_media.moderation_status = 'rejected' then 'rejected'
    when event_media.publication_status = 'hidden' then 'hidden'
    when event_media.publication_status = 'published' and event_media.moderation_status = 'approved' then 'published'
    when event_media.moderation_status = 'approved' then 'approved'
    when event_media.processing_status = 'failed' then 'error'
    when event_media.processing_status in ('downloaded', 'processed') then 'processing'
    else 'received'
end
SQL;
    }

    public function pendingPriorityExpression(): string
    {
        $safetyBlocking = $this->safetyBlockingExpression();
        $contextBlocking = $this->contextBlockingExpression();
        $operatorRejected = $this->operatorRejectedExpression();

        return <<<SQL
case
    when not {$operatorRejected}
        and not ({$safetyBlocking} and event_media.safety_status = 'block')
        and not ({$contextBlocking} and event_media.vlm_status = 'rejected')
        and (
            (
                {$safetyBlocking}
                and (
                    event_media.safety_status is null
                    or event_media.safety_status in ('queued', 'review', 'failed')
                )
            ) or (
                {$contextBlocking}
                and (
                    event_media.vlm_status is null
                    or event_media.vlm_status in ('queued', 'review', 'failed')
                )
            ) or event_media.moderation_status = 'pending'
        ) then 1
    else 0
end
SQL;
    }

    private function safetyBlockingExpression(): string
    {
        return <<<SQL
(
    event_media.media_type = 'image'
    and events.moderation_mode = 'ai'
    and coalesce(event_content_moderation_settings.enabled, false) = true
    and coalesce(event_content_moderation_settings.mode, 'enforced') <> 'observe_only'
)
SQL;
    }

    private function contextBlockingExpression(): string
    {
        return <<<SQL
(
    event_media.media_type = 'image'
    and events.moderation_mode = 'ai'
    and coalesce(event_media_intelligence_settings.enabled, false) = true
    and coalesce(event_media_intelligence_settings.mode, 'enrich_only') = 'gate'
)
SQL;
    }

    private function operatorRejectedExpression(): string
    {
        return <<<SQL
(
    event_media.decision_source = 'user_override'
    and event_media.moderation_status = 'rejected'
)
SQL;
    }
}
