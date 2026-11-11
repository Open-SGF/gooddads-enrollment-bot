<?php

declare(strict_types=1);

use App\Transformers\NeonDTOTransformer;
use Illuminate\Support\Facades\Date;

it('keeps child ages stable until their birthday', function (): void {
    $participant = array_fill_keys(
        ['contactInfo', 'children', 'disclosure', 'assessment', 'survey', 'servicePlan'],
        ['records' => []],
    );
    $participant['children']['records'] = [
        ['dateOfBirth' => ['value' => '2018-06-15']],
        ['dateOfBirth' => ['value' => '2026-01-01']],
        [],
    ];
    $expected = ['child_age_1' => '7', 'child_age_2' => 'Under 1', 'child_age_3' => ''];

    $this->travelTo(Date::parse('2026-06-14 09:00:00'));
    expect(NeonDTOTransformer::transformParticipantData($participant)->toPdfArray())->toMatchArray($expected);

    $this->travel(5)->hours();
    expect(NeonDTOTransformer::transformParticipantData($participant)->toPdfArray())->toMatchArray($expected);

    $this->travelTo(Date::parse('2026-06-15 00:00:00'));
    expect(NeonDTOTransformer::transformParticipantData($participant)->toPdfArray()['child_age_1'])->toBe('8');
});
