<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV lisible directement dans Excel : séparateur « ; », encodage UTF-8 avec BOM.
 * Les lignes sont écrites au fil de l'eau (pas de chargement complet en mémoire).
 */
class Csv
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => self::cell($v), $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise les formules (=, +, -, @) pour éviter l'injection dans Excel. */
    private static function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value) ? "'".$value : $value;
    }

    /**
     * Lit un CSV envoyé (séparateur ; ou ,, BOM, encodage Windows) en tableaux associatifs.
     *
     * @return list<array<string, string>>
     */
    public static function read(string $path, int $max = 5000): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        $first = preg_replace('/^\x{FEFF}/u', '', (string) fgets($handle));
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $headers = array_map(fn ($h) => Str::slug(trim($h), '_'), str_getcsv(trim($first), $delimiter));

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($rows) < $max) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = array_map(fn ($v) => trim(mb_convert_encoding((string) $v, 'UTF-8', 'UTF-8, Windows-1252')), $row);
            $rows[] = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), ''));
        }
        fclose($handle);

        return $rows;
    }
}
