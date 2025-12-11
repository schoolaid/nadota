<?php

namespace SchoolAid\Nadota\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SchoolAid\Nadota\Models\ActionEvent;

class ActionLogged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The action event instance.
     */
    public ActionEvent $actionEvent;

    /**
     * The action name (create, update, delete, restore, forceDelete, etc.)
     */
    public string $action;

    /**
     * Create a new event instance.
     */
    public function __construct(ActionEvent $actionEvent, string $action)
    {
        $this->actionEvent = $actionEvent;
        $this->action = $action;
    }

    /**
     * Get the action event.
     */
    public function getActionEvent(): ActionEvent
    {
        return $this->actionEvent;
    }

    /**
     * Get the action name.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Check if the action is a create action.
     */
    public function isCreate(): bool
    {
        return $this->action === 'create';
    }

    /**
     * Check if the action is an update action.
     */
    public function isUpdate(): bool
    {
        return $this->action === 'update';
    }

    /**
     * Check if the action is a delete action.
     */
    public function isDelete(): bool
    {
        return $this->action === 'delete';
    }

    /**
     * Check if the action is a restore action.
     */
    public function isRestore(): bool
    {
        return $this->action === 'restore';
    }

    /**
     * Check if the action is a force delete action.
     */
    public function isForceDelete(): bool
    {
        return $this->action === 'forceDelete';
    }
}
