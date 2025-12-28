<?php

namespace SchoolAid\Nadota\Http\Services\Exporters;

use Illuminate\Support\LazyCollection;
use SchoolAid\Nadota\Contracts\ExporterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AbstractExporter implements ExporterInterface
{
    /**
     * Chunk size for processing large datasets.
     */
    protected int $chunkSize = 500;

    /**
     * Set chunk size for processing.
     */
    public function chunkSize(int $size): static
    {
        $this->chunkSize = $size;
        return $this;
    }

    /**
     * Get chunk size.
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Create a streamed response for download.
     */
    protected function streamResponse(string $filename, callable $callback): StreamedResponse
    {
        $fullFilename = $filename . '.' . $this->getExtension();

        return new StreamedResponse($callback, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => "attachment; filename=\"{$fullFilename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Format a row ensuring all headers are present.
     */
    protected function formatRow(array $row, array $headers): array
    {
        $formatted = [];

        foreach (array_keys($headers) as $key) {
            $formatted[] = $row[$key] ?? '';
        }

        return $formatted;
    }
}
