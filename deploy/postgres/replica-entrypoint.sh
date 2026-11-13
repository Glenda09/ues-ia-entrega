#!/usr/bin/env bash
# Entrypoint de la réplica de lectura. En el primer arranque (volumen vacío) clona el
# primario con pg_basebackup; -R escribe primary_conninfo y standby.signal, y -S usa el
# slot físico creado por primary-init.sh. Después arranca PostgreSQL en hot standby.
set -euo pipefail

: "${PGDATA:=/var/lib/postgresql/data}"
: "${PRIMARY_HOST:=pgsql-primary}"
: "${PRIMARY_PORT:=5432}"
: "${REPLICATION_USER:=replicator}"
: "${REPLICATION_SLOT:=replica1}"

if [ -z "${REPLICATION_PASSWORD:-}" ]; then
    echo "[replica] ERROR: REPLICATION_PASSWORD no definido" >&2
    exit 1
fi

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    echo "[replica] Volumen vacío: esperando al primario ${PRIMARY_HOST}:${PRIMARY_PORT}..."
    until pg_isready -h "$PRIMARY_HOST" -p "$PRIMARY_PORT" -q; do
        sleep 2
    done

    # Limpia restos de un intento previo interrumpido.
    find "$PGDATA" -mindepth 1 -delete 2>/dev/null || true
    mkdir -p "$PGDATA"
    chmod 700 "$PGDATA"

    echo "[replica] Clonando el primario con pg_basebackup (slot ${REPLICATION_SLOT})..."
    PGPASSWORD="$REPLICATION_PASSWORD" pg_basebackup \
        -h "$PRIMARY_HOST" -p "$PRIMARY_PORT" -U "$REPLICATION_USER" \
        -D "$PGDATA" -R -X stream -S "$REPLICATION_SLOT" -P

    # application_name = nombre del slot: es el que espera synchronous_standby_names.
    {
        echo ""
        echo "# deploy/postgres/replica-entrypoint.sh"
        echo "primary_conninfo = 'host=${PRIMARY_HOST} port=${PRIMARY_PORT} user=${REPLICATION_USER} password=${REPLICATION_PASSWORD} application_name=${REPLICATION_SLOT}'"
        echo "primary_slot_name = '${REPLICATION_SLOT}'"
    } >> "$PGDATA/postgresql.auto.conf"
    chmod 600 "$PGDATA/postgresql.auto.conf"
    echo "[replica] Clonado completo."
fi

exec docker-entrypoint.sh postgres "$@"
