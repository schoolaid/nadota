<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicy;
use SchoolAid\Nadota\Tests\Fixtures\Policies\OptionsAwareStudentPolicyWithOptions;
use SchoolAid\Nadota\Tests\Fixtures\Resources\EnrollmentFormResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\ScopedStudentResource;

/**
 * GET /{resourceKey}/resource/export/config
 * GET /{resourceKey}/resource/export
 *
 * The exporters and ResourceExportable have unit tests (tests/Unit/Export/*);
 * these drive the real routes, parse the streamed CSV/XLSX and check that the
 * export follows the index pipeline (search, filters, sorting, getQuery()).
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi(array_merge($this->fixtureResources, [
        ScopedStudentResource::class,
        EnrollmentFormResource::class,
    ]));
});

/** @return array<int, array<int, string>> rows of the CSV body, header first, BOM stripped */
function exportControllerCsvRows($response): array
{
    $body = $response->streamedContent();

    expect(str_starts_with($body, "\xEF\xBB\xBF"))->toBeTrue();

    $lines = array_filter(explode("\n", substr($body, 3)), fn ($line) => $line !== '');

    return array_values(array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines));
}

/** @return array<int, array<int, mixed>> rows of the first sheet of the XLSX body */
function exportControllerXlsxRows($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'nadota-export-');
    file_put_contents($path, $response->streamedContent());

    try {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $spreadsheet->disconnectWorksheets();

        return $rows;
    } finally {
        @unlink($path);
    }
}

describe('config', function () {
    it('describes the export of a resource with default settings', function () {
        $this->getJson($this->apiUrl('student', 'export/config'))
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.url', '/nadota-api/student/resource/export')
            ->assertJsonPath('data.formats', [
                ['format' => 'excel', 'extension' => 'xlsx'],
                ['format' => 'csv', 'extension' => 'csv'],
            ])
            ->assertJsonPath('data.syncLimit', 1000)
            ->assertJsonPath('data.defaultColumns', null)
            ->assertJsonPath('data.columns.0', ['key' => 'name', 'label' => 'Name', 'selected' => true]);

        expect(collect($this->getJson($this->apiUrl('student', 'export/config'))->json('data.columns'))->pluck('key')->all())
            ->toBe(['name', 'email', 'age', 'balance', 'birth_date', 'enrolled_at', 'is_active', 'status', 'grade_id', 'courses_count', 'notes_exists']);
    });

    it('reflects the resource formats, default columns, sync limit and exportable hidden fields', function () {
        $this->getJson($this->apiUrl('scoped-student', 'export/config'))
            ->assertOk()
            ->assertJsonPath('data.formats', [['format' => 'csv', 'extension' => 'csv']])
            ->assertJsonPath('data.syncLimit', 2)
            ->assertJsonPath('data.defaultColumns', ['name', 'email'])
            ->assertJsonPath('data.columns', [
                ['key' => 'name', 'label' => 'Name', 'selected' => true],
                ['key' => 'email', 'label' => 'Email', 'selected' => true],
                ['key' => 'age', 'label' => 'Age', 'selected' => false],
                ['key' => 'bio', 'label' => 'Bio', 'selected' => false],
            ]);
    });

    it('reports export as disabled when the resource disables it', function () {
        $this->getJson($this->apiUrl('enrollment-form', 'export/config'))
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    });

    it('reports export as disabled when the policy denies the export ability', function () {
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['export']]);

        $this->getJson($this->apiUrl('student', 'export/config'))
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    });

    it('reads the sync limit default from config nadota.export.sync_limit', function () {
        config(['nadota.export.sync_limit' => 250]);

        $this->getJson($this->apiUrl('student', 'export/config'))->assertJsonPath('data.syncLimit', 250);
    })->skip('ISSUE OPS-019: nadota.export.sync_limit (and enabled, chunk_size) are never read; the resource hard-codes 1000');
});

