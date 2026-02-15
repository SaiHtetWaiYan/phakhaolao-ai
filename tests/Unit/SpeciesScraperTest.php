<?php

use App\Services\SpeciesScraper;

beforeEach(function () {
    $this->scraper = new SpeciesScraper(delayMs: 0);
    $this->detailHtml = file_get_contents(dirname(__DIR__) . '/Fixtures/species_detail_56.html');
});

it('parses the scientific name from the detail page', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['scientific_name'])->toBe('Amorphophallus paeoniifolius (Dennst.) Nicolson');
});

it('parses common names from the header', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['common_name_lao'])->toContain('ຫົວກະບຸກ');
    expect($data['common_name_english'])->toContain('Elephant Foot Yam');
});

it('parses the data collection level', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['data_collection_level'])->toBe('ຂໍ້ມູນລະອຽດ');
});

it('parses synonyms as an array', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['synonyms'])->toBeArray();
    expect($data['synonyms'])->toContain('Amorphophallus campanulatus Decne.');
    expect($data['synonyms'])->toContain('Amorphophallus chatty Andrews');
    expect($data['synonyms'])->toContain('Arum campanulatum Roxb.');
});

it('parses the family', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['family'])->toBe('Araceae');
});

it('parses image URLs with absolute paths', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['image_urls'])->toBeArray();
    expect($data['image_urls'])->toHaveCount(2);
    expect($data['image_urls'][0])->toStartWith('https://species.phakhaolao.la/');
});

it('parses map URLs', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['map_urls'])->toBeArray();
    expect($data['map_urls'])->toContain('https://species.phakhaolao.la/maps/topographic_la.jpg');
    expect($data['map_urls'])->toContain('https://species.phakhaolao.la/maps/landscapes_la.jpg');
});

it('parses use types as an array', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['use_types'])->toBeArray();
    expect($data['use_types'])->toContain('ອາຫານ');
    expect($data['use_types'])->toContain('ພືດເປັນຢາ');
});

it('parses habitat types as an array', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['habitat_types'])->toBeArray();
    expect($data['habitat_types'])->toContain('ປ່າດົງດິບ');
});

it('parses the IUCN status', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['iucn_status'])->toBe('ມີຄວາມສ່ຽງໜ້ອຍສຸດ');
});

it('parses the native status', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['native_status'])->toBe('ພື້ນເມືອງ');
});

it('parses the invasiveness', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['invasiveness'])->toBe('ບໍ່ຮຸກຮານ');
});

it('parses the botanical description', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['botanical_description'])->toContain('botanical description');
});

it('parses the global distribution', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['global_distribution'])->toContain('Southeast Asia');
});

it('parses the harvest season as comma-separated string', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['harvest_season'])->toContain('ມັງກອນ');
    expect($data['harvest_season'])->toContain('ກຸມພາ');
});

it('parses the nutrition table', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['nutrition'])->toBeArray();
    expect($data['nutrition'])->toHaveCount(3);
    expect($data['nutrition'][0]['nutrient'])->toBe('ໂປຣຕີນ');
    expect($data['nutrition'][0]['value_per_100g'])->toBe('1.2');
});

it('parses the cultivation info', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['cultivation_info'])->toBe('ຊະນິດທຳມະຊາດ');
});

it('parses the market data', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['market_data'])->toContain('Market data text');
});

it('parses the management info', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['management_info'])->toContain('Management information');
});

it('parses references', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['references'])->toBeArray();
    expect($data['references'])->not->toBeEmpty();
});

it('sets the source_id', function () {
    $data = $this->scraper->parseDetailPage($this->detailHtml, 56);

    expect($data['source_id'])->toBe(56);
});
