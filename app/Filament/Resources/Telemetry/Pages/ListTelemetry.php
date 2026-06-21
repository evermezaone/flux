<?php

namespace App\Filament\Resources\Telemetry\Pages;

use App\Filament\Resources\Telemetry\TelemetryResource;
use App\Models\Telemetry;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTelemetry extends ListRecords
{
    protected static string $resource = TelemetryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Exportar a CSV respetando los filtros/orden activos de la tabla (REQ-0024).
            Action::make('exportar_csv')
                ->label('Exportar CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /** Encabezados del CSV: clave = columna/accessor, valor = etiqueta. */
    private const COLUMNS = [
        'ts' => 'ts',
        'site' => 'cruce',
        'device' => 'dispositivo',
        'zone' => 'zona',
        'occupancy' => 'occupancy',
        'queue_len_m' => 'queue_len_m',
        'pressure' => 'pressure',
        'congestion' => 'congestion',
        'decision' => 'decision',
        'wait_est_s' => 'wait_est_s',
        'empty_s' => 'empty_s',
        'battery_pct' => 'battery_pct',
        'temp_c' => 'temp_c',
        'cpu_pct' => 'cpu_pct',
        'mem_pct' => 'mem_pct',
        'storage_free_pct' => 'storage_free_pct',
        'client_seq' => 'client_seq',
    ];

    /**
     * Genera el CSV de los registros actualmente filtrados/ordenados.
     * Usa getFilteredSortedTableQuery() para honrar los filtros activos (cruce, dispositivo,
     * congestion, zona, decision, rango de fechas) y el orden de la tabla.
     * Se procesa en chunks para no cargar toda la serie en memoria.
     */
    private function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery()->with(['site', 'device']);
        $filename = 'telemetria_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel muestre bien los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values(self::COLUMNS));

            $query->chunk(1000, function ($rows) use ($out): void {
                foreach ($rows as $r) {
                    fputcsv($out, $this->row($r));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Una fila del CSV a partir de un registro de telemetria. */
    private function row(Telemetry $r): array
    {
        return [
            optional($r->ts)->toIso8601String(),
            $r->site?->code,
            $r->device?->code,
            $r->zone,
            $r->occupancy,
            $r->queue_len_m,
            $r->pressure,
            $r->congestion,
            $r->decision,
            $r->wait_est_s,
            $r->empty_s,
            $r->battery_pct,
            $r->temp_c,
            $r->cpu_pct,
            $r->mem_pct,
            $r->storage_free_pct,
            $r->client_seq,
        ];
    }
}
