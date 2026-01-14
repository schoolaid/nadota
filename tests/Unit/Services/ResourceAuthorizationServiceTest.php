<?php

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceAuthorizationService;
use SchoolAid\Nadota\Tests\Models\TestModel;
use Illuminate\Foundation\Auth\User;

beforeEach(function () {
    $this->authService = new ResourceAuthorizationService();
    $this->user = new User();
    $this->user->id = 1;
    $this->user->email = 'test@example.com';

    // Create a mock request
    $this->request = Mockery::mock(NadotaRequest::class);
    $this->request->shouldReceive('user')->andReturn($this->user);
});

afterEach(function () {
    Mockery::close();
});

it('authorizes action when no policy exists', function () {
    $model = new TestModel();

    $this->authService->setModel($model);

    $result = $this->authService->authorizedTo($this->request, 'update');

    expect($result)->toBeTrue();
});

it('passes context to policy method when provided', function () {
    // Create a test policy
    $policy = new class {
        public function attach($user, $model, array $context = []): bool
        {
            // Verify context is passed correctly
            return isset($context['field']) && $context['field'] === 'grades';
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));
    Gate::before(fn() => null); // Don't auto-authorize

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with context
    $context = [
        'field' => 'grades',
        'items' => [1, 2, 3],
    ];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);

    expect($result)->toBeTrue();
});

it('tries field-specific method before generic method', function () {
    // Create a test policy with field-specific method
    $policy = new class {
        public $lastMethodCalled = null;

        public function attach($user, $model, array $context = []): bool
        {
            $this->lastMethodCalled = 'attach';
            return false; // Should not reach here
        }

        public function attachGrades($user, $model, array $context = []): bool
        {
            $this->lastMethodCalled = 'attachGrades';
            return true; // Field-specific method should be called first
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with field context
    $context = ['field' => 'grades'];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);

    expect($result)->toBeTrue();
});

it('falls back to generic method when field-specific does not exist', function () {
    // Create a test policy without field-specific method
    $policy = new class {
        public function attach($user, $model, array $context = []): bool
        {
            return true;
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with field context but no field-specific method
    $context = ['field' => 'students'];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);

    expect($result)->toBeTrue();
});

it('authorizes detach with field-specific method', function () {
    // Create a test policy with field-specific detach
    $policy = new class {
        public function detach($user, $model, array $context = []): bool
        {
            return false; // Generic should deny
        }

        public function detachTeachers($user, $model, array $context = []): bool
        {
            return true; // Field-specific should allow
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with teachers field
    $context = ['field' => 'teachers'];

    $result = $this->authService->authorizedTo($this->request, 'detach', $context);

    expect($result)->toBeTrue();
});

it('can validate items in context', function () {
    // Create a test policy that validates items
    $policy = new class {
        public function attachStudents($user, $model, array $context = []): bool
        {
            $items = $context['items'] ?? [];

            // Only allow attaching max 5 students
            return count($items) <= 5;
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with 3 items - should pass
    $context = [
        'field' => 'students',
        'items' => [1, 2, 3],
    ];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);
    expect($result)->toBeTrue();

    // Test with 6 items - should fail
    $context = [
        'field' => 'students',
        'items' => [1, 2, 3, 4, 5, 6],
    ];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);
    expect($result)->toBeFalse();
});

it('can access pivot data in context', function () {
    // Create a test policy that validates pivot data
    $policy = new class {
        public function attachGrades($user, $model, array $context = []): bool
        {
            $pivot = $context['pivot'] ?? [];

            // Verify pivot contains required data
            return isset($pivot['permission_text']);
        }
    };

    // Register the policy
    Gate::policy(TestModel::class, get_class($policy));

    $model = new TestModel();
    $this->authService->setModel($model);

    // Test with pivot data
    $context = [
        'field' => 'grades',
        'items' => [1, 2],
        'pivot' => [
            'permission_text' => 'Admin',
            'active' => true,
        ],
    ];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);
    expect($result)->toBeTrue();

    // Test without pivot data
    $context = [
        'field' => 'grades',
        'items' => [1, 2],
    ];

    $result = $this->authService->authorizedTo($this->request, 'attach', $context);
    expect($result)->toBeFalse();
});
