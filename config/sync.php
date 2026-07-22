<?php

return [
    // Timezone the nightly sync times below are interpreted in.
    'timezone' => env('SYNC_TIMEZONE', 'Asia/Vientiane'),

    // WordPress REST API imports. These fetch over HTTPS, so they work from
    // any server and are safe to leave on.
    'champions' => (bool) env('SYNC_CHAMPIONS', true),
    'stories' => (bool) env('SYNC_STORIES', true),
    'library' => (bool) env('SYNC_LIBRARY', true),

    // Species reads the separate "pkl" Postgres database. Leave this off until
    // that database is reachable from this server; set the PKL_DB_* variables
    // and flip SYNC_SPECIES=true to enable — no code change needed. The
    // schedule also verifies the connection before running, so enabling it
    // early is harmless.
    'species' => (bool) env('SYNC_SPECIES', false),

    // Generate embeddings for species that don't have one yet. A no-op (and
    // free) when nothing new was imported.
    'embed' => (bool) env('SYNC_EMBED', true),

    // Times of day (in the timezone above) each sync runs.
    'times' => [
        'species' => env('SYNC_SPECIES_AT', '01:30'),
        'champions' => env('SYNC_CHAMPIONS_AT', '02:00'),
        'stories' => env('SYNC_STORIES_AT', '02:15'),
        'library' => env('SYNC_LIBRARY_AT', '02:30'),
        'embed' => env('SYNC_EMBED_AT', '03:00'),
    ],
];
