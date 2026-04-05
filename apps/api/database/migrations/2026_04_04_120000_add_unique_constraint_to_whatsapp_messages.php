<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('whatsapp_messages')
            ->select('instance_id', 'direction', 'provider_message_id')
            ->whereNotNull('provider_message_id')
            ->groupBy('instance_id', 'direction', 'provider_message_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $duplicateIds = DB::table('whatsapp_messages')
                ->where('instance_id', $group->instance_id)
                ->where('direction', $group->direction)
                ->where('provider_message_id', $group->provider_message_id)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1)
                ->all();

            if ($duplicateIds !== []) {
                DB::table('whatsapp_messages')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unique(
                ['instance_id', 'direction', 'provider_message_id'],
                'wa_messages_instance_direction_provider_message_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropUnique('wa_messages_instance_direction_provider_message_unique');
        });
    }
};
