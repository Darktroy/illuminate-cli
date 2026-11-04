<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property float $lat
 * @property float $lng
 * @property string|null $code
 * @property array<string, mixed>|null $metadata
 * @property list<string>|null $tags
 * @property list<array<string, mixed>>|null $reports
 * @property Carbon|null $occurred_at
 * @property float|null $distance_km only present when loaded through WithinDonut
 */
class Incident extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'metadata' => 'array',
        'tags' => 'array',
        'reports' => 'array',
        'occurred_at' => 'datetime',
        'distance_km' => 'float',
    ];
}
