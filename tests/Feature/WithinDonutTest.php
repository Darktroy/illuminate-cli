<?php

declare(strict_types=1);

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    Artisan::call('migrate', ['--force' => true]);

    // 0.02° square around (33.33, 44.35); 0.009° of latitude ≈ 1 km at 111 km/deg.
    Neighborhood::query()->create(['id' => 1, 'name' => 'NB-T', 'boundary' => [[33.32, 44.34], [33.32, 44.36], [33.34, 44.36], [33.34, 44.34]]]);

    foreach ([
        [1, 33.33 + 0.0018, 'X'], // 0.2 km: inside the hole
        [2, 33.33 + 0.0135, 'B'], // 1.5 km
        [3, 33.33 - 0.0090, 'A'], // 1.0 km
        [4, 33.33 + 0.0270, 'Y'], // 3.0 km: beyond the ring
    ] as [$id, $lat, $code]) {
        Incident::query()->create(['id' => $id, 'lat' => $lat, 'lng' => 44.35, 'code' => $code]);
    }
});

it('returns only incidents inside the donut, nearest first', function (): void {
    $lazy = Neighborhood::query()->firstOrFail()->incidents(0.5, 2.0)->get();
    $eager = Neighborhood::query()->with('incidents')->firstOrFail()->incidents;

    expect($lazy->pluck('code')->implode(''))->toBe('AB')
        ->and($eager->pluck('code')->implode(''))->toBe('AB')
        ->and($lazy->first()->distance_km)->toEqualWithDelta(1.0, 0.001);
});
