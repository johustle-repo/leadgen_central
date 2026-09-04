<?php

namespace App;

enum AttendanceEntryType: string
{
    case TimeIn = 'time_in';
    case TimeOut = 'time_out';

    public function label(): string
    {
        return match ($this) {
            self::TimeIn => 'Time In',
            self::TimeOut => 'Time Out',
        };
    }
}
