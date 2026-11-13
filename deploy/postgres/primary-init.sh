#!/usr/bin/env bash
# Inicialización del primario PostgreSQL (solo se ejecuta con el volumen vacío, vía
# /docker-entrypoint-initdb.d). Prepara la réplica de lectura por streaming:
#   - rol de replicación con contraseña (scram-sha-256)
#   - regla pg_hba para conexiones de replicación desde la red de Docker
#   - slot físico para que el primario conserve el WAL que la réplica aún no recibe
#   - replicación síncrona remote_apply (REPLICATION_MODE=sync, por defecto)
set -euo pipefail

: "${REPLICATION_USER:=replicator}"
: "${REPLICATION_SLOT:=replica1}"
: "${REPLICATION_MODE:=sync}"

if [ -z "${REPLICATION_PASSWORD:-}" ]; then
    echo "[primary-init] ERROR: REPLICATION_PASSWORD no definido" >&2
    exit 1
fi

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v repl_user="$REPLICATION_USER" \
    -v repl_pass="$REPLICATION_PASSWORD" \
    -v repl_slot="$REPLICATION_SLOT" <<-'EOSQL'
	SELECT format('CREATE ROLE %I WITH REPLICATION LOGIN PASSWORD %L', :'repl_user', :'repl_pass')
	WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'repl_user') \gexec
	SELECT pg_create_physical_replication_slot(:'repl_slot')
	WHERE NOT EXISTS (SELECT 1 FROM pg_replication_slots WHERE slot_name = :'repl_slot');
EOSQL

# Replicación solo desde redes privadas (la red bridge de Compose); el puerto no se publica.
cat >> "$PGDATA/pg_hba.conf" <<EOF

# Réplica de lectura (deploy/postgres/primary-init.sh)
host replication ${REPLICATION_USER} 10.0.0.0/8 scram-sha-256
host replication ${REPLICATION_USER} 172.16.0.0/12 scram-sha-256
host replication ${REPLICATION_USER} 192.168.0.0/16 scram-sha-256
EOF

if [ "$REPLICATION_MODE" = "sync" ]; then
    # Entra en vigor cuando arranca el servidor definitivo (no bloquea este init).
    # remote_apply: el COMMIT responde cuando la réplica ya aplicó el cambio, de modo
    # que cualquier réplica de la app o worker lee datos al día desde la réplica.
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
        -c "ALTER SYSTEM SET synchronous_standby_names = '\"${REPLICATION_SLOT}\"'" \
        -c "ALTER SYSTEM SET synchronous_commit = 'remote_apply'"
    echo "[primary-init] Replicación síncrona (remote_apply) con standby '${REPLICATION_SLOT}'"
else
    echo "[primary-init] Replicación asíncrona"
fi
