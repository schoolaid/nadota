<?php

use Illuminate\Support\LazyCollection;
use SchoolAid\Nadota\Http\Services\Exporters\CsvExporter;

it('exports data to csv format', function () {
    $exporter = new CsvExporter();

    $data = LazyCollection::make([
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ]);

    $headers = [
        'name' => 'Name',
        'email' => 'Email',
    ];

    $response = $exporter->export($data, $headers, 'test_export');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('test_export.csv');
});

it('returns correct content type', function () {
    $exporter = new CsvExporter();

    expect($exporter->getContentType())->toBe('text/csv; charset=UTF-8');
});

it('returns correct extension', function () {
    $exporter = new CsvExporter();

    expect($exporter->getExtension())->toBe('csv');
});

it('can set custom delimiter', function () {
    $exporter = new CsvExporter();
    $result = $exporter->delimiter(';');

    expect($result)->toBeInstanceOf(CsvExporter::class);
});

it('can set custom enclosure', function () {
    $exporter = new CsvExporter();
    $result = $exporter->enclosure("'");

    expect($result)->toBeInstanceOf(CsvExporter::class);
});

it('can disable BOM', function () {
    $exporter = new CsvExporter();
    $result = $exporter->withoutBom();

    expect($result)->toBeInstanceOf(CsvExporter::class);
});
