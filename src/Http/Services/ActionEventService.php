<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Events\ActionLogged;
use SchoolAid\Nadota\Jobs\LogActionEvent;

class ActionEventService
{
    protected ?string $batchId = null;

    /**
     * Cached sensitive keys from config
     */
    protected ?array $sensitiveKeys = null;

    /**
     * Get or create a batch ID for grouping related actions
     */
    public function getBatchId(): string
    {
        if (!$this->batchId) {
            $this->batchId = (string) Str::uuid();
        }
        return $this->batchId;
    }

    /**
     * Reset the batch ID
     */
    public function resetBatchId(): void
    {
        $this->batchId = null;
    }

    /**
     * Resolve the user ID for the action event
     * Returns null if no user is authenticated (for system actions, registrations, etc.)
     */
    protected function resolveUserId(): ?int
    {
        // First check if there's an authenticated user
        $userId = Auth::id();

        if ($userId !== null) {
            return $userId;
        }

        // Return configured system user ID or null
        return config('nadota.action_events.system_user_id', null);
    }

    /**
     * Log a create action
     */
    public function logCreate(
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request,
        array $fields = []
    ): ActionEvent {
        return $this->persist(
            action: 'create',
            model: $model,
            actionableType: get_class($resource),
            fields: $fields,
            changes: $model->getAttributes()
        );
    }

    /**
     * Log an update action
     */
    public function logUpdate(
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request,
        array $fields = [],
        ?array $originalData = null
    ): ActionEvent {
        $original = $originalData ?? $model->getOriginal();
        $changes = $model->getChanges();

        return $this->persist(
            action: 'update',
            model: $model,
            actionableType: get_class($resource),
            fields: $fields,
            original: $original,
            changes: $changes
        );
    }

    /**
     * Log a delete action
     */
    public function logDelete(
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request
    ): ActionEvent {
        return $this->persist(
            action: 'delete',
            model: $model,
            actionableType: get_class($resource),
            original: $model->getAttributes()
        );
    }

    /**
     * Log a restore action (for soft deletes)
     */
    public function logRestore(
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request
    ): ActionEvent {
        return $this->persist(
            action: 'restore',
            model: $model,
            actionableType: get_class($resource)
        );
    }

    /**
     * Log a custom action
     */
    public function logAction(
        string $action,
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request,
        array $fields = [],
        array $metadata = []
    ): ActionEvent {
        return $this->persist(
            action: $action,
            model: $model,
            actionableType: get_class($resource),
            fields: $fields,
            original: $metadata['original'] ?? null,
            changes: $metadata['changes'] ?? null
        );
    }

    /**
     * Record an action event from any context (jobs, console commands, API,
     * controllers outside the Nadota panel).
     *
     * Context-free public entry point: requires only the affected model.
     * The user is resolved via Auth::id() or the configured system_user_id,
     * so it works without an HTTP request.
     *
     * @param string      $action          Action name (e.g. 'update', 'import', 'sync:roster')
     * @param Model       $model           The affected model
     * @param array|null  $changes         Values after the change
     * @param array|null  $original        Values before the change
     * @param array       $fields          Arbitrary context payload (sanitized)
     * @param string|null $actionableType  Origin identifier; defaults to the model class
     */
    public function record(
        string $action,
        Model $model,
        ?array $changes = null,
        ?array $original = null,
        array $fields = [],
        ?string $actionableType = null
    ): ActionEvent {
        return $this->persist($action, $model, $actionableType, $fields, $original, $changes);
    }

    /**
     * Core persistence method. Context-free: no HTTP request or Nadota
     * resource required.
     */
    protected function persist(
        string $action,
        Model $model,
        ?string $actionableType = null,
        array $fields = [],
        ?array $original = null,
        ?array $changes = null
    ): ActionEvent {
        $data = [
            'batch_id' => $this->getBatchId(),
            'user_id' => $this->resolveUserId(),
            'name' => $action,
            'actionable_type' => $actionableType ?? get_class($model),
            'actionable_id' => 0, // Resource doesn't have ID, using 0
            'target_type' => get_class($model),
            'target_id' => $model->getKey() ?? 0,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'fields' => $this->sanitizeFields($fields),
            'status' => 'finished',
            'exception' => null,
            'original' => $original ? $this->sanitizeData($original) : null,
            'changes' => $changes ? $this->sanitizeData($changes) : null,
        ];

        // Check if we should use queue for async processing
        if ($this->shouldUseQueue()) {
            return $this->logAsync($data, $action);
        }

        return $this->logSync($data, $action);
    }

