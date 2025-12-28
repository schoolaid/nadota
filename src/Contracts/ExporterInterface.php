<?php

namespace SchoolAid\Nadota\Contracts;

use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\Response;

interface ExporterInterface
{
    /**
     * Export data to the specific format.
     *
     * @param LazyCollection $data Lazy collection of rows to export
     * @param array $headers Column headers
     * @param string $filename Export filename (without extension)
     * @return Response
     */
    public function export(LazyCollection $data, array $headers, string $filename): Response;

    /**
     * Get the content type for this export format.
     */
    public function getContentType(): string;

    /**
     * Get the file extension for this export format.
     */
    public function getExtension(): string;
}
