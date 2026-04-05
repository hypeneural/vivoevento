<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->string('instance_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('provider_config_json')->nullable();
            $table->jsonb('provider_meta_json')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status', 40)->nullable();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['organization_id', 'is_default'], 'whatsapp_instances_org_default_idx');
            $table->index(['organization_id', 'provider_key'], 'whatsapp_instances_org_provider_idx');
            $table->index(['organization_id', 'instance_name'], 'whatsapp_instances_org_name_idx');
        });

        DB::table('whatsapp_instances')
            ->select(['id', 'external_instance_id', 'uuid'])
            ->whereNull('instance_name')
            ->orderBy('id')
            ->lazy()
            ->each(static function (object $instance): void {
                $instanceName = filled($instance->external_instance_id)
                    ? $instance->external_instance_id
                    : (string) $instance->uuid;

                DB::table('whatsapp_instances')
                    ->where('id', $instance->id)
                    ->update(['instance_name' => $instanceName]);
            });

        $defaultFlag = $driver === 'sqlite' ? '1' : 'true';

        DB::statement("
            CREATE UNIQUE INDEX whatsapp_instances_default_per_org_unique
            ON whatsapp_instances (organization_id)
            WHERE is_default = {$defaultFlag} AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS whatsapp_instances_default_per_org_unique');

        Schema::table('whatsapp_instances', function (Blueprint $table) {
            $table->dropIndex('whatsapp_instances_org_default_idx');
            $table->dropIndex('whatsapp_instances_org_provider_idx');
            $table->dropIndex('whatsapp_instances_org_name_idx');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'instance_name',
                'is_active',
                'is_default',
                'provider_config_json',
                'provider_meta_json',
                'last_health_check_at',
                'last_health_status',
                'last_error',
                'notes',
            ]);
        });
    }
};
