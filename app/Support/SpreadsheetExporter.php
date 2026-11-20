<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams rows into a temporary CSV/XLSX file and returns a download response that deletes
 * the file after sending. Cells are sanitised against spreadsheet formula injection.
 */
class SpreadsheetExporter
{
    /**
     * @param  array<string>  $headers
     * @param  iterable<array>  $rows
     */
    public static function download(string $format, string $baseName, array $headers, iterable $rows): BinaryFileResponse
    {
        $format = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = tempnam(sys_get_temp_dir(), 'rintel_export_');
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map([self::class, 'sanitize'], $row)));
        }
        $writer->close();

        $fileName = $baseName.'-'.now()->format('Ymd-His').'.'.$format;

        return response()->download($path, $fileName, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend();
    }

    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value ?? '';
        }
        // Neutralise =, @, tab/CR and +/- that are not simply a signed number (e.g. +447700900123).
        if (preg_match('/^[=@\t\r]/', $value) || (preg_match('/^[+\-]/', $value) && ! preg_match('/^[+\-]?[0-9 ]+$/', $value))) {
            return "'".$value;
        }

        return $value;
    }
}
