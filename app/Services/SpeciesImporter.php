<?php

namespace App\Services;

use App\Models\Species;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpeciesImporter
{
    /**
     * Minimum number of published source rows expected before an import is
     * allowed to run. Guards against wiping local data from a broken dump.
     */
    private const SANITY_MIN_SOURCE_ROWS = 50;

    /** @var array<string, array<int, string>> */
    private array $lutMaps = [];

    /** @var array<string, array<int, string>> */
    private array $lutMapsEn = [];

    /** @var array<int, string> */
    private array $categoryGroupByCategoryId = [];

    public function __construct(
        private readonly string $connection = 'pkl',
        private readonly string $mediaBaseUrl = 'https://species.phakhaolao.la',
    ) {}

    /**
     * Run the import.
     *
     * @return array{imported: int, changed: int, archived: int, source: int}
     */
    public function import(bool $dryRun = false, int $limit = 0): array
    {
        $source = DB::connection($this->connection);

        $sourceCount = (int) $source->table('data.specie')
            ->where('is_published', true)
            ->where('is_delete', false)
            ->count();

        if ($sourceCount < self::SANITY_MIN_SOURCE_ROWS) {
            throw new \RuntimeException(
                "Source has only {$sourceCount} published species (min ".self::SANITY_MIN_SOURCE_ROWS.'). Aborting to protect local data.'
            );
        }

        $this->preloadLookups($source);

        $useTypes = $this->aggregateNames($source, 'data.sub_uses', 'use_id', 'public.lut_use');
        $useUnits = $this->aggregateBilingualNames($source, 'data.sub_uses', 'use_id', 'public.lut_use');
        $useGroups = $this->aggregateUseGroups($source);
        $habitats = $this->aggregateNames($source, 'data.sub_landscapes', 'landscape_id', 'public.lut_landscape');
        $seasons = $this->aggregateNames($source, 'data.sub_seasonals', 'month_id', 'public.lut_month');
        $distributions = $this->aggregateNames($source, 'data.sub_distributions', 'distribution_id', 'public.lut_distribution');
        $distributionUnits = $this->aggregateBilingualNames($source, 'data.sub_distributions', 'distribution_id', 'public.lut_distribution');
        $ntfpLists = $this->aggregateBilingualNames($source, 'data.sub_ntfp_lists', 'ntfp_list_id', 'public.lut_ntfp_list', stripParens: true);
        $timberLists = $this->aggregateBilingualNames($source, 'data.sub_timber_lists', 'timber_list_id', 'public.lut_timber_list', stripParens: true);
        $nutritionalValues = $this->aggregateNames($source, 'data.sub_nutritional_values', 'nutritional_value_id', 'public.lut_nutritional_value');
        $landscapeUnits = $this->aggregateLandscapeUnits($source);
        $provinces = $this->aggregateProvinces($source);
        $relatives = $this->aggregateRelatives($source);
        $photos = $this->aggregatePhotos($source);
        $references = $this->aggregateReferences($source);

        $query = $source->table('data.specie as s')
            ->leftJoin('data.ecology as e', 'e.id_specie', '=', 's.id')
            ->leftJoin('data.utilize as u', 'u.id_specie', '=', 's.id')
            ->leftJoin('data.workflow as w', 'w.id_specie', '=', 's.id')
            ->where('s.is_published', true)
            ->where('s.is_delete', false)
            ->orderBy('s.id')
            ->select([
                's.id', 's.name_la', 's.name_en', 's.name_local_la', 's.name_local_en',
                's.scientific_name', 's.family', 's.name_syn', 's.category_id', 's.category_sub_id',
                's.spec_descr_la', 's.spec_descr_en', 's.inaturalist_taxon_id', 's.gbif_taxonkey',
                'e.distribution_global', 'e.topographic_descr_la', 'e.topographic_descr_en',
                'e.landscape_descr_la', 'e.landscape_descr_en', 'e.obser_descr_la', 'e.obser_descr_en',
                'e.endemism_id', 'e.endemism_descr_la', 'e.endemism_descr_en',
                'e.invasiveness_id', 'e.conservation_iucn_id', 'e.conservation_lao_id',
                'e.conserv_descr_la', 'e.conserv_descr_en',
                'u.use_descr_la', 'u.use_descr_en', 'u.domes_descr_la', 'u.domes_descr_en',
                'u.management_la', 'u.management_en', 'u.value_chains_la', 'u.value_chains_en',
                'u.nutr_value_descr_la', 'u.nutr_value_descr_en', 'u.nutrient_descr_la', 'u.nutrient_descr_en',
                'u.proteins', 'u.carbs', 'u.fats', 'u.vitamins', 'u.minerals', 'u.fibers', 'u.nutrient',
                'u.domestication_id', 'w.status_id',
            ]);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $imported = 0;
        $changed = 0;
        $archived = 0;
        $importedSourceIds = [];

        DB::transaction(function () use (
            $rows, $dryRun, $useTypes, $useUnits, $useGroups, $habitats, $seasons, $distributions, $ntfpLists, $timberLists,
            $nutritionalValues, $landscapeUnits, $distributionUnits, $provinces, $relatives, $photos, $references,
            &$imported, &$changed, &$archived, &$importedSourceIds, $limit
        ) {
            foreach ($rows as $row) {
                $sid = (int) $row->id;
                $importedSourceIds[] = $sid;

                $data = [
                    'scientific_name' => $this->clean($row->scientific_name),
                    'common_name_lao' => $this->clean($row->name_la),
                    'common_name_english' => $this->clean($row->name_en),
                    'family' => $this->clean($row->family),
                    'category' => $this->categoryGroup($row->category_id),
                    'category_en' => $this->categoryGroupEn($row->category_id),
                    'subcategory' => $this->lut('public.lut_category', $row->category_id),
                    'subcategory_en' => $this->lutEn('public.lut_category', $row->category_id),
                    'species_type' => $this->lut('public.lut_category_sub', $row->category_sub_id),
                    'species_type_en' => $this->lutEn('public.lut_category_sub', $row->category_sub_id),
                    'iucn_status' => $this->lut('public.lut_conservation_iucn', $row->conservation_iucn_id),
                    'iucn_status_en' => $this->lutEn('public.lut_conservation_iucn', $row->conservation_iucn_id),
                    'national_conservation_status' => $this->lut('public.lut_conservation_lao', $row->conservation_lao_id),
                    'national_conservation_status_en' => $this->lutEn('public.lut_conservation_lao', $row->conservation_lao_id),
                    'native_status' => $this->lut('public.lut_endemism', $row->endemism_id),
                    'native_status_en' => $this->lutEn('public.lut_endemism', $row->endemism_id),
                    'invasiveness' => $this->lut('public.lut_invasiveness', $row->invasiveness_id),
                    'invasiveness_en' => $this->lutEn('public.lut_invasiveness', $row->invasiveness_id),
                    'domestication' => $this->lut('public.lut_domestication', $row->domestication_id),
                    'domestication_en' => $this->lutEn('public.lut_domestication', $row->domestication_id),
                    'data_status' => $this->lut('public.lut_status', $row->status_id),
                    'data_status_en' => $this->lutEn('public.lut_status', $row->status_id),
                    'harvest_season' => $this->joinNames($seasons[$sid] ?? []),
                    'local_names' => $this->splitLines([$row->name_local_la, $row->name_local_en]),
                    'synonyms' => $this->splitLines([$row->name_syn]),
                    'related_species' => $relatives[$sid] ?? [],
                    'habitat_types' => $habitats[$sid] ?? [],
                    'landscape_units' => $landscapeUnits[$sid] ?? [],
                    'use_types' => $useTypes[$sid] ?? [],
                    'use_units' => $useUnits[$sid] ?? [],
                    'use_groups' => $useGroups[$sid] ?? [],
                    'ntfp_lists' => $ntfpLists[$sid] ?? [],
                    'timber_lists' => $timberLists[$sid] ?? [],
                    'nutrition' => $this->buildNutrition($row, $nutritionalValues[$sid] ?? []),
                    'image_urls' => $photos[$sid] ?? [],
                    'provinces' => $provinces[$sid] ?? [],
                    'references' => $references[$sid]['references'] ?? [],
                    'external_links' => $references[$sid]['external_links'] ?? [],
                    'botanical_description' => $this->laoFirst($row->spec_descr_la, $row->spec_descr_en),
                    'botanical_description_en' => $this->clean($row->spec_descr_en),
                    'global_distribution' => $this->clean($row->distribution_global),
                    'topographic_description' => $this->laoFirst($row->topographic_descr_la ?? null, $row->topographic_descr_en ?? null),
                    'topographic_description_en' => $this->clean($row->topographic_descr_en ?? null),
                    'landscape_description' => $this->laoFirst($row->landscape_descr_la ?? null, $row->landscape_descr_en ?? null),
                    'landscape_description_en' => $this->clean($row->landscape_descr_en ?? null),
                    'observation_description' => $this->laoFirst($row->obser_descr_la ?? null, $row->obser_descr_en ?? null),
                    'observation_description_en' => $this->clean($row->obser_descr_en ?? null),
                    'conservation_note' => $this->laoFirst($row->conserv_descr_la ?? null, $row->conserv_descr_en ?? null),
                    'conservation_note_en' => $this->clean($row->conserv_descr_en ?? null),
                    'endemism_description' => $this->laoFirst($row->endemism_descr_la ?? null, $row->endemism_descr_en ?? null),
                    'endemism_description_en' => $this->clean($row->endemism_descr_en ?? null),
                    'lao_distribution' => $this->joinNames($distributions[$sid] ?? []),
                    'distribution_units' => $distributionUnits[$sid] ?? [],
                    'inaturalist_taxon_id' => $row->inaturalist_taxon_id ? (int) $row->inaturalist_taxon_id : null,
                    'gbif_taxon_key' => $row->gbif_taxonkey ? (int) $row->gbif_taxonkey : null,
                    'use_description' => $this->laoFirst($row->use_descr_la, $row->use_descr_en),
                    'use_description_en' => $this->clean($row->use_descr_en),
                    'cultivation_info' => $this->laoFirst($row->domes_descr_la, $row->domes_descr_en),
                    'cultivation_info_en' => $this->clean($row->domes_descr_en),
                    'market_data' => $this->laoFirst($row->value_chains_la, $row->value_chains_en),
                    'market_data_en' => $this->clean($row->value_chains_en),
                    'management_info' => $this->laoFirst($row->management_la, $row->management_en),
                    'management_info_en' => $this->clean($row->management_en),
                    'nutrition_description' => $this->laoFirst($row->nutr_value_descr_la, $row->nutr_value_descr_en)
                        ?? $this->laoFirst($row->nutrient_descr_la, $row->nutrient_descr_en),
                    'nutrition_description_en' => $this->clean($row->nutr_value_descr_en)
                        ?? $this->clean($row->nutrient_descr_en),
                ];

                $hash = $this->hash($data);

                if ($dryRun) {
                    $imported++;

                    continue;
                }

                $existing = Species::query()->where('source_id', $sid)->first();
                $contentChanged = ! $existing || $existing->content_hash !== $hash;

                $data['source_id'] = $sid;
                $data['scrape_status'] = 'scraped';
                $data['scrape_error'] = null;
                $data['content_hash'] = $hash;

                if ($contentChanged) {
                    $data['embedding'] = null;
                    $data['scraped_at'] = now();
                    $changed++;
                }

                Species::query()->updateOrCreate(['source_id' => $sid], $data);
                $imported++;
            }

            // Non-destructive: archive local rows no longer present in the source.
            if (! $dryRun && $limit === 0 && count($importedSourceIds) >= self::SANITY_MIN_SOURCE_ROWS) {
                $archived = Species::query()
                    ->where('scrape_status', 'scraped')
                    ->whereNotIn('source_id', $importedSourceIds)
                    ->update(['scrape_status' => 'archived']);
            }
        });

        return [
            'imported' => $imported,
            'changed' => $changed,
            'archived' => $archived,
            'source' => $sourceCount,
        ];
    }

    private function preloadLookups($source): void
    {
        foreach ([
            'public.lut_use', 'public.lut_landscape', 'public.lut_month', 'public.lut_distribution',
            'public.lut_ntfp_list', 'public.lut_timber_list', 'public.lut_nutritional_value',
            'public.lut_category', 'public.lut_category_sub', 'public.lut_category_group',
            'public.lut_conservation_iucn', 'public.lut_conservation_lao', 'public.lut_endemism',
            'public.lut_invasiveness', 'public.lut_province',
            'public.lut_domestication', 'public.lut_status',
        ] as $table) {
            $records = $source->table($table)->get(['id', 'name_la', 'name_en']);

            $this->lutMaps[$table] = $records
                ->mapWithKeys(fn ($r) => [(int) $r->id => (string) $this->laoFirst($r->name_la, $r->name_en)])
                ->all();

            $this->lutMapsEn[$table] = $records
                ->mapWithKeys(fn ($r) => [(int) $r->id => $this->clean($r->name_en) ?? $this->clean($r->name_la)])
                ->filter()
                ->all();
        }

        $this->categoryGroupByCategoryId = $source->table('public.lut_category')
            ->get(['id', 'id_category_group'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => (int) $r->id_category_group])
            ->all();
    }

    private function lut(string $table, mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->lutMaps[$table][(int) $id] ?? null;
    }

    private function lutEn(string $table, mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->lutMapsEn[$table][(int) $id] ?? null;
    }

    private function categoryGroup(mixed $categoryId): ?string
    {
        return $this->categoryGroupName($categoryId, $this->lutMaps['public.lut_category_group']);
    }

    private function categoryGroupEn(mixed $categoryId): ?string
    {
        return $this->categoryGroupName($categoryId, $this->lutMapsEn['public.lut_category_group']);
    }

    /**
     * @param  array<int, string>  $groupNames
     */
    private function categoryGroupName(mixed $categoryId, array $groupNames): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        $groupId = $this->categoryGroupByCategoryId[(int) $categoryId] ?? null;

        return $groupId ? ($groupNames[$groupId] ?? null) : null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function aggregateNames($source, string $subTable, string $fkColumn, string $lutTable): array
    {
        return $source->table($subTable.' as sub')
            ->join($lutTable.' as lut', 'lut.id', '=', 'sub.'.$fkColumn)
            ->get(['sub.id_specie', 'lut.name_la', 'lut.name_en'])
            ->groupBy('id_specie')
            ->map(fn (Collection $g) => $g
                ->map(fn ($r) => $this->laoFirst($r->name_la, $r->name_en))
                ->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * Aggregate a sub-table's lut values per species as bilingual {lao, en} objects.
     *
     * @return array<int, array<int, array{lao: string, en: ?string}>>
     */
    private function aggregateBilingualNames($source, string $subTable, string $fkColumn, string $lutTable, bool $stripParens = false): array
    {
        return $source->table($subTable.' as sub')
            ->join($lutTable.' as lut', 'lut.id', '=', 'sub.'.$fkColumn)
            ->get(['sub.id_specie', 'lut.name_la', 'lut.name_en'])
            ->groupBy('id_specie')
            ->map(function (Collection $g) use ($stripParens): array {
                $items = [];

                foreach ($g as $r) {
                    $lao = $this->clean($r->name_la);
                    $en = $this->clean($r->name_en);

                    if ($stripParens) {
                        $lao = $this->stripTrailingParens($lao);
                        $en = $this->stripTrailingParens($en);
                    }

                    if ($lao !== null) {
                        $items[$lao] = ['lao' => $lao, 'en' => $en];
                    }
                }

                return array_values($items);
            })
            ->all();
    }

    /**
     * Parent use groups (Human Body, Household, Community, Prohibitions) per species,
     * derived from sub_uses -> lut_use -> lut_use_type, bilingually.
     *
     * @return array<int, array<int, array{lao: string, en: ?string}>>
     */
    private function aggregateUseGroups($source): array
    {
        return $source->table('data.sub_uses as su')
            ->join('public.lut_use as u', 'u.id', '=', 'su.use_id')
            ->join('public.lut_use_type as ut', 'ut.id', '=', 'u.id_use_type')
            ->get(['su.id_specie', 'ut.name_la', 'ut.name_en'])
            ->groupBy('id_specie')
            ->map(function (Collection $g): array {
                $items = [];

                foreach ($g as $r) {
                    $lao = $this->clean($r->name_la);
                    if ($lao !== null) {
                        $items[$lao] = ['lao' => $lao, 'en' => $this->clean($r->name_en)];
                    }
                }

                return array_values($items);
            })
            ->all();
    }

    private function stripTrailingParens(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->clean(preg_replace('/\s*\([^)]*\)\s*$/u', '', $value) ?? $value);
    }

    /**
     * Landscape units from sub_landscapes, capturing both the top-level landscape
     * and the sub-level (e.g. Forest + Evergreen), bilingually.
     *
     * @return array<int, array<int, array{lao: string, en: ?string}>>
     */
    private function aggregateLandscapeUnits($source): array
    {
        return $source->table('data.sub_landscapes as sl')
            ->leftJoin('public.lut_landscape as l', 'l.id', '=', 'sl.landscape_id')
            ->leftJoin('public.lut_landscape_sub as ls', 'ls.id', '=', 'sl.landscape_sub_id')
            ->get(['sl.id_specie', 'l.name_la as l_la', 'l.name_en as l_en', 'ls.name_la as ls_la', 'ls.name_en as ls_en'])
            ->groupBy('id_specie')
            ->map(function (Collection $g): array {
                $units = [];

                foreach ($g as $r) {
                    foreach ([[$r->l_la, $r->l_en], [$r->ls_la, $r->ls_en]] as [$la, $en]) {
                        $lao = $this->clean($la);
                        if ($lao !== null) {
                            $units[$lao] = ['lao' => $lao, 'en' => $this->clean($en)];
                        }
                    }
                }

                return array_values($units);
            })
            ->all();
    }

    /**
     * @return array<int, array<int, array{name: string, district: ?string, count: ?int}>>
     */
    private function aggregateProvinces($source): array
    {
        return $source->table('data.sub_observations as so')
            ->join('public.lut_province as p', 'p.id', '=', 'so.province_id')
            ->leftJoin('public.lut_district as d', 'd.id', '=', 'so.district_id')
            ->get([
                'so.id_specie', 'p.name_la as p_la', 'p.name_en as p_en',
                'd.name_la as d_la', 'd.name_en as d_en', 'so.observ_number',
            ])
            ->groupBy('id_specie')
            ->map(fn (Collection $g) => $g
                ->map(fn ($r) => [
                    'name' => $this->laoFirst($r->p_la, $r->p_en),
                    'district' => $this->laoFirst($r->d_la, $r->d_en),
                    'count' => $r->observ_number !== null ? (int) $r->observ_number : null,
                ])
                ->filter(fn ($p) => $p['name'] !== null)
                ->unique('name')->values()->all())
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function aggregateRelatives($source): array
    {
        return $source->table('data.sub_close_relatives as scr')
            ->join('data.specie as rel', 'rel.id', '=', 'scr.specie_id')
            ->get(['scr.id_specie', 'rel.scientific_name', 'rel.name_la'])
            ->groupBy('id_specie')
            ->map(fn (Collection $g) => $g
                ->map(fn ($r) => $this->clean($r->scientific_name) ?? $this->clean($r->name_la))
                ->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function aggregatePhotos($source): array
    {
        return $source->table('data.photos')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->get(['id_specie', 'photo_path'])
            ->groupBy('id_specie')
            ->map(fn (Collection $g) => $g
                ->map(fn ($r) => $this->mediaUrl($r->photo_path))
                ->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * @return array<int, array{references: array, external_links: array}>
     */
    private function aggregateReferences($source): array
    {
        return $source->table('data.reference')
            ->get()
            ->groupBy('id_specie')
            ->map(function (Collection $g) {
                $r = $g->first();
                $references = [];

                if ($content = $this->clean($r->reference)) {
                    $references[] = ['type' => 'reference', 'content' => $content];
                }
                if ($credits = $this->clean($r->photo_credits)) {
                    $references[] = ['type' => 'photo_credit', 'content' => $credits];
                }

                $external = array_filter([
                    'inaturalist' => $this->clean($r->link_inaturalist),
                    'redlist' => $this->clean($r->link_redlist),
                    'planet' => $this->clean($r->link_planet),
                    'youtube' => array_values(array_filter([
                        $this->clean($r->link_youtube),
                        $this->clean($r->link_youtube2 ?? null),
                        $this->clean($r->link_youtube3 ?? null),
                    ])),
                    'others' => $this->clean($r->link_others),
                ], fn ($v) => $v !== null && $v !== []);

                return ['references' => $references, 'external_links' => $external];
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $nutritionalValueNames
     * @return array<int, array{nutrient: string, value: ?float}>
     */
    private function buildNutrition(object $row, array $nutritionalValueNames): array
    {
        $entries = [];

        foreach ([
            'proteins' => $row->proteins, 'carbohydrates' => $row->carbs, 'fats' => $row->fats,
            'vitamins' => $row->vitamins, 'minerals' => $row->minerals, 'fibers' => $row->fibers,
            'energy' => $row->nutrient,
        ] as $label => $value) {
            if ($value !== null) {
                $entries[] = ['nutrient' => $label, 'value' => (float) $value];
            }
        }

        foreach ($nutritionalValueNames as $name) {
            $entries[] = ['nutrient' => $name, 'value' => null];
        }

        return $entries;
    }

    private function mediaUrl(?string $path): ?string
    {
        $path = $this->clean($path);
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'public/')) {
            return rtrim($this->mediaBaseUrl, '/').'/storage/'.substr($path, strlen('public/'));
        }

        return rtrim($this->mediaBaseUrl, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<int, string>  $names
     */
    private function joinNames(array $names): ?string
    {
        $names = array_values(array_filter($names));

        return $names === [] ? null : implode(', ', $names);
    }

    /**
     * Split newline-separated source text into a clean list of values.
     *
     * @param  array<int, ?string>  $values
     * @return array<int, string>
     */
    private function splitLines(array $values): array
    {
        $out = [];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            foreach (preg_split('/[\r\n]+/u', $value) ?: [] as $line) {
                if ($clean = $this->clean($line)) {
                    $out[] = $clean;
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function laoFirst(?string $lao, ?string $english): ?string
    {
        return $this->clean($lao) ?? $this->clean($english);
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * Fields that feed the embedding document (see EmbedSpecies::buildEmbeddingDocument).
     * Only these affect the content hash, so adding derived filter/label columns
     * (e.g. *_en, *_units, *_lists) does not needlessly trigger re-embedding.
     *
     * @var list<string>
     */
    private const EMBEDDING_FIELDS = [
        'scientific_name', 'common_name_lao', 'common_name_english', 'family',
        'iucn_status', 'native_status', 'invasiveness', 'use_description',
        'botanical_description', 'global_distribution', 'lao_distribution',
        'cultivation_info', 'market_data', 'management_info', 'threats', 'harvest_season',
        'local_names', 'synonyms', 'related_species', 'habitat_types', 'use_types', 'nutrition',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function hash(array $data): string
    {
        $core = array_intersect_key($data, array_flip(self::EMBEDDING_FIELDS));
        ksort($core);

        return md5(json_encode($core, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
