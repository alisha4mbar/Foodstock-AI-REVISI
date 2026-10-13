<?php

namespace App\Services;

use App\Models\Komoditas;
use App\Models\Konsumsi;
use App\Models\Stok;
use App\Models\Wilayah;
use Carbon\Carbon;

/**
 * FR-02 / FR-09: impor data historis.
 *  - importRows(): baris sudah berupa array (frontend membaca CSV/Excel di browser lalu mengirim JSON).
 *  - import(): berkas CSV mentah (pemisah koma/titik-koma) dengan kolom wilayah, komoditas, bulan, jumlah.
 * Nama wilayah/komoditas dicocokkan tanpa membedakan huruf besar/kecil (atau memakai id numerik).
 * Konsumsi: satu nilai per wilayah, komoditas, dan bulan (impor ulang = memperbarui, tidak menggandakan).
 */
class ImportService
{
    /** @param array<int, array{jenis?:string, wilayah:string, komoditas:string, jumlah:mixed, tanggal:string}> $rows */
    public function importRows(array $rows, string $defaultType, string $source, ?int $userId): array
    {
        $wilayah = Wilayah::all();
        $komoditas = Komoditas::all();
        $wByName = $wilayah->keyBy(fn ($w) => mb_strtolower($w->name));
        $kByName = $komoditas->keyBy(fn ($k) => mb_strtolower($k->name));
        $wById = $wilayah->keyBy('id');
        $kById = $komoditas->keyBy('id');

        $report = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        foreach (array_values($rows) as $i => $row) {
            $line = $i + 2; // baris 1 = header pada berkas sumber
            $type = strtolower(trim((string) ($row['jenis'] ?? $defaultType)));
            $wRaw = trim((string) ($row['wilayah'] ?? ''));
            $kRaw = trim((string) ($row['komoditas'] ?? ''));
            $qty = str_replace(',', '.', trim((string) ($row['jumlah'] ?? '')));
            $tgl = trim((string) ($row['tanggal'] ?? ''));

            $w = $wByName[mb_strtolower($wRaw)] ?? (ctype_digit($wRaw) ? $wById->get((int) $wRaw) : null);
            $k = $kByName[mb_strtolower($kRaw)] ?? (ctype_digit($kRaw) ? $kById->get((int) $kRaw) : null);

            try {
                $date = preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $tgl)
                    ? Carbon::parse(strlen($tgl) === 7 ? $tgl.'-01' : $tgl)
                    : null;
            } catch (\Throwable) {
                $date = null;
            }

            $err = null;
            if (! in_array($type, ['stok', 'konsumsi'], true)) $err = "Jenis '{$type}' tidak dikenal (stok/konsumsi)";
            elseif (! $w) $err = "Wilayah tidak ditemukan: {$wRaw}";
            elseif (! $k) $err = "Komoditas tidak ditemukan: {$kRaw}";
            elseif (! is_numeric($qty) || (float) $qty < 0) $err = 'Jumlah harus berupa angka >= 0';
            elseif (! $date) $err = 'Tanggal/periode harus YYYY-MM atau YYYY-MM-DD';

            if ($err) {
                $report['failed']++;
                $report['errors'][] = ['row' => $line, 'reason' => $err];
                continue;
            }

            if ($type === 'konsumsi') {
                $model = Konsumsi::updateOrCreate(
                    ['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'recorded_at' => $date->copy()->startOfMonth()->toDateString()],
                    ['quantity' => (float) $qty, 'source' => $source, 'recorded_by' => $userId]
                );
            } else {
                $model = Stok::updateOrCreate(
                    ['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'recorded_at' => $date->toDateString(), 'source' => $source],
                    ['quantity' => (float) $qty, 'recorded_by' => $userId]
                );
            }
            $model->wasRecentlyCreated ? $report['inserted']++ : $report['updated']++;
        }

        return $report;
    }

    public function import(string $path, string $type, string $source, ?int $userId): array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            throw new \RuntimeException('Berkas tidak dapat dibaca.');
        }
        $firstLine = fgets($fh);
        rewind($fh);
        $delim = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';

        $header = fgetcsv($fh, 0, $delim);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);
        if (in_array('bulan', $header, true) && ! in_array('tanggal', $header, true)) {
            $header[array_search('bulan', $header, true)] = 'tanggal';
        }
        foreach (['wilayah', 'komoditas', 'tanggal', 'jumlah'] as $need) {
            if (! in_array($need, $header, true)) {
                throw new \InvalidArgumentException("Kolom '{$need}' (atau 'bulan') tidak ditemukan pada header berkas.");
            }
        }

        $rows = [];
        while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($cols, 0, count($header)), count($header), ''));
        }
        fclose($fh);

        return $this->importRows($rows, $type, $source, $userId);
    }
}
