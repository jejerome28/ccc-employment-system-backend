<?php

namespace App\Enums;

// Case order is the CRA report block order.
enum EmploymentGroup: string
{
    case Permanent = 'permanent';
    case CosNonTeaching = 'cos_non_teaching';
    case PermanentNonTeaching = 'permanent_non_teaching';
    case CosTeaching = 'cos_teaching';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => '(Permanent)',
            self::CosNonTeaching => '(COS- Non Teaching Personnel)',
            self::PermanentNonTeaching => '(Permanent Non-Teaching Personnel)',
            self::CosTeaching => '(COS Full-time Teaching Personnel)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent Teaching',
            self::CosNonTeaching => 'COS Non-Teaching',
            self::PermanentNonTeaching => 'Permanent Non-Teaching',
            self::CosTeaching => 'COS Teaching',
        };
    }
}
