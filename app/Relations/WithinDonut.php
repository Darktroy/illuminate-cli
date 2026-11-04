<?php

declare(strict_types=1);

namespace App\Relations;

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Neighborhood -> incidents whose great-circle distance from the neighborhood's
 * centroid falls between an inner and outer radius (a "donut"), nearest first.
 *
 * There is no foreign key: the link is purely geometric, so constraints are
 * a distance expression instead of a key comparison.
 *
 * @extends Relation<Incident, Neighborhood, Collection<int, Incident>>
 */
class WithinDonut extends Relation
{
    // ponytail: equirectangular projection at 111 km/deg, the metric the challenge data was
    // generated with; error vs haversine is ~0.2% within a few km. Use haversine for long ranges.
    public const KM_PER_DEGREE = 111.0;

    /** @param Builder<Incident> $query */
    public function __construct(
        Builder $query,
        Neighborhood $parent,
        protected float $innerKm,
        protected float $outerKm,
    ) {
        parent::__construct($query, $parent);
    }

    /** Lazy load ($neighborhood->incidents): filter and order in SQL. */
    public function addConstraints(): void
    {
        if (! static::$constraints) {
            return;
        }

        [$lat, $lng] = $this->parent->centroid();
        [$sql, $bindings] = $this->distanceSql($lat, $lng);

        $this->query
            ->select($this->related->qualifyColumn('*'))
            ->selectRaw("{$sql} as distance_km", $bindings)
            // PDO binds as text; SQLite orders every number before any text, so cast explicitly.
            ->whereRaw("{$sql} between cast(? as real) and cast(? as real)", [...$bindings, $this->innerKm, $this->outerKm])
            ->orderBy('distance_km');
    }

    /**
     * Eager load (Neighborhood::with('incidents')): fetch the union of every
     * parent's donut in one query; match() splits it back out per parent.
     *
     * @param  array<int, Neighborhood>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->where(function (Builder $query) use ($models): void {
            foreach ($models as $model) {
                [$sql, $bindings] = $this->distanceSql(...$model->centroid());
                $query->orWhereRaw("{$sql} between cast(? as real) and cast(? as real)", [...$bindings, $this->innerKm, $this->outerKm]);
            }
        });
    }

    /**
     * @param  array<int, Neighborhood>  $models
     * @return array<int, Neighborhood>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * @param  array<int, Neighborhood>  $models
     * @param  Collection<int, Incident>  $results
     * @return array<int, Neighborhood>
     */
    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $model) {
            [$lat, $lng] = $model->centroid();

            $matched = $results
                ->map(function (Incident $incident) use ($lat, $lng): Incident {
                    $incident = clone $incident; // an incident can sit in several donuts at different distances
                    $incident->distance_km = self::distanceKm($lat, $lng, $incident->lat, $incident->lng);

                    return $incident;
                })
                ->filter(fn (Incident $incident): bool => $incident->distance_km >= $this->innerKm
                    && $incident->distance_km <= $this->outerKm)
                ->sortBy('distance_km')
                ->values();

            $model->setRelation($relation, $matched);
        }

        return $models;
    }

    /** @return Collection<int, Incident> */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /** Same formula as distanceSql(), for matching eager-loaded results in PHP. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = $lat2 - $lat1;
        $dLng = ($lng2 - $lng1) * cos(deg2rad($lat1));

        return self::KM_PER_DEGREE * sqrt($dLat ** 2 + $dLng ** 2);
    }

    /**
     * Distance in km from a fixed point to each row. cos(lat) is computed in PHP,
     * so the SQL is plain arithmetic plus sqrt (SQLite 3.35+, MySQL, Postgres).
     *
     * @return array{0: string, 1: list<float>}
     */
    protected function distanceSql(float $lat, float $lng): array
    {
        $grammar = $this->query->getQuery()->getGrammar();
        $latCol = $grammar->wrap($this->related->qualifyColumn('lat'));
        $lngCol = $grammar->wrap($this->related->qualifyColumn('lng'));
        $k = self::KM_PER_DEGREE;
        $lngScale = cos(deg2rad($lat));

        $sql = "({$k} * sqrt(({$latCol} - ?) * ({$latCol} - ?) + ({$lngCol} - ?) * ({$lngCol} - ?) * ? * ?))";

        return [$sql, [$lat, $lat, $lng, $lng, $lngScale, $lngScale]];
    }
}
