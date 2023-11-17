<?php
namespace App\Enums;

use Spatie\Enum\Laravel\Enum;

/**
 * @method static self CREDIT()
 * @method static self DEBIT()
 *
 */
class TransactionType extends Enum
{
    const CREDIT = "credit";
    const DEBIT = "debit";
}
