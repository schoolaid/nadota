<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ManagesAttachments
{
    /**
     * Whether this field supports attachments.
     */
    protected bool $attachable = true;

    /**
     * Fields to search when looking for attachable items.
     */
    protected array $attachableSearchFields = ['id'];

    /**
     * Callback to customize the attachable query.
     */
    protected $attachableQueryCallback = null;

    /**
     * Maximum number of items that can be attached (null = unlimited).
     */
    protected ?int $attachableLimit = null;

    /**
     * Whether to show attached items count in index view.
     */
    protected bool $showCountOnIndex = false;

    /**
     * Custom label for the attach button.
     */
    protected ?string $attachButtonLabel = null;

    /**
     * Fields to display in the attachment modal/list.
     */
    protected array $attachableDisplayFields = [];

    /**
     * Enable attachments for this field.
     *
     * @return static
     */
    public function attachable(bool $attachable = true): static
    {
        $this->attachable = $attachable;
        return $this;
    }

    /**
     * Set fields to search when looking for attachable items.
     *
     * @param array $fields
     * @return static
     */
    public function attachableSearchFields(array $fields): static
    {
        $this->attachableSearchFields = $fields;
        return $this;
    }

    /**
     * Set a callback to customize the attachable query.
     *
     * @param callable $callback
     * @return static
     */
    public function attachableQuery(callable $callback): static
    {
        $this->attachableQueryCallback = $callback;
        return $this;
    }

    /**
     * Set the maximum number of items that can be attached.
     *
     * @param int|null $limit
     * @return static
     */
    public function attachableLimit(?int $limit): static
    {
        $this->attachableLimit = $limit;
        return $this;
    }

    /**
     * Check if this field is attachable.
     *
     * @return bool
     */
    public function isAttachable(): bool
    {
        return $this->attachable;
    }

    /**
     * Get the attachable search fields.
     *
     * @return array
     */
    public function getAttachableSearchFields(): array
    {
        return $this->attachableSearchFields;
    }

    /**
     * Get the attachable query callback.
     *
     * @return callable|null
     */
    public function getAttachableQueryCallback(): ?callable
    {
        return $this->attachableQueryCallback;
    }

    /**
     * Get the attachable limit.
     *
     * @return int|null
     */
    public function getAttachableLimit(): ?int
    {
        return $this->attachableLimit;
    }

    /**
     * Show count of attached items in index view.
     *
     * @param bool $show
     * @return static
     */
    public function showCountOnIndex(bool $show = true): static
    {
        $this->showCountOnIndex = $show;
        return $this;
    }

    /**
     * Set custom label for the attach button.
     *
     * @param string $label
     * @return static
     */
    public function attachButtonLabel(string $label): static
    {
        $this->attachButtonLabel = $label;
        return $this;
    }

    /**
     * Set fields to display in the attachment modal/list.
     *
     * @param array $fields
     * @return static
     */
    public function attachableDisplayFields(array $fields): static
    {
        $this->attachableDisplayFields = $fields;
        return $this;
    }

    /**
     * Get whether to show count on index.
     *
     * @return bool
     */
    public function shouldShowCountOnIndex(): bool
    {
        return $this->showCountOnIndex;
    }

    /**
     * Get the attach button label.
     *
     * @return string|null
     */
    public function getAttachButtonLabel(): ?string
    {
        return $this->attachButtonLabel;
    }

    /**
     * Get the attachable display fields.
     *
     * @return array
     */
    public function getAttachableDisplayFields(): array
    {
        return $this->attachableDisplayFields;
    }

    /**
     * Get attachment configuration for frontend.
     *
     * @return array
     */
    public function getAttachmentConfig(): array
    {
        return [
            'enabled' => $this->attachable,
            'searchFields' => $this->attachableSearchFields,
            'limit' => $this->attachableLimit,
            'showCountOnIndex' => $this->showCountOnIndex,
            'buttonLabel' => $this->attachButtonLabel,
            'displayFields' => $this->attachableDisplayFields,
        ];
    }
}