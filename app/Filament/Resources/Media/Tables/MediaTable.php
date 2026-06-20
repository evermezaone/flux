<?php

namespace App\Filament\Resources\Media\Tables;

use App\Models\Command;
use App\Models\Media;
use App\Models\Telemetry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ts_start', 'desc')
            ->columns([
                TextColumn::make('site.code')->label('Cruce')->searchable(),
                TextColumn::make('device.code')->label('Dispositivo')->searchable(),
                TextColumn::make('tipo')->badge(),
                TextColumn::make('ts_start')->label('Hora')->dateTime()->sortable(),
                TextColumn::make('file')->label('Archivo')->searchable(),
                TextColumn::make('size_mb')->label('MB')->numeric()->sortable(),
                IconColumn::make('available')->label('Disp.')->boolean(),
                // Verificacion dato<->video: telemetria de esa hora.
                TextColumn::make('correlacion')
                    ->label('Telemetria @ hora')
                    ->state(fn (Media $record): string => self::correlacion($record)),
            ])
            ->recordActions([
                self::pedirClipAction(),
                // Ver el video/foto inline en el navegador (REQ-0018).
                Action::make('ver')
                    ->label('Ver video')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->url(fn (Media $record): ?string => filled($record->url) ? route('media.view', $record) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record): bool => filled($record->url)),
                Action::make('descargar')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    // route() respeta el subdirectorio /flux y corrige urls viejas root-relative (REQ-0016).
                    ->url(fn (Media $record): ?string => filled($record->url) ? route('media.download', $record) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record): bool => filled($record->url)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Decision/presion registradas en la hora del clip (cruce dato<->video). */
    private static function correlacion(Media $record): string
    {
        if (! $record->ts_start) {
            return '-';
        }

        $t = Telemetry::query()
            ->where('device_id', $record->device_id)
            ->whereBetween('ts', [
                $record->ts_start->copy()->startOfHour(),
                $record->ts_start->copy()->endOfHour(),
            ])
            ->orderBy('ts')
            ->first();

        return $t
            ? "decision={$t->decision} · presion={$t->pressure} · congestion={$t->congestion}"
            : 'sin telemetria en esa hora';
    }

    /** Encola un publish_clip para que el dispositivo suba el clip de esa hora. */
    private static function pedirClipAction(): Action
    {
        return Action::make('pedir_clip')
            ->label('Pedir clip')
            ->icon(Heroicon::OutlinedVideoCamera)
            ->requiresConfirmation()
            // publish_clip requiere ts (contrato REQ-0004): sin ts_start no se ofrece la accion.
            ->visible(fn (Media $record): bool => filled($record->ts_start))
            ->action(function (Media $record): void {
                if (blank($record->ts_start)) {
                    // Defensa: nunca encolar un publish_clip sin ts.
                    Notification::make()->title('Este media no tiene hora (ts) para pedir el clip')->danger()->send();

                    return;
                }

                // REQ-0018: se pasa el file objetivo para que el clip subido actualice ESTA fila
                // (mismo registro) en lugar de crear uno nuevo con otro nombre.
                Command::create([
                    'device_id' => $record->device_id,
                    'cmd' => 'publish_clip',
                    'params' => [
                        'ts' => $record->ts_start->toIso8601String(),
                        'file' => $record->file,
                    ],
                    'status' => 'pending',
                ]);

                Notification::make()->title('Pedido de clip encolado (actualizará esta fila)')->success()->send();
            });
    }
}
