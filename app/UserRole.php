<?php

namespace App;

enum UserRole: string
{
    case Administrator = 'administrator';
    case SubAdministrator = 'sub_administrator';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::SubAdministrator => 'Sub-Administrator',
            self::Agent => 'Agent',
        };
    }
}
