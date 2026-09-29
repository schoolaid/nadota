<?php

namespace SchoolAid\Nadota\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        $model = $this->resolveAuthorizedModel($request, $id);

        $perPage = min($request->input('per_page', 15), 100);
        $page = $request->input('page', 1);

        $query = $this->eventsOf($model)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

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
     * Get a single action event for a specific model instance.
     */
    public function show(NadotaRequest $request, string $resourceKey, int $id, int $eventId): JsonResponse
    {
        $model = $this->resolveAuthorizedModel($request, $id);

        $event = $this->eventsOf($model)
            ->with('user')
            ->findOrFail($eventId);

        return response()->json(['data' => $this->formatEvent($event)]);
    }

    /**
     * Resolve the record through the resource's own query and require `view` on it,
     * exactly like the show endpoint does. The audit trail carries attribute snapshots
     * of the record, so it must not be reachable by anyone who cannot open the record.
     */
    protected function resolveAuthorizedModel(NadotaRequest $request, int $id): Model
    {
        $model = $request->getResource()->getQuery($request)->findOrFail($id);

        $request->authorized('view', $model);

        return $model;
    }

    /**
     * Events of a record, whoever wrote them. Host apps (and Nova before) store
     * `getMorphClass()`, which is the alias for a model in a morph map, while this
     * package stores the class name: both spellings belong to the same record.
     *
     * The id is compared as a string on purpose. Apps that widened `model_id` to a
     * string column only get an index lookup that way; an integer column converts
     * the constant once and is unaffected.
     */
    protected function eventsOf(Model $model): Builder
    {
        return ActionEvent::query()
            ->whereIn('model_type', array_values(array_unique([$model::class, $model->getMorphClass()])))
            ->where('model_id', (string) $model->getKey());
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
