<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AuditActivityResource extends JsonResource
{
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'password_confirmation',
        'remember_token',
        'token',
        'session_token',
        'plain_text_token',
        'code',
        'debug_code',
        'code_hash',
    ];

    public function __construct(
        Activity $resource,
        private readonly Collection $events,
        private readonly Collection $organizations,
        private readonly Collection $organizationMembers,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'description' => $activity->description,
            'activity_event' => $activity->event,
            'category' => $this->resolveCategory($activity),
            'severity' => $this->resolveSeverity($activity),
            'batch_uuid' => $activity->batch_uuid,
            'actor' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
                'email' => $this->maskEmail($activity->causer->email),
            ] : null,
            'subject' => $this->resolveSubject($activity),
            'organization' => $this->resolveOrganization($activity),
            'related_event' => $this->resolveRelatedEvent($activity),
            'changes' => $this->resolveChanges($activity),
            'metadata' => $this->resolveMetadata($activity),
            'created_at' => $activity->created_at?->toISOString(),
        ];
    }

    private function resolveSubject(Activity $activity): array
    {
        $type = $this->subjectTypeKey($activity->subject_type);

        return [
            'type' => $type,
            'type_label' => $this->subjectTypeLabel($type),
            'id' => $activity->subject_id,
            'label' => $this->resolveSubjectLabel($activity),
            'route' => $type === 'event' && $activity->subject_id
                ? "/events/{$activity->subject_id}"
                : null,
        ];
    }

    private function resolveSubjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;

        if ($subject instanceof Event) {
            return $subject->title;
        }

        if ($subject instanceof Organization) {
            return $subject->trade_name ?: $subject->legal_name ?: $subject->name;
        }

        if ($subject instanceof Client) {
            return $subject->name;
        }

        if ($subject instanceof EventMedia) {
            return $subject->title
                ?: $subject->caption
                ?: $subject->original_filename
                ?: "Midia #{$subject->id}";
        }

        if ($subject instanceof User) {
            return $subject->name;
        }

        if ($subject instanceof Subscription) {
            return $subject->plan?->name
                ? "Assinatura {$subject->plan->name}"
                : "Assinatura #{$subject->id}";
        }

        $candidate = $activity->properties['attributes']['title']
            ?? $activity->properties['attributes']['caption']
            ?? $activity->properties['attributes']['original_filename']
            ?? $activity->properties['attributes']['name']
            ?? $activity->properties['old']['title']
            ?? $activity->properties['old']['caption']
            ?? $activity->properties['old']['original_filename']
            ?? $activity->properties['old']['name']
            ?? $activity->properties['caption']
            ?? $activity->properties['original_filename']
            ?? null;

        if (is_string($candidate) && trim($candidate) !== '') {
            return $candidate;
        }

        $fallbackType = $this->subjectTypeLabel($this->subjectTypeKey($activity->subject_type));

        return $activity->subject_id
            ? "{$fallbackType} #{$activity->subject_id}"
            : $fallbackType;
    }

    private function resolveOrganization(Activity $activity): ?array
    {
        $organizationId = null;

        if ($activity->subject instanceof Organization) {
            $organizationId = $activity->subject_id;
        } elseif ($activity->subject instanceof Event || $activity->subject instanceof Client || $activity->subject instanceof Subscription) {
            $organizationId = $activity->subject->organization_id ?? null;
        } elseif ($activity->subject instanceof User && $activity->subject_id) {
            $organizationId = $this->organizationMembers->get($activity->subject_id);
        } elseif ($activity->causer_id !== null) {
            $organizationId = $this->organizationMembers->get($activity->causer_id);
        }

        $organizationId ??= $activity->properties['organization_id'] ?? null;

        if ($organizationId === null) {
            $relatedEventId = $this->resolveRelatedEventId($activity);
            $organizationId = $relatedEventId ? $this->events->get($relatedEventId)?->organization_id : null;
        }

        if ($organizationId === null) {
            return null;
        }

        $organization = $this->organizations->get((int) $organizationId);

        if ($organization === null) {
            return [
                'id' => (int) $organizationId,
                'name' => "Organizacao #{$organizationId}",
                'slug' => null,
            ];
        }

        return [
            'id' => $organization->id,
            'name' => $organization->trade_name ?: $organization->legal_name ?: $organization->name,
            'slug' => $organization->slug,
        ];
    }

    private function resolveRelatedEvent(Activity $activity): ?array
    {
        $eventId = $this->resolveRelatedEventId($activity);

        if ($eventId === null) {
            return null;
        }

        $event = $this->events->get($eventId);

        if ($event === null) {
            return [
                'id' => $eventId,
                'title' => "Evento #{$eventId}",
                'slug' => null,
                'route' => "/events/{$eventId}",
            ];
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'route' => "/events/{$event->id}",
        ];
    }

    private function resolveRelatedEventId(Activity $activity): ?int
    {
        if ($activity->subject instanceof Event && $activity->subject_id !== null) {
            return (int) $activity->subject_id;
        }

        $eventId = $activity->properties['event_id'] ?? null;

        return is_numeric($eventId) ? (int) $eventId : null;
    }

    private function resolveChanges(Activity $activity): array
    {
        $old = $this->sanitizeValue($activity->properties['old'] ?? null);
        $new = $this->sanitizeValue($activity->properties['attributes'] ?? null);

        $changedFields = collect(array_keys($old ?? []))
            ->merge(array_keys($new ?? []))
            ->unique()
            ->values()
            ->all();

        return [
            'count' => count($changedFields),
            'fields' => $changedFields,
            'old' => $old,
            'new' => $new,
        ];
    }

    private function resolveMetadata(Activity $activity): array
    {
        $properties = collect($activity->properties?->toArray() ?? [])
            ->except(['old', 'attributes'])
            ->all();

        return $this->sanitizeValue($properties) ?? [];
    }

    private function resolveCategory(Activity $activity): string
    {
        $description = mb_strtolower($activity->description);
        $subjectType = $this->subjectTypeKey($activity->subject_type);

        if (str_contains($description, 'senha') || str_contains($description, 'otp') || str_contains($description, 'avatar')) {
            return 'security';
        }

        if ($subjectType === 'subscription' || str_contains($description, 'plano')) {
            return 'billing';
        }

        return match ($subjectType) {
            'event' => 'event',
            'media' => 'event',
            'organization' => 'organization',
            'client' => 'customer',
            'user' => 'account',
            default => 'system',
        };
    }

    private function resolveSeverity(Activity $activity): string
    {
        $description = mb_strtolower($activity->description);
        $changesCount = count(array_merge(
            array_keys($activity->properties['old'] ?? []),
            array_keys($activity->properties['attributes'] ?? []),
        ));

        if (
            str_contains($description, 'senha')
            || str_contains($description, 'otp')
            || str_contains($description, 'plano')
            || str_contains($description, 'recuperacao')
            || str_contains($description, 'convite')
        ) {
            return 'high';
        }

        if ($changesCount >= 5 || str_contains($description, 'atualizado')) {
            return 'medium';
        }

        return 'low';
    }

    private function subjectTypeKey(?string $subjectType): string
    {
        return match ($subjectType) {
            Event::class => 'event',
            EventMedia::class => 'media',
            Organization::class => 'organization',
            Client::class => 'client',
            User::class => 'user',
            Subscription::class => 'subscription',
            default => 'system',
        };
    }

    private function subjectTypeLabel(string $type): string
    {
        return match ($type) {
            'event' => 'Evento',
            'media' => 'Midia',
            'organization' => 'Organizacao',
            'client' => 'Cliente',
            'user' => 'Usuario',
            'subscription' => 'Assinatura',
            default => 'Sistema',
        };
    }

    private function sanitizeValue(mixed $value, array $path = []): mixed
    {
        if (! is_array($value)) {
            return $this->sanitizeScalar($value, $path);
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $currentPath = [...$path, (string) $key];
            $normalizedKey = mb_strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($item, $currentPath);
        }

        return $sanitized;
    }

    private function sanitizeScalar(mixed $value, array $path): mixed
    {
        $field = mb_strtolower((string) end($path));

        if (in_array($field, self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }

        if (! is_string($value)) {
            return $value;
        }

        if (str_contains($field, 'email')) {
            return $this->maskEmail($value);
        }

        if (str_contains($field, 'phone')) {
            return $this->maskPhone($value);
        }

        return $value;
    }

    private function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$localPart, $domain] = explode('@', $email, 2);

        $visibleStart = substr($localPart, 0, 1) ?: '*';
        $visibleEnd = strlen($localPart) > 2 ? substr($localPart, -1) : '';
        $hidden = str_repeat('*', max(strlen($localPart) - strlen($visibleStart . $visibleEnd), 2));

        return "{$visibleStart}{$hidden}{$visibleEnd}@{$domain}";
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', max(strlen($digits) - 4, 2)) . substr($digits, -4);
    }
}
