<?php

use App\Support\SourceDatabase;
use Illuminate\Console\Scheduling\Schedule;

function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn ($event): string => (string) $event->command)
        ->all();
}

function scheduledEventFor(string $command)
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, $command));
}

it('registers every nightly source sync', function (string $command) {
    expect(implode(' ', scheduledCommands()))->toContain($command);
})->with([
    'species:import',
    'champions:import',
    'stories:import',
    'library:import',
    'species:embed',
]);

it('keeps the species sync off until it is explicitly enabled', function () {
    expect(config('sync.species'))->toBeFalse();

    $event = scheduledEventFor('species:import');

    expect($event->filtersPass(app()))->toBeFalse();
});

it('runs the wordpress imports by default', function (string $command) {
    $event = scheduledEventFor($command);

    expect($event->filtersPass(app()))->toBeTrue();
})->with(['champions:import', 'stories:import', 'library:import']);

it('reports the species source database as unreachable when it is not configured', function () {
    config(['database.connections.pkl.host' => '127.0.0.1', 'database.connections.pkl.port' => 1]);

    expect(SourceDatabase::reachable())->toBeFalse();
});

it('skips a sync that is toggled off in config', function () {
    config(['sync.champions' => false]);

    expect(scheduledEventFor('champions:import')->filtersPass(app()))->toBeFalse();
});
