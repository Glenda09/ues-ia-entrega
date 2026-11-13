#!/usr/bin/env bash
set -euo pipefail

SU_EXEC="${SU_EXEC:-/usr/sbin/gosu}"
CACHE_DIR="/var/www/html/bootstrap/cache"
STORAGE_DIR="/var/www/html/storage"
ENV_FILE="/var/www/html/.env"
ENV_EXAMPLE="/var/www/html/.env.example"
SUPERVISORD_LOG="/tmp/supervisord.log"
SUPERVISORD_PID="/tmp/supervisord.pid"
SUPERVISORD_CONF="/etc/supervisord.conf"
SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"
SUPERVISOR_WORKER_CONF="${SUPERVISOR_CONF_DIR}/laravel-worker.conf"

# Rol del contenedor (despliegue escalable, ver docs/deploy-vm-docker.md):
#   all    -> nginx + php-fpm + scheduler + workers (comportamiento histórico, desarrollo)
#   web    -> solo nginx + php-fpm (réplicas detrás del balanceador)
#   worker -> solo scheduler + workers de cola
CONTAINER_ROLE="${CONTAINER_ROLE:-all}"
case "$CONTAINER_ROLE" in
    all) RUN_WEB=true; RUN_SCHEDULER=true; RUN_QUEUES=true ;;
    web) RUN_WEB=true; RUN_SCHEDULER=false; RUN_QUEUES=false ;;
    worker) RUN_WEB=false; RUN_SCHEDULER=true; RUN_QUEUES=true ;;
    *) echo "[entrypoint] ERROR: CONTAINER_ROLE invalido: ${CONTAINER_ROLE} (use all|web|worker)"; exit 1 ;;
esac
# supervisord.conf usa %(ENV_RUN_WEB)s y %(ENV_RUN_SCHEDULER)s en autostart.
export CONTAINER_ROLE RUN_WEB RUN_SCHEDULER RUN_QUEUES
export PHP_FPM_MAX_CHILDREN="${PHP_FPM_MAX_CHILDREN:-50}"

# En producción la configuración llega por variables de entorno (env_file de Compose):
# no se crea .env, no se generan llaves y no se vacía la caché compartida.
is_production() {
    [ "${APP_ENV:-}" = "production" ]
}

run_as_laravel() {
    if [ "$(id -u)" -eq 0 ]; then
        "$SU_EXEC" laravel "$@"
    else
        "$@"
    fi
}

has_app_key() {
    if [ -n "${APP_KEY:-}" ]; then
        return 0
    fi

    if [ ! -f "$ENV_FILE" ]; then
        return 1
    fi

    local clave
    clave="$(sed -n 's/^[[:space:]]*APP_KEY=//p' "$ENV_FILE" | head -n 1 | tr -d '\r')"
    clave="${clave%%#*}"
    clave="${clave%\"}"
    clave="${clave#\"}"
    clave="${clave%\'}"
    clave="${clave#\'}"

    [ -n "$clave" ]
}

