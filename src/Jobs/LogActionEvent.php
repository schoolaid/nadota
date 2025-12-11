<?php

namespace SchoolAid\Nadota\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SchoolAid\Nadota\Events\ActionLogged;
use SchoolAid\Nadota\Models\ActionEvent;

class LogActionEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The action event data.
     */
    protected array $data;

    /**
     * The action name.
     */
    protected string $action;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data, string $action)
    {
        $this->data = $data;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $actionEvent = ActionEvent::query()->create($this->data);

            // Dispatch the event for listeners
            if (config('nadota.action_events.dispatch_events', true)) {
                event(new ActionLogged($actionEvent, $this->action));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to log action event in queue', [
                'action' => $this->action,
                'error' => $e->getMessage(),
                'data' => $this->data,
            ]);

            // Create a failed event record
            ActionEvent::query()->create([
                'batch_id' => $this->data['batch_id'] ?? null,
                'user_id' => $this->data['user_id'] ?? null,
                'name' => $this->action,
                'actionable_type' => $this->data['actionable_type'] ?? '',
                'actionable_id' => $this->data['actionable_id'] ?? 0,
                'target_type' => $this->data['target_type'] ?? '',
                'target_id' => $this->data['target_id'] ?? 0,
                'model_type' => $this->data['model_type'] ?? '',
                'model_id' => $this->data['model_id'] ?? null,
                'fields' => [],
                'status' => 'failed',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'nadota',
            'action-event',
            'action:' . $this->action,
        ];
    }
}
