<?php

namespace SchoolAid\Nadota\Http\Services\Exporters;

use Illuminate\Support\LazyCollection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExporter extends AbstractExporter
{
    /**
     * Whether to auto-size columns.
     */
    protected bool $autoSize = true;

    /**
     * Whether to freeze the header row.
     */
    protected bool $freezeHeader = true;

    /**
     * Whether to style headers as bold.
     */
    protected bool $boldHeaders = true;

    /**
     * Disable auto-size columns.
     */
    public function withoutAutoSize(): static
    {
        $this->autoSize = false;
        return $this;
    }

    /**
     * Disable freeze header row.
     */
    public function withoutFreezeHeader(): static
    {
        $this->freezeHeader = false;
        return $this;
    }

    /**
     * Disable bold headers.
     */
    public function withoutBoldHeaders(): static
    {
        $this->boldHeaders = false;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function export(LazyCollection $data, array $headers, string $filename): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write headers
        $headerValues = array_values($headers);
        $columnCount = count($headerValues);

        foreach ($headerValues as $colIndex => $header) {
            $columnLetter = $this->getColumnLetter($colIndex + 1);
            $sheet->getCell("{$columnLetter}1")->setValue($header);
        }

        // Style headers
        if ($this->boldHeaders && $columnCount > 0) {
            $lastColumn = $this->getColumnLetter($columnCount);
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        }

        // Freeze header row
        if ($this->freezeHeader) {
            $sheet->freezePane('A2');
        }

        // Write data rows
        $rowIndex = 2;
        foreach ($data as $row) {
            $formattedRow = $this->formatRow($row, $headers);
            foreach ($formattedRow as $colIndex => $value) {
                $columnLetter = $this->getColumnLetter($colIndex + 1);
                $sheet->getCell("{$columnLetter}{$rowIndex}")->setValue($value);
            }
            $rowIndex++;
        }

        // Auto-size columns
        if ($this->autoSize && $columnCount > 0) {
            foreach (range(1, $columnCount) as $colIndex) {
                $columnLetter = $this->getColumnLetter($colIndex);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
        }

        return $this->createStreamedResponse($spreadsheet, $filename);
    }

    /**
     * Create streamed response for the Excel file.
     */
    protected function createStreamedResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $fullFilename = $filename . '.' . $this->getExtension();

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => "attachment; filename=\"{$fullFilename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get column letter from column number (1-based).
     */
    protected function getColumnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intdiv($columnNumber, 26);
        }
        return $letter;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension(): string
    {
        return 'xlsx';
    }
}
