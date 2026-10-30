<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $organisation
 * @property string $contact_name
 * @property string $email
 * @property string $use_case
 * @property string $expected_volume
 * @property string|null $egress_cidrs
 * @property bool $institution_eligible
 */
final class ApiAccessRequest extends Model
{
    protected $fillable = [
        'organisation',
        'contact_name',
        'email',
        'use_case',
        'expected_volume',
        'egress_cidrs',
        'institution_eligible',
    ];

    protected function casts(): array
    {
        return [
            'institution_eligible' => 'boolean',
        ];
    }
}
