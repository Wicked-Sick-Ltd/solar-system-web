<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $user_id @property int $revision @property array<string,mixed>|null $payload */
final class PrivateObservingWorkspace extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'payload' => 'encrypted:array'];
    }
}
