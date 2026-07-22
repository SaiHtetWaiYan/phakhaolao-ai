<?php

it('returns a formatted workbook for a table', function () {
    $response = $this->post('/chat/export-table', [
        'rows' => [
            ['Fern species', 'Common name', 'Ailments'],
            ['Adiantum stenochlamys Baker', 'Back Adiantum / ຜັກກູດຜັກຊີດຳ', 'Cough from asthma; jaundice'],
            ['Diplazium esculentum', 'Fiddlehead fern', 'Postpartum tonic'],
        ],
        'title' => 'Ferns',
    ]);

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $bytes = $response->streamedContent();

    // xlsx is a zip archive; "PK" is its signature.
    expect(substr($bytes, 0, 2))->toBe('PK')
        ->and(strlen($bytes))->toBeGreaterThan(2000);
});

it('rejects a request with no rows', function () {
    $this->postJson('/chat/export-table', ['rows' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rows');
});

it('rejects an absurdly large table', function () {
    $this->postJson('/chat/export-table', [
        'rows' => array_fill(0, 501, ['a', 'b']),
    ])->assertUnprocessable()->assertJsonValidationErrors('rows');
});
