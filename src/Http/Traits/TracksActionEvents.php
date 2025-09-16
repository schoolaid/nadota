<?php

namespace SchoolAid\Nadota\Http\Traits;

use SchoolAid\Nadota\Http\Services\ActionEventService;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

trait TracksActionEvents
{
    protected ?ActionEventService $actionEventService = null;

    /**
     * Get the action event service instance
     */
    protected function getActionEventService(): ActionEventService
    {
        if (!$this->actionEventService) {
            $this->actionEventService = app(ActionEventService::class);
        }
        return $this->actionEventService;
    }

    /**
     * Track a create action
     */
    protected function trackCreate(Model $model, NadotaRequest $request, array $fields = []): void
    {
        if (!$this->shouldTrackActions()) {
            return;
        }

        $this->getActionEventService()->logCreate(
            $model,
            $request->getResource(),
            $request,
            $fields
        );
    }

    /**
     * Track an update action
     */
    protected function trackUpdate(Model $model, NadotaRequest $request, array $fields = [], array $originalData = null): void
    {
        if (!$this->shouldTrackActions()) {
            return;
        }

        $this->getActionEventService()->logUpdate(
            $model,
            $request->getResource(),
            $request,
            $fields,
            $originalData
        );
    }

    /**
     * Track a delete action
     */
    protected function trackDelete(Model $model, NadotaRequest $request): void
    {
        if (!$this->shouldTrackActions()) {
            return;
        }

        $this->getActionEventService()->logDelete(
            $model,
            $request->getResource(),
            $request
        );
    }

    /**
     * Track a restore action
     */
    protected function trackRestore(Model $model, NadotaRequest $request): void
    {
        if (!$this->shouldTrackActions()) {
            return;
        }

        $this->getActionEventService()->logRestore(
            $model,
            $request->getResource(),
            $request
        );
    }

    /**
     * Track a custom action
     */
    protected function trackCustomAction(
        string $action,
        Model $model,
        NadotaRequest $request,
        array $fields = [],
        array $metadata = []
    ): void {
        if (!$this->shouldTrackActions()) {
            return;
        }

        $this->getActionEventService()->logAction(
            $action,
            $model,
            $request->getResource(),
            $request,
            $fields,
            $metadata
        );
    }

    /**
     * Determine if actions should be tracked
     * Can be overridden in individual services
     */
    protected function shouldTrackActions(): bool
    {
        return config('nadota.track_actions', true);
    }
}