<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceAuthorizationService;
use SchoolAid\Nadota\Http\Services\ResourceExportService;
use SchoolAid\Nadota\Tests\Fixtures\Export\AuditExportStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Export\DenyExportPolicy;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

beforeEach(function () {
    FixtureSchema::create();
    Student::create(['name' => 'Ana', 'status' => 'enrolled', 'website' => 'https://ana.dev', 'email' => 'ana@example.com']);
    Student::create(['name' => 'Bob', 'status' => 'draft', 'website' => 'https://bob.dev', 'email' => 'bob@example.com']);
});

function audExportRequest(array $query = [], int $userId = 1): NadotaRequest
{
    $request = NadotaRequest::create('/nadota-api/audit-export-student/resource/export', 'GET', $query);
    $request->setResource(new AuditExportStudentResource());

    $user = new User();
    $user->id = $userId;
    $request->setUserResolver(fn () => $user);

    return $request;
}

/** @return array<int, array<int, string>> CSV rows, header first, BOM stripped */
function audExportCsv(Response $response): array
{
    ob_start();
    $response->sendContent();
    $body = ltrim(ob_get_clean(), "\xEF\xBB\xBF");

    return array_map('str_getcsv', array_filter(explode("\n", $body), fn ($line) => $line !== ''));
}

function audExportStatus(Closure $call): int
{
    try {
        return $call()->getStatusCode();
    } catch (HttpExceptionInterface $e) {
        return $e->getStatusCode();
    }
}

it('streams a CSV with the exportable headers and one row per record', function () {
    $response = app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv', 'sortField' => 'name', 'sortDirection' => 'asc']));
    $rows = audExportCsv($response);

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($rows[0])->toBe(['Name', 'Status', 'Website'])
        ->and(array_column(array_slice($rows, 1), 0))->toBe(['Ana', 'Bob'])
        ->and(array_column(array_slice($rows, 1), 1))->toBe(['Enrolled', 'Draft']); // Select exports its label
});

it('falls back to export.default_format when the request names no format', function () {
    config()->set('nadota.export.default_format', 'csv');

    $response = app(ResourceExportService::class)->handle(audExportRequest());

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
});

it('answers 422 for a format the resource does not allow', function () {
    expect(audExportStatus(fn () => app(ResourceExportService::class)->handle(audExportRequest(['format' => 'pdf']))))->toBe(422);
});

it('answers 403 when the resource disables export', function () {
    $request = audExportRequest(['format' => 'csv']);
    $request->setResource(new class extends AuditExportStudentResource {
        protected bool $exportEnabled = false;
    });

    expect(audExportStatus(fn () => app(ResourceExportService::class)->handle($request)))->toBe(403);
});

it('answers 403 without viewAny', function () {
    $this->app->instance(ResourceAuthorizationInterface::class, new class implements ResourceAuthorizationInterface {
        public function setModel($model): self
        {
            return $this;
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            return false;
        }
    });

    expect(audExportStatus(fn () => app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv']))))->toBe(403);
});

it('keeps only the requested columns and uses the requested filename', function () {
    $response = app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv', 'columns' => ['name'], 'filename' => 'students']));

    expect(audExportCsv($response)[0])->toBe(['Name'])
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="students.csv"');
});

it('applies the index search to the exported rows', function () {
    $rows = audExportCsv(app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv', 'globalSearch' => 'Bob'])));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Bob']);
});

it('refuses the export when the policy refuses the export ability', function () {
    $this->app->singleton(ResourceAuthorizationInterface::class, ResourceAuthorizationService::class);
    Gate::policy(Student::class, DenyExportPolicy::class);
    $request = audExportRequest(['format' => 'csv']);

    expect($request->getResource()->getExportConfig($request)['enabled'])->toBeFalse()
        ->and(audExportStatus(fn () => app(ResourceExportService::class)->handle($request)))->toBe(403);
})->skip('ISSUE AUD-013: /export/config reports enabled=false for the export ability, but /export only checks viewAny');

it('refuses every export while export.enabled is false', function () {
    config()->set('nadota.export.enabled', false);

    expect(audExportStatus(fn () => app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv']))))->toBe(403);
})->skip('ISSUE AUD-014: the global export.enabled switch is never read');

it('reads the sync limit and the chunk size from the export config', function () {
    config()->set('nadota.export.sync_limit', 5);
    config()->set('nadota.export.chunk_size', 1);

    DB::enableQueryLog();
    audExportCsv(app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv'])));
    $selects = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select'))->count();

    expect((new AuditExportStudentResource())->getSyncExportLimit())->toBe(5)
        ->and($selects)->toBeGreaterThanOrEqual(2); // one page per record with chunk_size = 1
})->skip('ISSUE AUD-014: syncExportLimit is hard-coded to 1000 and the service chunk size to 500; export.sync_limit/chunk_size are never read');

it('exports the value of a field hidden from index but marked exportable()', function () {
    $rows = audExportCsv(app(ResourceExportService::class)->handle(audExportRequest(['format' => 'csv', 'sortField' => 'name', 'sortDirection' => 'asc'])));

    expect($rows[0][2])->toBe('Website')
        ->and($rows[1][2])->toBe('https://ana.dev');
})->skip('ISSUE AUD-015: BuildQueryPipe selects only the index fields columns, so the exportable website column is exported empty');

it('exports the columns the index shows to the current user through request-aware callbacks', function () {
    $request = audExportRequest(['format' => 'csv'], AuditExportStudentResource::AUDITOR_ID);

    $indexKeys = $request->getResource()->fieldsForIndex($request)->map->key()->values()->all();
    $exportHeader = audExportCsv(app(ResourceExportService::class)->handle($request))[0];

    expect($indexKeys)->toContain('email')
        ->and($exportHeader)->toContain('Email');
})->skip('ISSUE AUD-016: getExportableFields() calls isShowOnIndex() without the request, so the callback sees null');