describe('csv', function () {
    beforeEach(function () {
        $first = Grade::create(['name' => 'First']);
        $this->ana = Student::create(['name' => 'Ana', 'email' => 'ana@school.test', 'age' => 12, 'status' => 'enrolled', 'is_active' => true, 'grade_id' => $first->id, 'password' => 'hash-ana']);
        $this->ben = Student::create(['name' => 'Ben', 'age' => 14, 'status' => 'draft']);
        $this->ana->courses()->attach(Course::create(['name' => 'Math'])->id);
        Note::create(['body' => 'n', 'notable_type' => Student::class, 'notable_id' => $this->ben->id]);
    });

    it('streams a CSV download with the documented headers and default filename', function () {
        Carbon::setTestNow('2026-11-02 09:30:15');

        $response = $this->get($this->apiUrl('student', 'export?format=csv'))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
            ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="student_2026-11-02_093015.csv"')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');

        Carbon::setTestNow();
    });

    it('writes a header row and one row per record, formatted for export', function () {
        $rows = exportControllerCsvRows($this->get($this->apiUrl('student', 'export?format=csv'))->assertOk());

        expect($rows)->toBe([
            ['Name', 'Email', 'Age', 'Balance', 'Birth Date', 'Enrolled At', 'Active', 'Status', 'Grade', 'Courses Count', 'Has Notes'],
            ['Ana', 'ana@school.test', '12', '', '', '', 'Yes', 'Enrolled', 'First', '1', 'No'],
            ['Ben', '', '14', '', '', '', 'No', 'Draft', '', '0', 'Yes'],
        ]);
    });

    it('never exports the password, even when asked for it', function () {
        $response = $this->get($this->apiUrl('student', 'export?format=csv&columns[]=name&columns[]=password'))->assertOk();
        $rows = exportControllerCsvRows($response);

        expect($rows[0])->toBe(['Name'])
            ->and($response->streamedContent())->not->toContain('hash-ana');
    });

    it('exports only the requested columns, in field order', function () {
        $rows = exportControllerCsvRows($this->get($this->apiUrl('student', 'export?format=csv&columns[]=status&columns[]=name'))->assertOk());

        expect($rows)->toBe([['Name', 'Status'], ['Ana', 'Enrolled'], ['Ben', 'Draft']]);
    });

    it('uses the requested filename', function () {
        $response = $this->get($this->apiUrl('student', 'export?format=csv&filename=my-students'))->assertOk();

        expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="my-students.csv"');
    });

    it('keeps a quote in the requested filename from breaking the Content-Disposition header', function () {
        $response = $this->get($this->apiUrl('student', 'export?format=csv&filename=' . urlencode('a"; filename*=UTF-8\'\'evil.html')))->assertOk();

        expect($response->headers->get('Content-Disposition'))->not->toContain('filename*=');
    })->skip('ISSUE OPS-020: the filename query value is pasted unescaped into Content-Disposition');

    it('follows the search, the filters and the sorting of the index', function (string $query, array $names) {
        Student::create(['name' => 'Carla', 'age' => 13, 'status' => 'enrolled']);

        $rows = exportControllerCsvRows($this->get($this->apiUrl('student', "export?format=csv&columns[]=name&{$query}"))->assertOk());

        expect(array_column(array_slice($rows, 1), 0))->toBe($names);
    })->with([
        'default sort' => ['', ['Ana', 'Ben', 'Carla']],
        'search (globalSearch key)' => ['globalSearch=ar', ['Carla']],
        'search on a relation' => ['globalSearch=first', ['Ana']],
        'filter' => ['filters[status]=enrolled', ['Ana', 'Carla']],
        'sorting' => ['sortField=age&sortDirection=desc', ['Ben', 'Carla', 'Ana']],
    ]);

    it('leaves out soft-deleted records', function () {
        $this->ben->delete();

        $rows = exportControllerCsvRows($this->get($this->apiUrl('student', 'export?format=csv&columns[]=name'))->assertOk());

        expect(array_column(array_slice($rows, 1), 0))->toBe(['Ana']);
    });

    it('goes through the resource getQuery(), so hidden records are not exported', function () {
        $this->ben->update(['status' => 'archived']);

        $rows = exportControllerCsvRows($this->get($this->apiUrl('scoped-student', 'export?format=csv&columns[]=name'))->assertOk());

        expect(array_column(array_slice($rows, 1), 0))->toBe(['Ana']);
    });

    it('fills the value of a field exported although hidden from index', function () {
        $this->ana->update(['bio' => 'Likes chess']);

        $rows = exportControllerCsvRows($this->get($this->apiUrl('scoped-student', 'export?format=csv&columns[]=name&columns[]=bio'))->assertOk());

        expect($rows[1])->toBe(['Ana', 'Likes chess']);
    })->skip('ISSUE OPS-018: the export selects only the index columns, so an exportable() field hidden from index is always empty');

    it('exports past the sync limit, which is informational only', function () {
        Student::create(['name' => 'Carla']);

        $rows = exportControllerCsvRows($this->get($this->apiUrl('scoped-student', 'export?format=csv&columns[]=name'))->assertOk());

        expect($rows)->toHaveCount(4);
    });

    it('neutralizes values that a spreadsheet would run as a formula', function () {
        $this->ben->update(['name' => '=HYPERLINK("http://evil.test","x")']);

        $rows = exportControllerCsvRows($this->get($this->apiUrl('student', 'export?format=csv&columns[]=name'))->assertOk());

        $names = array_column(array_slice($rows, 1), 0);

        expect($names)->toContain('Ana')
            ->and(array_filter($names, fn ($name) => str_starts_with($name, '=')))->toBe([]);
    });
});

