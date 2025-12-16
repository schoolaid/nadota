<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Models\ActionEvent;

class ActionEventController extends Controller
{
    /**
     * Get action events for a specific model instance.
     * Returns a clean, lightweight response for frontend consumption.
     */
    public function index(NadotaRequest $request, string $resourceKey, int $id): JsonResponse
    {
        $resource = $request->getResource();
        $modelClass = $resource->model;

        $perPage = min($request->input('per_page', 15), 100);
        $page = $request->input('page', 1);

        $query = ActionEvent::query()
            ->where('model_type', $modelClass)
            ->where('model_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Optional filters
        if ($request->filled('name')) {
            $query->where('name', $request->input('name'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $actionEvents = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($actionEvents->items())->map(fn ($event) => $this->formatEvent($event)),
            'meta' => [
                'current_page' => $actionEvents->currentPage(),
                'last_page' => $actionEvents->lastPage(),
                'per_page' => $actionEvents->perPage(),
                'total' => $actionEvents->total(),
            ],
        ]);
    }

    /**
     * Format a single action event for the response.
     */
    protected function formatEvent(ActionEvent $event): array
    {
        return [
            'id' => $event->id,
            'batchId' => $event->batch_id,
            'name' => $event->name,
            'nameLabel' => $this->getActionLabel($event->name),
            'status' => $event->status,
            'user' => $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ] : null,
            'modelType' => $event->model_type,
            'modelId' => $event->model_id,
            'fields' => $event->fields,
            'original' => $event->original,
            'changes' => $event->changes,
            'exception' => $event->exception,
            'createdAt' => $event->created_at?->toDateTimeString(),
            'updatedAt' => $event->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get a human-readable label for the action name.
     */
    protected function getActionLabel(string $name): string
    {
        return match ($name) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'restore' => 'Restored',
            'forceDelete' => 'Permanently Deleted',
            default => ucfirst($name),
        };
    }
}
