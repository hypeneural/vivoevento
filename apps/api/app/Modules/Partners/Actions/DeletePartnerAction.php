<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;

class DeletePartnerAction
{
    public function execute(Organization $partner, User $actor): bool
    {
        if ($this->hasOperationalHistory($partner)) {
            return false;
        }

        $partner->delete();

        activity()
            ->event('partner.deleted')
            ->performedOn($partner)
            ->causedBy($actor)
            ->withProperties([
                'partner_id' => $partner->id,
                'organization_id' => $partner->id,
            ])
            ->log('Parceiro removido');

        return true;
    }

    private function hasOperationalHistory(Organization $partner): bool
    {
        return $partner->clients()->exists()
            || $partner->events()->exists()
            || $partner->subscriptions()->exists()
            || $partner->billingOrders()->exists()
            || $partner->invoices()->exists()
            || $partner->eventAccessGrants()->exists();
    }
}
