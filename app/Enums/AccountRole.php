<?php

namespace App\Enums;

enum AccountRole: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Librarian = 'librarian';
    case Parent = 'parent';
    case Supervisor = 'supervisor';
    case Manager = 'manager';
    case Dean = 'dean';
    case Professor = 'professor';
    case Administrator = 'administrator';
    case Receptionist = 'receptionist';
    case Cashier = 'cashier';
    case Director = 'director';
    case Subdirector = 'subdirector';
    case Salesperson = 'salesperson';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Platform Admin',
            self::Subdirector => 'Deputy Director',
            default => ucfirst($this->value),
        };
    }

    /** Institution-scoped roles permitted to access every campus of their institution. */
    public static function institutionWideCodes(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            [self::Administrator, self::Director, self::Subdirector, self::Manager, self::Dean, self::Supervisor]
        );
    }
}
