<?php

namespace SchoolAid\Nadota\Http\Actions;

abstract class DestructiveAction extends Action
{
    /**
     * Indicates if this is a destructive action.
     */
    protected bool $destructive = true;

    /**
     * The text for the action confirmation button.
     */
    protected string $confirmButtonText = 'Delete';

    /**
     * The confirmation text for the action.
     */
    protected ?string $confirmText = 'Are you sure you want to run this action?';
}
