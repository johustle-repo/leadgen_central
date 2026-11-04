<?php

namespace App;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-time',
            self::PartTime => 'Part-time',
        };
    }
}
