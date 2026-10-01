<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $version
 * @property string $commit
 * @property array{title:string,summary:string,sections:array<string,list<string>>} $notes
 * @property CarbonInterface $published_at
 */
final class CommunityRelease extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['notes' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
