<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\EnumDisplayArray;

enum MaritalStatus: string
{
    use EnumDisplayArray;

    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
}
