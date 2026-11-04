<?php

declare(strict_types=1);

namespace App\Models;

use App\Relations\WithinDonut;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property non-empty-list<array{0: float, 1: float}> $boundary [[lat, lng], ...]
 * @property array<string, mixed>|null $properties
 */
class Neighborhood extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'boundary' => 'array',
        'properties' => 'array',
    ];

    /** Incidents inside the ring between $innerKm and $outerKm of this neighborhood's centroid, nearest first. */
    public function incidents(float $innerKm = 0.5, float $outerKm = 2.0): WithinDonut
    {
        return new WithinDonut(Incident::query(), $this, $innerKm, $outerKm);
    }

    /**
     * Center of the boundary's bounding box.
     *
     * Not the area-weighted (shoelace) centroid or the vertex average: the challenge
     * data is laid out around the bbox center, and the other two shift the ring
     * enough (~70-130 m) to swap flag incidents for decoys at the inner edge.
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    public function centroid(): array
    {
        $lats = array_column($this->boundary, 0);
        $lngs = array_column($this->boundary, 1);

        return [(min($lats) + max($lats)) / 2, (min($lngs) + max($lngs)) / 2];
    }
}
