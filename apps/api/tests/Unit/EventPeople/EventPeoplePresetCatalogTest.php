<?php

use App\Modules\EventPeople\Services\EventPeoplePresetCatalog;

it('returns event-specific role semantics instead of falling back social events to one generic package', function () {
    $catalog = app(EventPeoplePresetCatalog::class);

    $fifteen = $catalog->forEventType('fifteen');
    $birthday = $catalog->forEventType('birthday');
    $graduation = $catalog->forEventType('graduation');

    expect($fifteen['model_key'])->toBe('fifteen')
        ->and(collect($fifteen['people'])->pluck('role_key'))->toContain('debutante')
        ->and(collect($fifteen['people'])->firstWhere('role_key', 'mae_da_debutante')['role_family'])->toBe('familia')
        ->and(collect($fifteen['groups'])->pluck('key'))->toContain('debutante_family')
        ->and(collect($fifteen['coverage_targets'])->pluck('key'))->toContain('debutante_solo');

    expect($birthday['model_key'])->toBe('birthday')
        ->and(collect($birthday['people'])->pluck('role_key'))->toContain('aniversariante')
        ->and(collect($birthday['coverage_targets'])->pluck('key'))->toContain('birthday_person');

    expect($graduation['model_key'])->toBe('graduation')
        ->and(collect($graduation['people'])->pluck('role_key'))->toContain('formando')
        ->and(collect($graduation['people'])->firstWhere('role_key', 'patrono')['role_family'])->toBe('academico')
        ->and(collect($graduation['groups'])->pluck('key'))->toContain('academic');
});

it('returns corporate and fair presets with business roles, group seeds and coverage targets', function () {
    $catalog = app(EventPeoplePresetCatalog::class);

    $corporate = $catalog->forEventType('corporate');
    $fair = $catalog->forEventType('fair');

    expect($corporate['model_key'])->toBe('corporate')
        ->and(collect($corporate['people'])->pluck('role_key'))->toContain('proprietario')
        ->and(collect($corporate['people'])->pluck('role_key'))->toContain('socio')
        ->and(collect($corporate['groups'])->pluck('key'))->toContain('leadership')
        ->and(collect($corporate['coverage_targets'])->pluck('key'))->toContain('leadership');

    expect($fair['model_key'])->toBe('fair')
        ->and(collect($fair['people'])->pluck('role_key'))->toContain('responsavel_stand')
        ->and(collect($fair['groups'])->pluck('key'))->toContain('stand_team')
        ->and(collect($fair['coverage_targets'])->pluck('key'))->toContain('stand_team');
});

