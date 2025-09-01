<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrollStat: string
{
    case Pending = 'Pending';
    case VerifiedByDeptHead = 'Verified By Dept Head';
    case VerifiedByCashier = 'Verified By Cashier';
}
