<?php
namespace App\Enums;

use Spatie\Enum\Laravel\Enum;

/**
 * @method static self INDIVIDUAL()
 * @method static self BUSINESS()
 *
 */
class AccountType extends Enum
{
    const INDIVIDUAL = "individual";
    const BUSINESS = "business";
    public static function isValid($value): bool
    {
        return in_array($value, self::values());
    }
}
