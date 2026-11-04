<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use LaravelZero\Framework\Commands\Command;

class DonutFlagCommand extends Command
{
    protected $signature = 'gis:donut
        {neighborhood=NB-7A2F : Neighborhood name}
        {--inner=0.5 : Inner radius in km}
        {--outer=2.0 : Outer radius in km}';

    protected $description = 'Stage 3: list incidents in the donut around a neighborhood centroid and reveal the flag';

    public function handle(): int
    {
        $neighborhood = Neighborhood::query()->where('name', $this->argument('neighborhood'))->firstOrFail();

        $incidents = $neighborhood
            ->incidents((float) $this->option('inner'), (float) $this->option('outer'))
            ->get();

        [$lat, $lng] = $neighborhood->centroid();
        $this->line(sprintf('Centroid of %s: %.6f, %.6f — %d incidents in donut', $neighborhood->name, $lat, $lng, $incidents->count()));

        $this->table(
            ['id', 'distance (km)', 'code'],
            $incidents->map(fn (Incident $i): array => [$i->id, number_format((float) $i->distance_km, 4), $i->code])->all(),
        );

        $this->info($incidents->pluck('code')->implode(''));

        return self::SUCCESS;
    }
}
