<?php

use Illuminate\Routing\Route;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

function authorizesResourcesRequest(string $resourceKey): NadotaRequest
{
    $request = NadotaRequest::create("/nadota-api/{$resourceKey}/resource");
    $route = (new Route('GET', 'nadota-api/{resourceKey}/resource', fn () => null))->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function authorizesResourcesStatus(callable $call): ?int
{
    try {
        $call();

        return null;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

beforeEach(function () {
    InteractsWithNadotaApi::resetResourceManager();
    ResourceManager::registerResourceClass(StudentResource::class);

    // Records every check and answers from $this->allowed.
    $this->checks = [];
    $this->allowed = true;
    $test = $this;
    $this->app->bind(ResourceAuthorizationInterface::class, fn () => new class($test) implements ResourceAuthorizationInterface {
        private $model;

        public function __construct(private $test)
        {
        }

        public function setModel($model): self
        {
            $this->model = $model;

            return $this;
        }

        public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
        {
            $this->test->checks[] = [$action, $this->model];

            return $this->test->allowed;
        }
    });
});

afterEach(fn () => InteractsWithNadotaApi::resetResourceManager());

describe('validateResource()', function () {
    it('passes for a registered resource key', function () {
        expect(authorizesResourcesStatus(fn () => authorizesResourcesRequest('student')->validateResource()))->toBeNull();
    });

    it('answers 404 for an unknown resource key', function () {
        expect(authorizesResourcesStatus(fn () => authorizesResourcesRequest('nope')->validateResource()))->toBe(404);
    });
});

describe('authorized()', function () {
    it('checks the action against the resource model class when no record is given', function () {
        authorizesResourcesRequest('student')->authorized('viewAny');

        expect($this->checks)->toBe([['viewAny', Student::class]]);
    });

    it('checks the action against the given record', function () {
        $student = new Student(['name' => 'Ana']);

        authorizesResourcesRequest('student')->authorized('update', $student);

        expect($this->checks[0][0])->toBe('update')
            ->and($this->checks[0][1])->toBe($student);
    });

    it('answers 403 when the authorization service denies', function () {
        $this->allowed = false;

        expect(authorizesResourcesStatus(fn () => authorizesResourcesRequest('student')->authorized('delete')))->toBe(403);
    });

    it('resolves the resource from the route first', function () {
        $request = authorizesResourcesRequest('student');

        $request->authorized('viewAny');

        expect($request->getResource())->toBeInstanceOf(StudentResource::class);
    });

    it('authorizes against a manually set resource', function () {
        $request = new NadotaRequest();
        $request->setResource(new StudentResource());

        $request->authorized('create');

        expect($this->checks)->toBe([['create', Student::class]]);
    });
});
