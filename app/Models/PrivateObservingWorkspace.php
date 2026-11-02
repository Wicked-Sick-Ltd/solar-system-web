<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** @property int $user_id @property int $revision @property array<string,mixed>|null $payload */
#[Guarded('id', 'user_id')]
#[Hidden('payload')]
final class PrivateObservingWorkspace extends Model
{
    protected function casts(): array
    {
        return ['revision' => 'integer', 'payload' => 'encrypted:array'];
    }
}