    /**
     * Log action synchronously
     */
    protected function logSync(array $data, string $action): ActionEvent
    {
        try {
            $actionEvent = ActionEvent::query()->create($data);

            // Dispatch event for listeners
            $this->dispatchEvent($actionEvent, $action);

            return $actionEvent;
        } catch (\Exception $e) {
            \Log::error('Failed to log action event', [
                'action' => $action,
                'model' => $data['model_type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            try {
                return ActionEvent::query()->create([
                    'batch_id' => $data['batch_id'],
                    'user_id' => $data['user_id'],
                    'name' => $action,
                    'actionable_type' => $data['actionable_type'],
                    'actionable_id' => 0,
                    'target_type' => $data['target_type'],
                    'target_id' => $data['target_id'] ?? 0,
                    'model_type' => $data['model_type'],
                    'model_id' => $data['model_id'],
                    'fields' => [],
                    'status' => 'failed',
                    'exception' => $e->getMessage(),
                ]);
            } catch (\Exception $inner) {
                \Log::error('Failed to create failed action event record', ['error' => $inner->getMessage()]);

                return new ActionEvent(['status' => 'failed', 'name' => $action]);
            }
        }
    }

    /**
     * Log action asynchronously via queue
     */
    protected function logAsync(array $data, string $action): ActionEvent
    {
        // Dispatch to queue
        $queue = config('nadota.action_events.queue_name', 'default');
        LogActionEvent::dispatch($data, $action)->onQueue($queue);

        // Return a temporary ActionEvent instance (not persisted yet)
        $actionEvent = new ActionEvent($data);
        $actionEvent->status = 'running';

        return $actionEvent;
    }

    /**
     * Dispatch the ActionLogged event
     */
    protected function dispatchEvent(ActionEvent $actionEvent, string $action): void
    {
        if (config('nadota.action_events.dispatch_events', true)) {
            event(new ActionLogged($actionEvent, $action));
        }
    }

    /**
     * Check if queue should be used for logging
     */
    protected function shouldUseQueue(): bool
    {
        return config('nadota.action_events.queue', false);
    }

    /**
     * Get sensitive keys from config (cached)
     */
    protected function getSensitiveKeys(): array
    {
        if ($this->sensitiveKeys === null) {
            $this->sensitiveKeys = config('nadota.action_events.exclude_fields', [
                'password',
                'token',
                'secret',
                'api_key',
                'private_key',
                'remember_token',
                'api_token',
            ]);
        }

        return $this->sensitiveKeys;
    }

    /**
     * Sanitize fields to remove sensitive data
     */
    protected function sanitizeFields(array $fields): array
    {
        $sensitiveKeys = $this->getSensitiveKeys();

        foreach ($fields as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $fields[$key] = '***REDACTED***';
                }
            }
        }

        return $fields;
    }

    /**
     * Sanitize data array to remove sensitive information
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = $this->getSensitiveKeys();

        foreach ($data as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $data[$key] = '***REDACTED***';
                }
            }
        }

        return $data;
    }

    /**
     * Get action events for a specific model
     */
    public function getModelHistory(Model $model, int $limit = 50)
    {
        return ActionEvent::query()->where('model_type', get_class($model))
            ->where('model_id', $model->getKey())
            ->recent()
            ->limit($limit)
            ->get();
    }

    /**
     * Get action events for a specific user
     */
    public function getUserActivity(int $userId, int $limit = 50)
    {
        return ActionEvent::byUser($userId)
            ->recent()
            ->limit($limit)
            ->get();
    }

    /**
     * Get action events for a specific resource type
     */
    public function getResourceActivity(string $resourceClass, int $limit = 50)
    {
        return ActionEvent::query()->byActionableType($resourceClass)
            ->recent()
            ->limit($limit)
            ->get();
    }
}