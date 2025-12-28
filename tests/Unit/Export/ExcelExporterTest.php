<?php

use Illuminate\Support\LazyCollection;
use SchoolAid\Nadota\Http\Services\Exporters\ExcelExporter;

it('exports data to excel format', function () {
    $exporter = new ExcelExporter();

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
        ->and($response->headers->get('Content-Type'))->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('test_export.xlsx');
});

it('returns correct content type', function () {
    $exporter = new ExcelExporter();

    expect($exporter->getContentType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('returns correct extension', function () {
    $exporter = new ExcelExporter();

    expect($exporter->getExtension())->toBe('xlsx');
});

it('can disable auto-size', function () {
    $exporter = new ExcelExporter();
    $result = $exporter->withoutAutoSize();

    expect($result)->toBeInstanceOf(ExcelExporter::class);
});

it('can disable freeze header', function () {
    $exporter = new ExcelExporter();
    $result = $exporter->withoutFreezeHeader();

    expect($result)->toBeInstanceOf(ExcelExporter::class);
});

it('can disable bold headers', function () {
    $exporter = new ExcelExporter();
    $result = $exporter->withoutBoldHeaders();

    expect($result)->toBeInstanceOf(ExcelExporter::class);
});
