<?php

declare(strict_types=1);

namespace App\Support\IdeHelper;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Illuminate\Database\Eloquent\Model;
use ReflectionProperty;

final class ForceNullablePropertiesHook implements ModelHookInterface
{
    public function run(ModelsCommand $command, Model $model): void
    {
        $ref = new ReflectionProperty($command, 'properties');

        /** @var array<string, array{type: string, read: bool|null, write: bool|null, comment: string}> $properties */
        $properties = $ref->getValue($command);

        foreach ($properties as $name => $property) {
            if (str_ends_with($property['type'], '|null')) {
                continue;
            }

            $command->setProperty(
                $name,
                $property['type'],
                $property['read'],
                $property['write'],
                $property['comment'],
                true,
            );
        }
    }
}
