<?php

namespace SchoolAid\Nadota\Http\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Contracts\ActionInterface;
use SchoolAid\Nadota\Http\Helpers\Helpers;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

abstract class Action implements ActionInterface
{
    /**
     * The displayable name of the action.
     */
    protected ?string $name = null;

    /**
     * The text for the action confirmation button.
     */
    protected string $confirmButtonText = 'Run Action';

    /**
     * The text for the action cancel button.
     */
    protected string $cancelButtonText = 'Cancel';

    /**
     * The confirmation text for the action.
     */
    protected ?string $confirmText = null;

    /**
     * Indicates if the action should be shown on index.
     */
    protected bool $showOnIndex = true;

    /**
     * Indicates if the action should be shown on detail.
     */
    protected bool $showOnDetail = true;

    /**
     * Indicates if this is a destructive action.
     */
    protected bool $destructive = false;

    /**
     * Indicates if the action can run without any models selected.
     */
    protected bool $standalone = false;

    /**
     * Custom component name for the frontend.
     * When null, frontend should use its default action component.
     */
    protected ?string $component = null;

    /**
     * The callback used to authorize running the action.
     */
    protected ?\Closure $authCallback = null;

    /**
     * Get the unique key/identifier for this action.
     */
    public static function getKey(): string
    {
        return Helpers::toUri(static::class);
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->name ?? Str::headline(class_basename(static::class));
    }

    /**
     * Execute the action on the given models.
     *
     * @param Collection<int, Model> $models
     * @param NadotaRequest $request
     * @return mixed
     */
    abstract public function handle(Collection $models, NadotaRequest $request): mixed;

    /**
     * Get the fields available for this action.
     */
    public function fields(NadotaRequest $request): array
    {
        return [];
    }

    /**
     * Determine if this action is available for the given request.
     */
    public function authorizedToRun(NadotaRequest $request, Model $model): bool
    {
        if ($this->authCallback) {
            return call_user_func($this->authCallback, $request, $model);
        }

        return true;
    }

    /**
     * Set the callback used to authorize running the action.
     */
    public function canRun(\Closure $callback): static
    {
        $this->authCallback = $callback;

        return $this;
    }

    /**
     * Determine if this action should be available on the resource index.
     */
    public function showOnIndex(): bool
    {
        return $this->showOnIndex;
    }

    /**
     * Set whether the action should be shown on index.
     */
    public function onlyOnIndex(): static
    {
        $this->showOnIndex = true;
        $this->showOnDetail = false;

        return $this;
    }

    /**
     * Determine if this action should be available on the resource detail view.
     */
    public function showOnDetail(): bool
    {
        return $this->showOnDetail;
    }

    /**
     * Set whether the action should only be shown on detail.
     */
    public function onlyOnDetail(): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = true;

        return $this;
    }

    /**
     * Set the action to show on both index and detail.
     */
    public function showOnTableRow(): static
    {
        $this->showOnIndex = true;
        $this->showOnDetail = true;

        return $this;
    }

    /**
     * Determine if this action is a destructive action.
     */
    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * Mark the action as destructive.
     */
    public function destructive(bool $destructive = true): static
    {
        $this->destructive = $destructive;

        return $this;
    }

    /**
     * Determine if this action can run without models.
     */
    public function isStandalone(): bool
    {
        return $this->standalone;
    }

    /**
     * Mark the action as standalone (can run without models).
     */
    public function standalone(bool $standalone = true): static
    {
        $this->standalone = $standalone;

        return $this;
    }

    /**
     * Get the confirmation text for the action.
     */
    public function confirmText(): ?string
    {
        return $this->confirmText;
    }

    /**
     * Set the confirmation text for the action.
     */
    public function withConfirmation(string $text): static
    {
        $this->confirmText = $text;

        return $this;
    }

    /**
     * Get the confirm button text.
     */
    public function confirmButtonText(): string
    {
        return $this->confirmButtonText;
    }

    /**
     * Set the confirm button text.
     */
    public function setConfirmButtonText(string $text): static
    {
        $this->confirmButtonText = $text;

        return $this;
    }

    /**
     * Get the cancel button text.
     */
    public function cancelButtonText(): string
    {
        return $this->cancelButtonText;
    }

    /**
     * Set the cancel button text.
     */
    public function setCancelButtonText(string $text): static
    {
        $this->cancelButtonText = $text;

        return $this;
    }

    /**
     * Get the custom component name.
     */
    public function getComponent(): ?string
    {
        return $this->component;
    }

    /**
     * Set a custom component for the frontend.
     */
    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    /**
     * Return a successful action response.
     */
    public static function message(string $message): ActionResponse
    {
        return ActionResponse::message($message);
    }

    /**
     * Return a danger/error action response.
     */
    public static function danger(string $message): ActionResponse
    {
        return ActionResponse::danger($message);
    }

    /**
     * Return a redirect action response.
     */
    public static function redirect(string $url): ActionResponse
    {
        return ActionResponse::redirect($url);
    }

    /**
     * Return a download action response.
     */
    public static function download(string $url, string $name): ActionResponse
    {
        return ActionResponse::download($url, $name);
    }

    /**
     * Return an action response that opens a URL in a new tab.
     */
    public static function openInNewTab(string $url): ActionResponse
    {
        return ActionResponse::openInNewTab($url);
    }

    /**
     * Convert the action to array representation.
     */
    public function toArray(NadotaRequest $request): array
    {
        $data = [
            'key' => static::getKey(),
            'name' => $this->name(),
            'fields' => collect($this->fields($request))
                ->map(fn($field) => $field->toArray($request))
                ->values()
                ->toArray(),
            'showOnIndex' => $this->showOnIndex(),
            'showOnDetail' => $this->showOnDetail(),
            'destructive' => $this->isDestructive(),
            'standalone' => $this->isStandalone(),
            'confirmText' => $this->confirmText(),
            'confirmButtonText' => $this->confirmButtonText(),
            'cancelButtonText' => $this->cancelButtonText(),
        ];

        // Only include component if explicitly set
        if ($this->component !== null) {
            $data['component'] = $this->component;
        }

        return $data;
    }

    /**
     * Create a new action instance.
     */
    public static function make(): static
    {
        return new static();
    }
}
