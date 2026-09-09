<?php

declare(strict_types=1);

use Illuminate\Support\Env;

it('loads intake form recipients as an optional array', function (?string $value, array $expected): void {
    $environment = Env::getRepository();
    $original = $environment->get('MAIL_INTAKE_FORM_RECIPIENTS');

    if ($value === null) {
        $environment->clear('MAIL_INTAKE_FORM_RECIPIENTS');
    } else {
        $environment->set('MAIL_INTAKE_FORM_RECIPIENTS', $value);
    }

    try {
        $mailConfig = require config_path('mail.php');

        expect($mailConfig['intake_form_recipients'])->toBe($expected);
    } finally {
        if ($original === null) {
            $environment->clear('MAIL_INTAKE_FORM_RECIPIENTS');
        } else {
            $environment->set('MAIL_INTAKE_FORM_RECIPIENTS', $original);
        }
    }
})->with([
    'unset' => [null, []],
    'empty' => ['', []],
    'whitespace' => ['   ', []],
    'empty entries' => [' , , ', []],
    'one recipient' => ['intake@example.org', ['intake@example.org']],
    'multiple recipients' => ['intake@example.org,enrollment@example.net', ['intake@example.org', 'enrollment@example.net']],
    'trims and removes empty entries' => [' intake@example.org, , enrollment@example.net, ', ['intake@example.org', 'enrollment@example.net']],
]);
