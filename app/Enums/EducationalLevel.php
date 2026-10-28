<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed educational level values, stored as a plain string column (D-017).
 */
enum EducationalLevel: string
{
    case Creche = 'creche';
    case Kg = 'kg';
    case Primary1 = 'primary_1';
    case Primary2 = 'primary_2';
    case Primary3 = 'primary_3';
    case Primary4 = 'primary_4';
    case Primary5 = 'primary_5';
    case Primary6 = 'primary_6';
    case Jhs1 = 'jhs_1';
    case Jhs2 = 'jhs_2';
    case Jhs3 = 'jhs_3';
    case Shs1 = 'shs_1';
    case Shs2 = 'shs_2';
    case Shs3 = 'shs_3';
    case Tertiary = 'tertiary';
    case Graduate = 'graduate';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Creche => 'Creche',
            self::Kg => 'KG',
            self::Primary1 => 'Primary 1',
            self::Primary2 => 'Primary 2',
            self::Primary3 => 'Primary 3',
            self::Primary4 => 'Primary 4',
            self::Primary5 => 'Primary 5',
            self::Primary6 => 'Primary 6',
            self::Jhs1 => 'JHS 1',
            self::Jhs2 => 'JHS 2',
            self::Jhs3 => 'JHS 3',
            self::Shs1 => 'SHS 1',
            self::Shs2 => 'SHS 2',
            self::Shs3 => 'SHS 3',
            self::Tertiary => 'Tertiary',
            self::Graduate => 'Graduate',
        };
    }

    /**
     * Whether this level counts as youth for the youth pastor's view of the directory (D-031):
     * everything from creche to tertiary. Graduates are adults.
     */
    public function isYouth(): bool
    {
        return $this !== self::Graduate;
    }
}
