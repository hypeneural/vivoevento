<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS event_media_moderation_feed_event_sort_moderation_created_idx
ON event_media (event_id, sort_order DESC, moderation_status, created_at DESC, id DESC)
WHERE deleted_at IS NULL
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS event_media_moderation_feed_event_publication_processing_create
ON event_media (event_id, publication_status, processing_status, created_at DESC, id DESC)
WHERE deleted_at IS NULL
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS event_media_moderation_search_trgm_idx
ON event_media
USING GIN (caption gin_trgm_ops, title gin_trgm_ops, source_label gin_trgm_ops, original_filename gin_trgm_ops)
WHERE deleted_at IS NULL
SQL);

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS inbound_messages_moderation_search_trgm_idx
ON inbound_messages
USING GIN (sender_name gin_trgm_ops, sender_phone gin_trgm_ops, sender_lid gin_trgm_ops, sender_external_id gin_trgm_ops)
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS inbound_messages_moderation_search_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS event_media_moderation_search_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS event_media_moderation_feed_event_publication_processing_create');
        DB::statement('DROP INDEX IF EXISTS event_media_moderation_feed_event_sort_moderation_created_idx');
    }
};
