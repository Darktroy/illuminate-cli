<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Support\Facades\DB;
use LaravelZero\Framework\Commands\Command;

class ImportGisDataCommand extends Command
{
    protected $signature = 'gis:import';

    protected $description = 'Stage 3: copy neighborhoods and incidents from the remote Postgres into local SQLite';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        $remote = DB::connection('remote');

        // Let Postgres unpack its native types (point, polygon, arrays) so PHP only sees scalars and JSON.
        $neighborhoods = $remote->table('neighborhoods')
            ->select('id', 'name', 'properties')
            ->selectRaw('boundary::text as boundary')
            ->get();

        $incidents = $remote->table('incidents')
            ->select('id', 'metadata', 'occurred_at')
            ->selectRaw('location[0] as lat, location[1] as lng')
            ->selectRaw("metadata->'incident'->>'code' as code")
            ->selectRaw('array_to_json(tags) as tags, array_to_json(reports) as reports')
            ->get();

        DB::transaction(function () use ($neighborhoods, $incidents): void {
            foreach ($neighborhoods as $row) {
                Neighborhood::query()->updateOrCreate(['id' => $row->id], [
                    'name' => $row->name,
                    'boundary' => self::parsePolygon($row->boundary),
                    'properties' => json_decode((string) $row->properties, true),
                ]);
            }

            foreach ($incidents as $row) {
                Incident::query()->updateOrCreate(['id' => $row->id], [
                    'lat' => $row->lat,
                    'lng' => $row->lng,
                    'code' => $row->code,
                    'metadata' => json_decode((string) $row->metadata, true),
                    'tags' => json_decode((string) $row->tags, true),
                    'reports' => json_decode((string) $row->reports, true),
                    'occurred_at' => $row->occurred_at,
                ]);
            }
        });

        $this->info("Imported {$neighborhoods->count()} neighborhoods and {$incidents->count()} incidents.");

        return self::SUCCESS;
    }

    /**
     * Postgres polygon text "((x1,y1),(x2,y2),...)" -> [[x1, y1], [x2, y2], ...].
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function parsePolygon(string $polygon): array
    {
        preg_match_all('/\(\s*(-?[\d.eE+-]+)\s*,\s*(-?[\d.eE+-]+)\s*\)/', $polygon, $m, PREG_SET_ORDER);

        return array_map(fn (array $p): array => [(float) $p[1], (float) $p[2]], $m);
    }
}
