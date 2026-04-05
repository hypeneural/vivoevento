<?php

namespace App\Modules\WhatsApp\Queries;

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Shared\Concerns\HasPortableLike;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsAppInstancesQuery implements QueryInterface
{
    use HasPortableLike;

    public function __construct(
        protected int $organizationId,
        protected ?string $search = null,
        protected ?string $providerKey = null,
        protected ?string $status = null,
        protected ?bool $isDefault = null,
        protected ?bool $isActive = null,
    ) {}

    public function query(): Builder
    {
        $query = WhatsAppInstance::query()
            ->with('provider')
            ->forOrganization($this->organizationId);

        if ($this->search) {
            $search = $this->search;
            $like = $this->likeOperator();

            $query->where(function (Builder $builder) use ($search, $like) {
                $builder
                    ->where('name', $like, "%{$search}%")
                    ->orWhere('instance_name', $like, "%{$search}%")
                    ->orWhere('phone_number', $like, "%{$search}%")
                    ->orWhere('external_instance_id', $like, "%{$search}%");
            });
        }

        if ($this->providerKey) {
            $query->where('provider_key', $this->providerKey);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->isDefault !== null) {
            $query->where('is_default', $this->isDefault);
        }

        if ($this->isActive !== null) {
            $query->where('is_active', $this->isActive);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderByRaw("CASE WHEN status = 'connected' THEN 0 ELSE 1 END")
            ->latest('updated_at');
    }
}
