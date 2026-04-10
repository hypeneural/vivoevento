<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'event_media_moderation_search_document_trgm_idx';

    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            if (! Schema::hasColumn('event_media', 'moderation_search_document')) {
                $table->text('moderation_search_document')->nullable();
            }
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            $this->backfillPostgresSearchDocument();
            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON event_media USING GIN (moderation_search_document gin_trgm_ops) WHERE moderation_search_document IS NOT NULL',
                self::INDEX_NAME,
            ));

            return;
        }

        $this->backfillPortableSearchDocument();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::INDEX_NAME));
        }

        Schema::table('event_media', function (Blueprint $table) {
            if (Schema::hasColumn('event_media', 'moderation_search_document')) {
                $table->dropColumn('moderation_search_document');
            }
        });
    }

    private function backfillPostgresSearchDocument(): void
    {
        DB::statement(<<<'SQL'
            UPDATE event_media
            SET moderation_search_document = nullif(trim(concat_ws(' ',
                event_media.caption,
                event_media.title,
                event_media.source_label,
                event_media.original_filename,
                event_media.client_filename,
                events.title,
                (SELECT inbound_messages.sender_name FROM inbound_messages WHERE inbound_messages.id = event_media.inbound_message_id),
                (SELECT inbound_messages.sender_phone FROM inbound_messages WHERE inbound_messages.id = event_media.inbound_message_id),
                (SELECT inbound_messages.sender_lid FROM inbound_messages WHERE inbound_messages.id = event_media.inbound_message_id),
                (SELECT inbound_messages.sender_external_id FROM inbound_messages WHERE inbound_messages.id = event_media.inbound_message_id)
            )), '')
            FROM events
            WHERE events.id = event_media.event_id
        SQL);
    }

    private function backfillPortableSearchDocument(): void
    {
        DB::table('event_media')
            ->select('event_media.id')
            ->orderBy('event_media.id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $media = DB::table('event_media')
                        ->leftJoin('events', 'events.id', '=', 'event_media.event_id')
                        ->leftJoin('inbound_messages', 'inbound_messages.id', '=', 'event_media.inbound_message_id')
                        ->where('event_media.id', $row->id)
                        ->first([
                            'event_media.caption',
                            'event_media.title',
                            'event_media.source_label',
                            'event_media.original_filename',
                            'event_media.client_filename',
                            'events.title as event_title',
                            'inbound_messages.sender_name',
                            'inbound_messages.sender_phone',
                            'inbound_messages.sender_lid',
                            'inbound_messages.sender_external_id',
                        ]);

                    if (! $media) {
                        continue;
                    }

                    $document = collect([
                        $media->caption,
                        $media->title,
                        $media->source_label,
                        $media->original_filename,
                        $media->client_filename,
                        $media->event_title,
                        $media->sender_name,
                        $media->sender_phone,
                        $media->sender_lid,
                        $media->sender_external_id,
                    ])
                        ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
                        ->map(fn ($value) => trim((string) $value))
                        ->implode(' ');

                    DB::table('event_media')
                        ->where('id', $row->id)
                        ->update(['moderation_search_document' => $document === '' ? null : $document]);
                }
            }, 'event_media.id', 'id');
    }
};