describe('excel', function () {
    it('streams an xlsx with the header row and the records', function () {
        Student::create(['name' => 'Ana', 'status' => 'enrolled']);

        $response = $this->get($this->apiUrl('student', 'export?format=excel&columns[]=name&columns[]=status&filename=roster'))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="roster.xlsx"')
            ->and(exportControllerXlsxRows($response))->toBe([['Name', 'Status'], ['Ana', 'Enrolled']]);
    });

    it('uses excel when no format is given', function () {
        $response = $this->get($this->apiUrl('student', 'export?filename=roster'))->assertOk();

        expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="roster.xlsx"');
    });

    it('does not turn a cell into a live formula', function () {
        Student::create(['name' => '=1+1']);

        $path = tempnam(sys_get_temp_dir(), 'nadota-export-');
        file_put_contents($path, $this->get($this->apiUrl('student', 'export?format=excel&columns[]=name'))->streamedContent());

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();

            expect($sheet->getCell('A2')->isFormula())->toBeFalse();
        } finally {
            @unlink($path);
        }
    });
});

describe('refusals', function () {
    it('answers 422 for a format that is not allowed', function (string $resource, string $format, string $allowed) {
        $this->getJson($this->apiUrl($resource, "export?format={$format}"))
            ->assertStatus(422)
            ->assertJsonPath('message', "Export format '{$format}' is not allowed. Allowed formats: {$allowed}");
    })->with([
        'unknown' => ['student', 'pdf', 'excel, csv'],
        'not allowed on the resource' => ['scoped-student', 'excel', 'csv'],
    ]);

    it('answers 403 when the resource disables export', function () {
        $this->getJson($this->apiUrl('enrollment-form', 'export?format=csv'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Export is not enabled for this resource.');
    });

    it('answers 403 when export is disabled globally in config', function () {
        config(['nadota.export.enabled' => false]);

        $this->getJson($this->apiUrl('student', 'export?format=csv'))->assertForbidden();
    })->skip('ISSUE OPS-019: nadota.export.enabled is never read');

    it('answers 403 on both routes when viewAny is denied', function (string $path) {
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicy::class);
        config(['nadota-tests.ops_denied' => ['viewAny']]);

        $this->getJson($this->apiUrl('student', $path))->assertForbidden();
    })->with(['export?format=csv', 'export/config']);

    it('answers 403 when the policy denies the export ability', function () {
        $this->useRealAuthorization();
        Gate::policy(Student::class, OptionsAwareStudentPolicyWithOptions::class);
        config(['nadota-tests.ops_denied' => ['export']]);

        $this->getJson($this->apiUrl('student', 'export?format=csv'))->assertForbidden();
    });

    it('answers 422, not 500, when columns is not a list', function () {
        $this->getJson($this->apiUrl('student', 'export?format=csv&columns=name'))->assertStatus(422);
    })->skip('ISSUE OPS-016: a scalar columns value reaches ExportRequestDTO(?array $columns) and throws a TypeError (500)');
});
