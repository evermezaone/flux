<x-filament-panels::page>
    {{-- FLX REQ-0025: Leaflet + OpenStreetMap (sin API key). --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div
        x-data="vlsMapa(@js($mapDataUrl))"
        x-init="init()"
        wire:ignore
    >
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <button type="button" class="fi-btn fi-btn-size-sm fi-color-primary px-3 py-1 rounded-lg bg-primary-600 text-white text-sm"
                    x-on:click="setLive()">En vivo</button>

            <label class="text-sm">Histórico:
                <input type="datetime-local" x-model="atLocal" x-on:change="setHistorico()"
                       class="fi-input rounded-lg border-gray-300 text-sm" />
            </label>

            <label class="text-sm flex items-center gap-1">
                <input type="checkbox" x-model="showHeat" x-on:change="renderHeat()" />
                Mapa de calor
            </label>

            <label class="text-sm flex items-center gap-1">
                Tamaño por:
                <select x-model="intensityMetric" x-on:change="render()" class="fi-input rounded-lg border-gray-300 text-sm">
                    <option value="pressure">Presión</option>
                    <option value="occupancy">Ocupación</option>
                </select>
            </label>

            <span class="text-xs text-gray-500" x-text="statusText"></span>
        </div>

        <div id="vls-map" style="height: 70vh; width: 100%; border-radius: 0.75rem;"></div>

        <div class="mt-3 text-xs text-gray-500" x-show="unlocated.length > 0">
            Cruces sin ubicación (no aparecen en el mapa):
            <span x-text="unlocated.map(s => s.code).join(', ')"></span>.
            Cargá su latitud/longitud en <strong>Cruces</strong>.
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    @endpush

    <script>
        function vlsMapa(dataUrl) {
            return {
                dataUrl: dataUrl,
                map: null,
                markersLayer: null,
                heatLayer: null,
                data: { sites: [], unlocated: [] },
                unlocated: [],
                atLocal: '',
                showHeat: false,
                intensityMetric: 'pressure',
                statusText: '',
                _timer: null,

                init() {
                    // Espera a que Leaflet (CDN) este disponible.
                    if (typeof L === 'undefined') {
                        setTimeout(() => this.init(), 150);
                        return;
                    }
                    if (this.map) return;
                    this.map = L.map('vls-map').setView([-25.30, -57.60], 12); // Paraguay por defecto
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap',
                    }).addTo(this.map);
                    this.markersLayer = L.layerGroup().addTo(this.map);
                    this.setLive();
                },

                color(cong) {
                    switch (cong) {
                        case 'low': return '#16a34a';
                        case 'med': return '#d97706';
                        case 'high': return '#dc2626';
                        case 'saturated': return '#7f1d1d';
                        default: return '#6b7280';
                    }
                },

                radius(last) {
                    const v = last ? (Number(last[this.intensityMetric]) || 0) : 0;
                    return Math.max(7, Math.min(28, 7 + v * 2)); // intensidad -> tamaño
                },

                fetchData() {
                    let url = this.dataUrl;
                    if (this.atLocal) {
                        const iso = new Date(this.atLocal).toISOString();
                        url += (url.includes('?') ? '&' : '?') + 'at=' + encodeURIComponent(iso);
                    }
                    this.statusText = 'Cargando…';
                    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(j => {
                            this.data = j;
                            this.unlocated = j.unlocated || [];
                            this.statusText = (j.mode === 'historico' ? 'Histórico ' + (j.at || '') : 'En vivo') +
                                ' · ' + (j.sites ? j.sites.length : 0) + ' cruce(s)';
                            this.render();
                        })
                        .catch(() => { this.statusText = 'No se pudo cargar el mapa.'; });
                },

                render() {
                    if (!this.map) return;
                    this.markersLayer.clearLayers();
                    const pts = [];
                    (this.data.sites || []).forEach(s => {
                        const last = s.last;
                        const m = L.circleMarker([s.lat, s.lng], {
                            radius: this.radius(last),
                            color: this.color(last ? last.congestion : null),
                            fillColor: this.color(last ? last.congestion : null),
                            fillOpacity: 0.65,
                            weight: 2,
                        });
                        m.bindPopup(this.popupHtml(s));
                        m.addTo(this.markersLayer);
                        const inten = last ? (Number(last[this.intensityMetric]) || 0) : 0;
                        pts.push([s.lat, s.lng, Math.max(0.2, inten / 10)]);
                    });
                    this._heatPts = pts;
                    this.renderHeat();
                },

                renderHeat() {
                    if (!this.map) return;
                    if (this.heatLayer) { this.map.removeLayer(this.heatLayer); this.heatLayer = null; }
                    if (this.showHeat && this._heatPts && this._heatPts.length && typeof L.heatLayer === 'function') {
                        this.heatLayer = L.heatLayer(this._heatPts, { radius: 35, blur: 20, maxZoom: 17 }).addTo(this.map);
                    }
                },

                popupHtml(s) {
                    const last = s.last;
                    // Escape HTML real (anti-XSS): los datos de cruce/dispositivo/telemetria son
                    // externos (los manda el equipo) y se insertan via innerHTML del popup.
                    const esc = (v) => {
                        if (v === null || v === undefined) return '—';
                        return String(v)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                    };
                    // s.id es entero del backend; se fuerza a numero para la URL.
                    const tel = '{{ url('admin/telemetria') }}' + '?tableFilters[site_id][value]=' + encodeURIComponent(Number(s.id));
                    let h = '<strong>' + esc(s.code) + '</strong>' + (s.name ? ' — ' + esc(s.name) : '') + '<br>';
                    h += 'Dispositivos: ' + (s.devices && s.devices.length ? esc(s.devices.join(', ')) : '—') + '<br>';
                    if (last) {
                        h += 'Congestión: <b>' + esc(last.congestion) + '</b><br>';
                        h += 'Presión: ' + esc(last.pressure) + ' · Ocup.: ' + esc(last.occupancy) + '<br>';
                        h += 'Decisión: ' + esc(last.decision) + '<br>';
                        h += 'Batería: ' + esc(last.battery_pct) + '%<br>';
                        h += '<small>Últ. dato: ' + esc(last.ts) + '</small><br>';
                    } else {
                        h += '<em>Sin telemetría</em><br>';
                    }
                    if (s.location_manual) h += '<small>📍 ubicación manual</small><br>';
                    h += '<a href="' + tel + '">Ver telemetría →</a>';
                    return h;
                },

                setLive() {
                    this.atLocal = '';
                    this.fetchData();
                    if (this._timer) clearInterval(this._timer);
                    this._timer = setInterval(() => { if (!this.atLocal) this.fetchData(); }, 30000);
                },

                setHistorico() {
                    if (this._timer) { clearInterval(this._timer); this._timer = null; }
                    this.fetchData();
                },
            };
        }
    </script>
</x-filament-panels::page>
