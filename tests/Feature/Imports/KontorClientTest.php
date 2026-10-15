<?php

use App\Imports\ImportRequestException;
use App\Imports\Kontor\KontorClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const KONTOR_URL = 'https://kontor.example.test/api/v1/kontor';

beforeEach(function () {
    Sleep::fake();
});

test('products are searched with the key in the header and the manufacturers as one string', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(kontorEnvelope([kontorRow()], 4386))]);

    $page = (new KontorClient(KONTOR_URL, 'key%41with%percent'))->articles(['084', '123'], 200, 200);

    Http::assertSent(fn (Request $request): bool => $request->url() === KONTOR_URL.'/search'
        && $request->header('x-api-key') === ['key%41with%percent']
        && $request['entity'] === 'products'
        && $request['paging'] === ['skip' => 200, 'take' => 200]
        && $request['filter'] === ['herstellerids' => '084,123']);

    expect($page->total)->toBe(4386)
        ->and($page->rowCount)->toBe(1)
        ->and($page->articles)->toHaveCount(1);
});

test('a product row is read into the platform terms', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(kontorEnvelope([
        kontorRow(),
        kontorRow(['Artnr' => 'gri2', 'Shoptitel' => '', 'Bez1' => 'Fallback name', 'Ws_aktiv' => false]),
    ]))]);

    [$first, $second] = (new KontorClient(KONTOR_URL, 'key'))->articles(['038'], 0, 200)->articles;

    expect($first->sku)->toBe('gri04040')
        ->and($first->name)->toBe('Steckfigur zwei Delphine')
        ->and($first->ean)->toBe('4048565040402')
        ->and($first->mpn)->toBe('04040')
        ->and($first->manufacturerId)->toBe('038')
        ->and($first->manufacturerName)->toBe('Grimms')
        ->and($first->shortDescription)->toBe('Diese kleine handbemalte Steckfigur.')
        ->and($first->longDescription)->toBe("Holz: Lindenholz handbemalt.\nMasse: Höhe ca.7cm.")
        ->and($first->isActive)->toBeTrue()
        ->and($first->imageFiles)->toBe(['gri04040.jpg', 'gri04040_2.JPG'])
        ->and($second->name)->toBe('Fallback name')
        ->and($second->isActive)->toBeFalse();
});

test('a row without an article number is dropped but still counted toward the next page', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(kontorEnvelope([kontorRow(), kontorRow(['Artnr' => ''])]))]);

    $page = (new KontorClient(KONTOR_URL, 'key'))->articles(['038'], 0, 200);

    expect($page->rowCount)->toBe(2)
        ->and($page->articles)->toHaveCount(1)
        ->and($page->unusableRows())->toBe(1);
});

test('an empty page is the end of the catalogue', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(kontorEnvelope([], 4386))]);

    expect((new KontorClient(KONTOR_URL, 'key'))->articles(['038'], 4400, 200)->isEmpty())->toBeTrue();
});

test('an answer that says it failed is an error even with a 200', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response([
        'success' => false,
        'message' => 'Ungültiger API-Schlüssel',
        'data' => null,
        'errorCode' => 'ERR-401-INVALID-API-KEY',
    ])]);

    (new KontorClient(KONTOR_URL, 'bad'))->manufacturers();
})->throws(ImportRequestException::class, 'Ungültiger API-Schlüssel (ERR-401-INVALID-API-KEY)');

test('a busy api is asked again', function () {
    Http::fake([KONTOR_URL.'/search' => Http::sequence()
        ->push(['success' => false], 429)
        ->push(kontorEnvelope([['Herstellerid' => '038', 'Hersteller' => 'Grimms']]))]);

    $manufacturers = (new KontorClient(KONTOR_URL, 'key'))->manufacturers();

    expect($manufacturers)->toHaveCount(1);
    Http::assertSentCount(2);
});

test('a rejected key is not asked again', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(['success' => false, 'message' => 'Nope'], 401)]);

    try {
        (new KontorClient(KONTOR_URL, 'key'))->manufacturers();
    } catch (ImportRequestException) {
        //
    }

    Http::assertSentCount(1);
});

test('manufacturer ids keep their leading zeros and come back sorted by name', function () {
    Http::fake([KONTOR_URL.'/search' => Http::response(kontorEnvelope([
        ['Herstellerid' => '084', 'Hersteller' => 'Plan Toys'],
        ['Herstellerid' => '038', 'Hersteller' => 'Grimms'],
        ['Herstellerid' => '038', 'Hersteller' => 'Grimms'],
    ]))]);

    $manufacturers = (new KontorClient(KONTOR_URL, 'key'))->manufacturers();

    expect(array_map(fn ($manufacturer) => $manufacturer->toArray(), $manufacturers))->toBe([
        ['id' => '038', 'name' => 'Grimms'],
        ['id' => '084', 'name' => 'Plan Toys'],
    ]);
    Http::assertSent(fn (Request $request): bool => $request->data() === ['entity' => 'manufacturer']);
});
