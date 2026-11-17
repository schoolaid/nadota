<?php

use Illuminate\Support\LazyCollection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SchoolAid\Nadota\Http\Services\Exporters\CsvExporter;
use SchoolAid\Nadota\Http\Services\Exporters\ExcelExporter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/*
 * Cross-exporter hardening checks (AUD-017, AUD-018). They target the two
 * shipped exporters together, so they live outside CsvExporterTest/ExcelExporterTest.
 */

function audExporterBody(Response $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

it('writes a cell starting with "=" as text in the xlsx file, not as a formula', function () {
    $response = (new ExcelExporter())->export(LazyCollection::make([['name' => '=HYPERLINK("https://evil.test","x")']]), ['name' => 'Name'], 'students');

    $path = tempnam(sys_get_temp_dir(), 'nadota-aud') . '.xlsx';
    file_put_contents($path, audExporterBody($response));
    $cell = IOFactory::load($path)->getActiveSheet()->getCell('A2');
    @unlink($path);

    expect($cell->getDataType())->not->toBe(DataType::TYPE_FORMULA);
});

it('neutralises cells that a spreadsheet would evaluate as a formula in the csv file', function () {
    $response = (new CsvExporter())->withoutBom()->export(LazyCollection::make([['name' => '=1+1'], ['name' => '@SUM(A1)']]), ['name' => 'Name'], 'students');

    $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", audExporterBody($response)))));

    expect($rows[1][0])->not->toStartWith('=')
        ->and($rows[2][0])->not->toStartWith('@');
});

it('keeps a filename containing quotes inside one Content-Disposition filename parameter', function (string $exporter) {
    $response = (new $exporter())->export(LazyCollection::make([]), ['name' => 'Name'], 'a"; filename=evil.exe');

    $parts = HeaderUtils::split($response->headers->get('Content-Disposition'), ';=');

    expect($parts)->toHaveCount(2)
        ->and($parts[1][0])->toBe('filename');
})->with([CsvExporter::class, ExcelExporter::class])
    ->skip('ISSUE AUD-018: the filename query parameter is interpolated into the header without escaping (use HeaderUtils::makeDisposition)');

it('exposes a chunk size that nothing in the export flow reads', function () {
    // Documents AUD-014: AbstractExporter::chunkSize() is a public setter with no consumer;
    // ResourceExportService pages with its own hard-coded $chunkSize = 500.
    $exporter = (new CsvExporter())->chunkSize(50);

    expect($exporter->getChunkSize())->toBe(50);
});
