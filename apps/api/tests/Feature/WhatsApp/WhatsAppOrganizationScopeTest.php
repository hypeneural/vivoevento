<?php

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

it('forbids sending messages with an instance from another organization', function () {
    [$user, $organization] = $this->actingAsOwner();
    $otherOrganization = $this->createOrganization();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    Queue::fake();

    $response = $this->apiPost('/whatsapp/messages/text', [
        'instance_id' => $instance->id,
        'phone' => '5511999999999',
        'message' => 'Teste',
    ]);

    $response->assertStatus(403);
});