ensure_writable_paths() {
    # Directorios requeridos por Laravel (storage/app puede ser un volumen compartido vacío)
    mkdir -p "$CACHE_DIR" "$STORAGE_DIR/logs" "$STORAGE_DIR/logs/supervisor" \
        "$STORAGE_DIR/app/private" "$STORAGE_DIR/app/public" "$STORAGE_DIR/app/personalizacion" \
        "$STORAGE_DIR/framework/cache/data" "$STORAGE_DIR/framework/sessions" \
        "$STORAGE_DIR/framework/views" "$STORAGE_DIR/framework/testing"

    # Elimina cachés previas (.php) montadas desde el host para evitar providers obsoletos o ausentes
    rm -f "$CACHE_DIR"/*.php

    if [ "$(id -u)" -eq 0 ]; then
        echo "[entrypoint] Ajustando permisos de bootstrap/cache y storage..."

        if is_production; then
            # Volumen compartido entre réplicas: solo corrige lo que no pertenece a laravel,
            # sin recorrer ni reescribir todo el árbol en cada arranque.
            if ! find "$CACHE_DIR" "$STORAGE_DIR" \( ! -user laravel -o ! -group laravel \) -exec chown laravel:laravel {} + ; then
                echo "[entrypoint] WARNING: chown parcial fallo en storage"
            fi
        elif ! chown -R laravel:laravel "$CACHE_DIR" "$STORAGE_DIR"; then
            echo "[entrypoint] WARNING: chown fallo, relajando permisos a 777"
            chmod -R 777 "$CACHE_DIR" "$STORAGE_DIR"
        else
            chmod -R u+rwX,g+rwX "$CACHE_DIR" "$STORAGE_DIR"
        fi
    else
        for path in "$CACHE_DIR" "$STORAGE_DIR"; do
            if [ ! -w "$path" ]; then
                echo "[entrypoint] ERROR: ${path} no es escribible por UID $(id -u). Revisa permisos del bind mount (ajusta WWWUSER/WWWGROUP a tus UID/GID o aplica chown/chmod en el host)."
                exit 1
            fi
        done
    fi
}

ensure_env_file() {
    if is_production; then
        return 0
    fi

    if [ ! -f "$ENV_FILE" ] && [ -f "$ENV_EXAMPLE" ]; then
        if [ "$(id -u)" -eq 0 ]; then
            cp "$ENV_EXAMPLE" "$ENV_FILE"
            echo "[entrypoint] .env no encontrado, se copio desde .env.example"
        else
            echo "[entrypoint] ERROR: .env no existe y no se puede crear sin permisos root. Crea .env a partir de .env.example en el host."
            exit 1
        fi
    fi

    if [ -f "$ENV_FILE" ]; then
        if [ "$(id -u)" -eq 0 ]; then
            if ! chown laravel:laravel "$ENV_FILE"; then
                echo "[entrypoint] WARNING: no se pudo chown de .env, ajustando permisos para escritura compartida (666)"
                chmod 666 "$ENV_FILE" || true
            else
                chmod u+rw,g+rw "$ENV_FILE"
            fi
            if ! run_as_laravel [ -w "$ENV_FILE" ]; then
                echo "[entrypoint] ERROR: $ENV_FILE sigue sin ser escribible por el usuario laravel. Ajusta permisos/propietario en el host (chown/chmod) o monta el archivo con UID/GID compatibles."
                exit 1
            fi
        else
            if [ ! -w "$ENV_FILE" ]; then
                echo "[entrypoint] ERROR: $ENV_FILE no es escribible por UID $(id -u). Ajusta permisos del bind mount (chown/chmod en host o usa WWWUSER/WWWGROUP que coincidan)."
                exit 1
            fi
        fi
    fi
}

ensure_writable_paths

ensure_env_file

ensure_supervisor_worker_conf() {
    # Solo inicializa la config del worker cuando el contenedor vaya a correr supervisord.
    if [ "${1:-}" != "/usr/bin/supervisord" ] && [ "${1:-}" != "supervisord" ]; then
        return 0
    fi

    # Las réplicas web no procesan colas: elimina cualquier conf de worker previa.
    if [ "$RUN_QUEUES" != "true" ]; then
        rm -f "$SUPERVISOR_WORKER_CONF" 2>/dev/null || true
        return 0
    fi

    if [ ! -w "$SUPERVISOR_CONF_DIR" ] && [ "$(id -u)" -ne 0 ]; then
        echo "[entrypoint] WARNING: no se puede inicializar ${SUPERVISOR_WORKER_CONF} sin permisos de escritura en ${SUPERVISOR_CONF_DIR}."
        return 0
    fi

    mkdir -p "$SUPERVISOR_CONF_DIR" || true

    local target="${SUPERVISOR_ENV:-production}"

    run_as_laravel php artisan queue:supervisor-config \
        --target="$target" \
        --app-path=/var/www/html \
        --php-bin=/usr/local/bin/php \
        --user=laravel \
        --output=/tmp/laravel-workers.conf

    if ! cmp -s /tmp/laravel-workers.conf "$SUPERVISOR_WORKER_CONF"; then
        cp /tmp/laravel-workers.conf "${SUPERVISOR_WORKER_CONF}.new"
        mv -f "${SUPERVISOR_WORKER_CONF}.new" "$SUPERVISOR_WORKER_CONF"
    fi

    chmod 644 "$SUPERVISOR_WORKER_CONF"
    rm -f /tmp/laravel-workers.conf "${SUPERVISOR_WORKER_CONF}.new"

    if [ -f "$SUPERVISORD_CONF" ] && ! grep -Eq "^[[:space:]]*\\[include\\][[:space:]]*$" "$SUPERVISORD_CONF"; then
        cat >> "$SUPERVISORD_CONF" <<'EOF'

[include]
files = /etc/supervisor/conf.d/*.conf
EOF
    fi
}

if [ "$(id -u)" -eq 0 ]; then
    # Supervisord escribe en /var/www/html; aseguramos permisos
    touch "$SUPERVISORD_LOG" "$SUPERVISORD_PID"
    if ! chown laravel:laravel "$SUPERVISORD_LOG" "$SUPERVISORD_PID"; then
        echo "[entrypoint] WARNING: no se pudo chown de supervisord.log/pid, relajando permisos a 664"
        chmod 664 "$SUPERVISORD_LOG" "$SUPERVISORD_PID" || true
    else
        chmod u+rw,g+rw "$SUPERVISORD_LOG" "$SUPERVISORD_PID"
    fi
else
    for sup_file in "$SUPERVISORD_LOG" "$SUPERVISORD_PID"; do
        if [ ! -e "$sup_file" ]; then
            touch "$sup_file" 2>/dev/null || true
        fi
        if [ ! -w "$sup_file" ]; then
            echo "[entrypoint] ERROR: $sup_file no es escribible por UID $(id -u). Ajusta permisos del bind mount."
            exit 1
        fi
    done
fi

# Genera APP_KEY si falta (solo fuera de producción: todas las réplicas deben compartir la misma llave)
if ! has_app_key; then
    if is_production; then
        echo "[entrypoint] ERROR: APP_KEY no definido. Generelo una vez y declárelo en .env.production."
        exit 1
    fi
    echo "[entrypoint] APP_KEY no encontrado, generando..."
    run_as_laravel php artisan key:generate --force
fi

if is_production; then
    # Cachés de arranque locales al contenedor (config, rutas, eventos, vistas).
    # No se usa optimize:clear porque incluye cache:clear y vaciaría la caché compartida
    # en PostgreSQL (sesiones de 2FA, blacklist JWT, rate limits) cada vez que arranca una réplica.
    run_as_laravel php artisan package:discover --ansi || true
    run_as_laravel php artisan optimize
else
    # Limpia y optimiza
    run_as_laravel php artisan optimize:clear || true
    run_as_laravel php artisan package:discover --ansi || true
fi

# Configura la zona horaria
if [ "$(id -u)" -eq 0 ]; then
    echo "[entrypoint] Configurando zona horaria a America/El_Salvador..."
    ln -snf /usr/share/zoneinfo/America/El_Salvador /etc/localtime
    echo "America/El_Salvador" > /etc/timezone
fi

# Configura git (safe.directory) para evitar warnings de "dubious ownership"
# en bind-mounts de desarrollo. Best-effort: no debe abortar el arranque y no
# depende de $HOME (que puede venir vacío en el contenedor).
if [ "$(id -u)" -eq 0 ]; then
    git config --system --add safe.directory /var/www/html 2>/dev/null || true
else
    HOME="${HOME:-/tmp}" git config --global --add safe.directory /var/www/html 2>/dev/null || true
fi

# Inicializa la configuración del worker para supervisord (colas)
ensure_supervisor_worker_conf "${1:-}"

if [ "$(id -u)" -eq 0 ]; then
    exec "$SU_EXEC" laravel "$@"
else
    exec "$@"
fi
