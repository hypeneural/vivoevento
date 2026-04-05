<?php

namespace App\Modules\WhatsApp\Actions;

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetDefaultWhatsAppInstanceAction
{
    public function execute(WhatsAppInstance $instance): WhatsAppInstance
    {
        if (! $instance->is_active) {
            throw ValidationException::withMessages([
                'instance' => ['Ative a instancia antes de defini-la como padrao.'],
            ]);
        }

        if ($instance->last_health_check_at === null || ! in_array($instance->last_health_status, ['connected', 'disconnected'], true)) {
            throw ValidationException::withMessages([
                'instance' => ['Teste a conexao da instancia antes de defini-la como padrao.'],
            ]);
        }

        DB::transaction(function () use ($instance) {
            WhatsAppInstance::query()
                ->where('organization_id', $instance->organization_id)
                ->update(['is_default' => false]);

            $instance->update(['is_default' => true]);
        });

        return $instance->fresh(['provider']);
    }
}
