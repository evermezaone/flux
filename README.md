# FLX — Backend del sistema FLUX

Backend (API REST + panel) del sistema de control de tráfico adaptativo **FLUX**. Recibe la telemetría
de la app **VialSense**, la persiste y la sirve a un panel de operación (Filament). Stack: **Laravel 13 +
Filament 5 + MariaDB**.

> El contrato de datos está en `requerimientos_backend.md` y `sensor_campo_arquitectura.md` (§3).

---

## Requisitos

- PHP **8.2+** (extensiones: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`, `intl`)
- **Composer** 2.x
- **MariaDB** 10.x / 11.x
- (Opcional) Node solo si se recompilan assets de Filament; el `filament:install` ya publica los assets.

---

## Despliegue (VPS — recomendado)

```bash
# 1. Clonar el código
git clone <repo> flux-backend && cd flux-backend

# 2. Dependencias (sin dev en produccion)
composer install --no-dev --optimize-autoloader

# 3. Entorno
cp .env.example .env
php artisan key:generate
#   PRODUCCION (obligatorio, antes de cachear config):
#     APP_ENV=production
#     APP_DEBUG=false          # NUNCA true en produccion (filtra trazas/variables)
#     APP_URL=https://TU-DOMINIO
#   Editar tambien: DB_*, ADMIN_EMAIL/ADMIN_PASSWORD, CORS_ALLOWED_ORIGINS,
#   INGESTA_RATE_LIMIT, TELEMETRY_RETENTION_DAYS

# 4. Base de datos
php artisan migrate --force

# 5. Operador del panel (lee ADMIN_* del .env; falla si faltan)
php artisan db:seed --class=Database\Seeders\DatabaseSeeder
#   (opcional, datos de prueba) php artisan db:seed --class=Database\Seeders\TelemetrySeeder

# 6. Cache de produccion
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 7. Permisos de escritura
chmod -R ug+rw storage bootstrap/cache
```

**Document root:** el servidor web debe apuntar a **`public/`** (no a la raíz del proyecto).

### Scheduler (cron)

La purga de telemetry (`telemetry:purge`, REQ-0010) corre con el scheduler de Laravel. Agregar **un solo**
cron en el VPS:

```cron
* * * * * cd /ruta/flux-backend && php artisan schedule:run >> /dev/null 2>&1
```

(El scheduler internamente ejecuta `telemetry:purge` diariamente a las 03:00.)

### Storage de clips

Los clips subidos (REQ-0008) se guardan en el disk `local` (`storage/app/private/media`). Asegurar que
`storage/` sea escribible y respaldado según la política de retención.

---

## Hostinger (hosting compartido) — alternativa

Funciona si el plan permite PHP 8.2+, Composer y apuntar el document root a `public/`. Limitaciones:

- Sin workers persistentes: la cola de comandos es por polling (no se usan queue workers), así que es OK.
- El scheduler depende del cron del panel de Hostinger (configurar `php artisan schedule:run` cada minuto).
- Si el plan no permite Composer o el docroot a `public/`, usar el **VPS**.

La BD puede ser la MariaDB de Hostinger (`u237417599_FLUX`) accedida desde el VPS de forma remota, o una
BD local del VPS. Hoy la BD vive en Hostinger.

---

## Verificación post-deploy (smoke test)

```bash
php artisan about                  # entorno y drivers OK
php artisan migrate:status         # migraciones aplicadas
php artisan route:list --path=api  # endpoints de la API presentes

# Auth de ingesta (debe dar 401 sin token):
curl -i https://TU-DOMINIO/api/v1/ping
# Con un device sembrado y su X-Device-Key, debe dar 200.

# Panel: entrar a https://TU-DOMINIO/admin y loguear con ADMIN_EMAIL/ADMIN_PASSWORD.
```

Checklist:

- [ ] `APP_ENV=production` y `APP_DEBUG=false` (verificar con `php artisan about`); `APP_URL=https://TU-DOMINIO`.
- [ ] `migrate` aplicado (tablas `sites/devices/telemetry/media/commands` + base de Laravel).
- [ ] Operador admin creado (login a `/admin` OK).
- [ ] `GET /api/v1/ping` sin token → 401; con token válido → 200.
- [ ] Cron del scheduler activo (verificar que `telemetry:purge` corre).
- [ ] Cabeceras de seguridad presentes (`curl -I`).

---

## Variables de entorno

Ver `.env.example`. Claves propias de FLX:

| Var | Para qué |
|---|---|
| `APP_ENV` | **`production`** en el VPS (no `local`) |
| `APP_DEBUG` | **`false`** en producción (true filtra trazas/variables) |
| `APP_URL` | `https://TU-DOMINIO` (URLs absolutas correctas) |
| `DB_*` | Conexión a MariaDB FLUX |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Operador del panel (el seeder falla si faltan) |
| `CORS_ALLOWED_ORIGINS` | Dominios permitidos del panel |
| `INGESTA_RATE_LIMIT` | Requests de ingesta por minuto por device (default 120) |
| `TELEMETRY_RETENTION_DAYS` | Días de retención de telemetry cruda (default 90) |

Nunca commitear `.env` (gitignoreado). `.env.example` no debe contener secretos.

---

## Tests

```bash
php artisan test    # suite completa (sqlite en memoria, no toca MariaDB)
```
