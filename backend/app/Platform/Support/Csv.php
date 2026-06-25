<?php

declare(strict_types=1);

namespace App\Platform\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds CSV downloads from an array of associative rows.
 */
final class Csv
{
    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,string>|null  $headers  column keys (defaults to first row keys)
     */
    public static function download(string $filename, array $rows, ?array $headers = null): StreamedResponse
    {
        $headers ??= $rows === [] ? [] : array_keys($rows[0]);

        return new StreamedResponse(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            if ($headers !== []) {
                fputcsv($out, $headers);
            }
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $key) {
                    $line[] = $row[$key] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
