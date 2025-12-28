<?php

namespace SchoolAid\Nadota\Http\Services\Exporters;

use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\Response;

class CsvExporter extends AbstractExporter
{
    /**
     * CSV delimiter character.
     */
    protected string $delimiter = ',';

    /**
     * CSV enclosure character.
     */
    protected string $enclosure = '"';

    /**
     * Whether to include BOM for Excel compatibility.
     */
    protected bool $includeBom = true;

    /**
     * Set CSV delimiter.
     */
    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Set CSV enclosure.
     */
    public function enclosure(string $enclosure): static
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    /**
     * Disable BOM (Byte Order Mark).
     */
    public function withoutBom(): static
    {
        $this->includeBom = false;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function export(LazyCollection $data, array $headers, string $filename): Response
    {
        return $this->streamResponse($filename, function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            if ($this->includeBom) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            // Write headers
            fputcsv($handle, array_values($headers), $this->delimiter, $this->enclosure);

            // Write data rows
            foreach ($data as $row) {
                $formattedRow = $this->formatRow($row, $headers);
                fputcsv($handle, $formattedRow, $this->delimiter, $this->enclosure);
            }

            fclose($handle);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension(): string
    {
        return 'csv';
    }
}
