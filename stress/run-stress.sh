#!/usr/bin/env bash
# Ejecuta con JMeter (CLI) la prueba de estrés del formulario de inscripción.
#
# Uso:
#   BASE_URL=http://localhost:8090 ./run-stress.sh
#   BASE_URL=https://qa.ues.test HILOS=80 DURACION_SEG=600 ./run-stress.sh
#   PREPARAR_CUENTAS=1 ./run-stress.sh        # crea/reinicia cuentas en el Docker local
#
# Variables opcionales (sobrescriben config.properties): BASE_URL, OBJETIVO_RPS, HILOS,
# DURACION_SEG, RAMPA_SEG, CONVOCATORIA. JMETER_HOME si jmeter no está en PATH.
# Devuelve 0 si la prueba cumple los criterios de resumen.txt; 1 si no.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG="$DIR/config.properties"

leer() { sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$CONFIG" | tail -1 | tr -d '\r'; }

SOBRESCRITOS=()
for clave in BASE_URL OBJETIVO_RPS HILOS DURACION_SEG RAMPA_SEG CONVOCATORIA; do
  if [ -n "${!clave:-}" ]; then
    valor="${!clave}"; [ "$clave" = BASE_URL ] && valor="${valor%/}"
    SOBRESCRITOS+=("-J$clave=$valor")
    printf -v "$clave" '%s' "$valor"
  else
    printf -v "$clave" '%s' "$(leer "$clave")"
  fi
done
PASSWORD="$(leer PASSWORD)"

case "$BASE_URL" in http://*|https://*) ;; *) echo "BASE_URL inválida: '$BASE_URL'. Usa http(s)://host[:puerto]." >&2; exit 1 ;; esac

# En Git Bash/MSYS (Windows) el script jmeter de Unix no detecta Java: se usa jmeter.bat.
case "$(uname -s)" in MINGW*|MSYS*|CYGWIN*) EJECUTABLE=jmeter.bat ;; *) EJECUTABLE=jmeter ;; esac
if [ -n "${JMETER_HOME:-}" ]; then JMETER="$JMETER_HOME/bin/$EJECUTABLE"; else JMETER="$(command -v "$EJECUTABLE" || true)"; fi
[ -f "$JMETER" ] || { echo "No se encontró JMeter. Agrega su carpeta bin al PATH o define JMETER_HOME." >&2; exit 1; }

curl -sf -o /dev/null -H 'Accept: application/json' --max-time 10 "$BASE_URL/api/publico/convocatorias/$CONVOCATORIA" \
  || { echo "La convocatoria $CONVOCATORIA no responde en $BASE_URL." >&2; exit 1; }

if [ "${PREPARAR_CUENTAS:-0}" = 1 ]; then
  (cd "$DIR/.." && docker compose exec -T app php stress/preparar-cuentas.php \
    "--cantidad=$HILOS" "--password=$PASSWORD" "--convocatoria=$CONVOCATORIA" --reiniciar)
fi

SALIDA="$DIR/resultados/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$SALIDA"
JTL="$SALIDA/resultados.jtl"

echo "Prueba de estrés: $BASE_URL | objetivo $OBJETIVO_RPS peticiones/s | $HILOS hilos | $DURACION_SEG s (rampa $RAMPA_SEG s)"
"$JMETER" -n -t "$DIR/formulario-inscripcion.jmx" -q "$CONFIG" \
  -l "$JTL" -j "$SALIDA/jmeter.log" -e -o "$SALIDA/reporte" \
  ${SOBRESCRITOS[@]+"${SOBRESCRITOS[@]}"}

# Veredicto contra los criterios de config.properties (escribe resumen.txt).
CRITERIOS=()
for s in ${SOBRESCRITOS[@]+"${SOBRESCRITOS[@]}"}; do CRITERIOS+=("${s#-J}"); done
codigo=0
"${JAVA_HOME:+$JAVA_HOME/bin/}java" "$DIR/Resumen.java" "$JTL" "$CONFIG" ${CRITERIOS[@]+"${CRITERIOS[@]}"} || codigo=$?
echo
echo "Resultados: $SALIDA (abre reporte/index.html)"
exit $codigo
