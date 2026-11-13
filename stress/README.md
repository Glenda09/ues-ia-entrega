# Prueba de estrés del formulario de inscripción

Plan de JMeter para ejecutar desde la línea de comandos. Mantiene una carga sostenida de **15 peticiones por segundo** sobre el formulario de admisión (EXP-01) y evalúa el resultado contra criterios explícitos.

## Qué se mide

Cada aspirante virtual inicia sesión una vez con su propia cuenta. Después repite el guardado por etapas que hace `AdmisionFormularioView.vue`:

| Petición | Endpoint |
| --- | --- |
| Formulario - Consultar borrador | `GET /api/academico/mi-borrador-ingreso?convocatoria=N` |
| Formulario - Guardar datos personales | `PATCH /api/academico/mi-borrador-ingreso` (etapa 2) |
| Formulario - Guardar información académica | `PATCH /api/academico/mi-borrador-ingreso` (etapa 3, valida la oferta y asegura la orden de pago) |

Las 15 peticiones por segundo cuentan solo estas tres. Un *Constant Throughput Timer* compartido entre todos los hilos regula el ritmo. Cada PATCH usa la versión optimista que devolvió la petición anterior, así que un `409 VERSION_CONFLICTO` cuenta como error. La convocatoria y el login (`Preparación - …`) se ejecutan una vez por hilo y no entran en el cálculo del objetivo.

## Archivos

| Archivo | Uso |
| --- | --- |
| `config.properties` | **Configuración que se edita antes de la prueba**: `BASE_URL`, convocatoria, carga, duración y criterios. |
| `formulario-inscripcion.jmx` | Plan de JMeter. Solo usa elementos nativos, sin Groovy, y funciona con cualquier Java soportado. |
| `preparar-cuentas.php` | Crea o reinicia `aspirante.stress.1..N@ues.test` con el perfil `academico_aspirante`. Se niega a ejecutarse con `APP_ENV=production`. |
| `run-stress.ps1` / `run-stress.sh` | Ejecutores para PowerShell y Bash. Comprueban la URL, lanzan JMeter, generan el reporte HTML y devuelven el veredicto. |
| `Resumen.java` | Evalúa el tramo estable y escribe `resumen.txt`. Sale con código `0` si la prueba se aprueba y `1` si se rechaza. |

## Requisitos

- Apache JMeter 5.6 o superior. Su carpeta `bin` debe estar en `PATH`, o se indica con `JMETER_HOME`.
- Java 17 o superior. JMeter lo usa y `Resumen.java` se ejecuta en modo de archivo fuente.
- La aplicación debe tener una convocatoria **publicada y abierta** con al menos una oferta de ingreso `general`. El seeder de demostración crea la convocatoria `1`.
- Las cuentas de prueba no pueden pedir doble factor: `AUTH_2FA_ENFORCE_ALL=false`.
- El límite `APP_API_RATE_LIMIT` se aplica por IP y por minuto. Toda la carga sale de una sola IP, así que 15 peticiones por segundo requieren al menos 900. Se recomiendan 1200 o más. El `.env` de desarrollo usa 6000.

## 1. Configurar la URL base

Edita `config.properties`:

```properties
BASE_URL=http://localhost:8090
CONVOCATORIA=1
```

También puedes indicarla al lanzar la prueba sin editar el archivo: `-BaseUrl` en PowerShell, la variable `BASE_URL=` en Bash o `-JBASE_URL=` en JMeter. El valor indicado así tiene prioridad sobre `config.properties`.

## 2. Preparar las cuentas

Crea tantas cuentas como `HILOS`, con la misma `PASSWORD` de `config.properties`. `--reiniciar` borra los borradores y las órdenes de pago de esas cuentas en la convocatoria.

```powershell
# Docker local (desde app/)
docker compose exec -T app php stress/preparar-cuentas.php --cantidad=50 --password=Stress2026! --convocatoria=1 --reiniciar
```

En otro entorno, ejecuta el mismo comando con `php` desde el directorio `app/` de ese servidor. Con el Docker local, los ejecutores lo hacen por ti si pasas `-PrepararCuentas` o `PREPARAR_CUENTAS=1`.

## 3. Ejecutar

```powershell
# PowerShell (desde app/stress)
.\run-stress.ps1 -BaseUrl http://localhost:8090 -PrepararCuentas
.\run-stress.ps1 -BaseUrl https://qa.ejemplo.edu -Duracion 600 -Hilos 80
```

```bash
# Bash (desde app/stress)
BASE_URL=http://localhost:8090 PREPARAR_CUENTAS=1 ./run-stress.sh
BASE_URL=https://qa.ejemplo.edu DURACION_SEG=600 HILOS=80 ./run-stress.sh
```

También puedes llamar a JMeter directamente:

```bash
jmeter -n -t formulario-inscripcion.jmx -q config.properties -JBASE_URL=http://localhost:8090 \
  -l resultados/manual/resultados.jtl -e -o resultados/manual/reporte
java Resumen.java resultados/manual/resultados.jtl config.properties
```

Cada ejecución deja en `resultados/AAAAMMDD-HHMMSS/` los archivos `resultados.jtl`, `jmeter.log`, `reporte/index.html` (el panel HTML de JMeter) y `resumen.txt`. La carpeta `resultados/` está fuera del control de versiones.

## Criterios de aceptación

`Resumen.java` descarta los primeros `RAMPA_SEG` segundos y evalúa el resto:

| Criterio | Parámetro | Valor por defecto |
| --- | --- | --- |
| Rendimiento ≥ objetivo − tolerancia | `OBJETIVO_RPS`, `TOLERANCIA_RPS_PCT` | 15 peticiones/s, 5 % (mínimo 14.25) |
| Errores (estado distinto de 200) | `MAX_ERROR_PCT` | 1 % |
| Percentil 95 de tiempo de respuesta | `MAX_P95_MS` | 2000 ms |
| Preparación sin fallos (convocatoria y login) | — | 0 fallos |

Si una ejecución sale `RECHAZADA`, revisa en `resumen.txt` qué criterio falló y qué códigos de error aparecieron. Si el rendimiento no llega al objetivo y el tiempo medio de respuesta multiplicado por 15 supera los `HILOS`, faltan usuarios concurrentes. En ese caso aumenta `HILOS` y prepara la misma cantidad de cuentas.

## Ajustes de carga

- `HILOS=50` permite sostener 15 peticiones por segundo con respuestas de hasta unos 3 s (50 ÷ 15). Cada hilo usa su propia cuenta y su propio borrador, así que no compiten por la misma fila.
- `RAMPA_SEG` y `DURACION_SEG` definen el arranque gradual y la duración total. Una duración mayor (600 s o más) detecta degradación sostenida.
- Antes de repetir la prueba, usa `--reiniciar` para que todas las ejecuciones partan del mismo estado.
