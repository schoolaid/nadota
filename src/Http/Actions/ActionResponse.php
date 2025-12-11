<?php

namespace SchoolAid\Nadota\Http\Actions;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class ActionResponse implements Arrayable, JsonSerializable
{
    /**
     * The response type.
     */
    protected string $type;

    /**
     * The response message.
     */
    protected ?string $message = null;

    /**
     * The redirect URL.
     */
    protected ?string $url = null;

    /**
     * The download filename.
     */
    protected ?string $filename = null;

    /**
     * Whether the URL should open in a new tab.
     */
    protected bool $openInNewTab = false;

    /**
     * Additional data to include in the response.
     */
    protected array $data = [];

    /**
     * Create a new action response.
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Create a success message response.
     */
    public static function message(string $message): static
    {
        $response = new static('message');
        $response->message = $message;

        return $response;
    }

    /**
     * Create a danger/error message response.
     */
    public static function danger(string $message): static
    {
        $response = new static('danger');
        $response->message = $message;

        return $response;
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url): static
    {
        $response = new static('redirect');
        $response->url = $url;

        return $response;
    }

    /**
     * Create a download response.
     */
    public static function download(string $url, string $name): static
    {
        $response = new static('download');
        $response->url = $url;
        $response->filename = $name;

        return $response;
    }

    /**
     * Create an open in new tab response.
     */
    public static function openInNewTab(string $url): static
    {
        $response = new static('openInNewTab');
        $response->url = $url;
        $response->openInNewTab = true;

        return $response;
    }

    /**
     * Add additional data to the response.
     */
    public function withData(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Get the response type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the response message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the redirect URL.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the download filename.
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Check if URL should open in new tab.
     */
    public function shouldOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    /**
     * Get additional data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'message' => $this->message,
            'url' => $this->url,
            'filename' => $this->filename,
            'openInNewTab' => $this->openInNewTab ?: null,
            'data' => !empty($this->data) ? $this->data : null,
        ], fn($value) => $value !== null);
    }

    /**
     * Convert the response for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
