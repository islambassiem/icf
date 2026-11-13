<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use RuntimeException;
use UnitEnum;

/** Finds an enum case by name or value, ignoring case. Fails loudly instead of guessing. */
final class EnumPick
{
    /**
     * @param  class-string<UnitEnum>  $enum
     */
    public static function case(string $enum, string $want): UnitEnum
    {
        foreach ($enum::cases() as $case) {
            $value = $case instanceof BackedEnum ? (string) $case->value : $case->name;

            if (strcasecmp($value, $want) === 0 || strcasecmp($case->name, $want) === 0) {
                return $case;
            }
        }

        throw new RuntimeException("{$enum} has no case matching '{$want}'. Add it, or adjust App\\Services\\Payments\\PaymentService.");
    }
}
