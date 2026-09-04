<?php

namespace App;

enum UserRole: string
{
    case SuperAdministrator = 'super_administrator';
    case Administrator = 'administrator';
    case SubAdministrator = 'sub_administrator';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrator',
            self::Administrator => 'Administrator',
            self::SubAdministrator => 'Sub-Administrator',
            self::Agent => 'Agent',
        };
    }
}
