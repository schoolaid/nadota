<?php

namespace SchoolAid\Nadota\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ActionEvent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'action_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'batch_id',
        'user_id',
        'name',
        'actionable_type',
        'actionable_id',
        'target_type',
        'target_id',
        'model_type',
        'model_id',
        'fields',
        'status',
        'exception',
        'original',
        'changes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fields' => 'array',
        'original' => 'array',
        'changes' => 'array',
        'user_id' => 'integer',
        'actionable_id' => 'integer',
        'target_id' => 'integer',
        'model_id' => 'integer',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->batch_id)) {
                $model->batch_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        $userClass = config('auth.providers.users.model', \App\Models\User::class);
        return $this->belongsTo($userClass, 'user_id');
    }

    /**
     * Get the actionable model (the resource/model being acted upon).
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo('actionable');
    }

    /**
     * Get the target model.
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    /**
     * Get the model that was affected by the action.
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Scope a query to only include actions for a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include actions with a specific status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include actions for a specific batch.
     */
    public function scopeByBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope a query to only include actions for a specific actionable type.
     */
    public function scopeByActionableType($query, $type)
    {
        return $query->where('actionable_type', $type);
    }

    /**
     * Scope a query to only include actions with a specific name.
     */
    public function scopeByActionName($query, $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Scope to get recent actions.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Mark the action as finished.
     */
    public function markAsFinished(): self
    {
        $this->update(['status' => 'finished']);
        return $this;
    }

    /**
     * Mark the action as failed with an exception message.
     */
    public function markAsFailed($exception): self
    {
        $this->update([
            'status' => 'failed',
            'exception' => $exception instanceof \Exception ? $exception->getMessage() : $exception,
        ]);
        return $this;
    }

    /**
     * Check if the action is running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the action is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * Check if the action failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the display name for the action.
     */
    public function getActionDisplayName(): string
    {
        return ucwords(str_replace('_', ' ', Str::snake($this->name)));
    }

    /**
     * Get only the changed fields between original and changes.
     */
    public function getChangedFields(): array
    {
        if (!$this->original || !$this->changes) {
            return [];
        }

        $changed = [];
        foreach ($this->changes as $key => $newValue) {
            if (!isset($this->original[$key]) || $this->original[$key] !== $newValue) {
                $changed[$key] = [
                    'old' => $this->original[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }
}