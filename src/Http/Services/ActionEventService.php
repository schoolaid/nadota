<?php

namespace SchoolAid\Nadota\Http\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Models\ActionEvent;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Contracts\ResourceInterface;

class ActionEventService
{
    protected ?string $batchId = null;

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
     * Log a create action
     */
    public function logCreate(
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request,
        array $fields = []
    ): ActionEvent {
        return $this->log(
            action: 'create',
            model: $model,
            resource: $resource,
            request: $request,
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
        array $originalData = null
    ): ActionEvent {
        $original = $originalData ?? $model->getOriginal();
        $changes = $model->getChanges();

        return $this->log(
            action: 'update',
            model: $model,
            resource: $resource,
            request: $request,
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
        return $this->log(
            action: 'delete',
            model: $model,
            resource: $resource,
            request: $request,
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
        return $this->log(
            action: 'restore',
            model: $model,
            resource: $resource,
            request: $request
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
        return $this->log(
            action: $action,
            model: $model,
            resource: $resource,
            request: $request,
            fields: $fields,
            original: $metadata['original'] ?? null,
            changes: $metadata['changes'] ?? null
        );
    }

    /**
     * Core logging method
     */
    protected function log(
        string $action,
        Model $model,
        ResourceInterface $resource,
        NadotaRequest $request,
        array $fields = [],
        array $original = null,
        array $changes = null
    ): ActionEvent {
        try {
            $actionEvent = ActionEvent::query()->create([
                'batch_id' => $this->getBatchId(),
                'user_id' => Auth::id() ?? 0,
                'name' => $action,
                'actionable_type' => get_class($resource),
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
            ]);

            return $actionEvent;
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            \Log::error('Failed to log action event', [
                'action' => $action,
                'model' => get_class($model),
                'error' => $e->getMessage()
            ]);

            // Create a failed event record
            return ActionEvent::query()->create([
                'batch_id' => $this->getBatchId(),
                'user_id' => Auth::id() ?? 0,
                'name' => $action,
                'actionable_type' => get_class($resource),
                'actionable_id' => 0,
                'target_type' => get_class($model),
                'target_id' => $model->getKey() ?? 0,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'fields' => [],
                'status' => 'failed',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitize fields to remove sensitive data
     */
    protected function sanitizeFields(array $fields): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'private_key'];

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
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'private_key', 'remember_token'];

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