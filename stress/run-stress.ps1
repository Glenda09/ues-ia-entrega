<#
.SYNOPSIS
    Ejecuta con JMeter (CLI) la prueba de estrés del formulario de inscripción.

.DESCRIPTION
    Lee config.properties, permite sobrescribir la URL base y la carga, ejecuta
    formulario-inscripcion.jmx sin interfaz, genera el reporte HTML y devuelve
    código 0 si la prueba cumple los criterios (resumen.txt), o 1 si no.

.EXAMPLE
    .\run-stress.ps1 -BaseUrl http://localhost:8090 -PrepararCuentas

.EXAMPLE
    .\run-stress.ps1 -BaseUrl https://qa.ues.test -Duracion 600 -Hilos 80
#>
param(
    [string]$BaseUrl,
    [int]$Rps,
    [int]$Hilos,
    [int]$Duracion,
    [int]$Rampa,
    [int]$Convocatoria,
    # Crea/reinicia las cuentas de aspirante en el contenedor Docker local antes de iniciar.
    [switch]$PrepararCuentas,
    # Carpeta de JMeter si no está en PATH (también se toma de $env:JMETER_HOME).
    [string]$JmeterHome = $env:JMETER_HOME
)

$ErrorActionPreference = 'Stop'
$dir = $PSScriptRoot
$config = Join-Path $dir 'config.properties'

# Valores efectivos: parámetro > config.properties.
$props = @{}
foreach ($linea in Get-Content $config -Encoding UTF8) {
    if ($linea -match '^\s*([A-Za-z_][\w.]*)\s*=\s*(.*?)\s*$') { $props[$Matches[1]] = $Matches[2] }
}
$sobrescritos = [ordered]@{}
if ($BaseUrl) { $sobrescritos.BASE_URL = $BaseUrl.TrimEnd('/') }
if ($Rps) { $sobrescritos.OBJETIVO_RPS = $Rps }
if ($Hilos) { $sobrescritos.HILOS = $Hilos }
if ($Duracion) { $sobrescritos.DURACION_SEG = $Duracion }
if ($Rampa) { $sobrescritos.RAMPA_SEG = $Rampa }
if ($Convocatoria) { $sobrescritos.CONVOCATORIA = $Convocatoria }
foreach ($k in $sobrescritos.Keys) { $props[$k] = "$($sobrescritos[$k])" }

if (($props.BASE_URL -notmatch '^https?://')) { throw "BASE_URL inválida: '$($props.BASE_URL)'. Usa http(s)://host[:puerto]." }

# Localiza JMeter.
$jmeter = $null
if ($JmeterHome) { $jmeter = Join-Path $JmeterHome 'bin\jmeter.bat' }
elseif (Get-Command jmeter.bat -ErrorAction SilentlyContinue) { $jmeter = (Get-Command jmeter.bat).Source }
elseif (Get-Command jmeter -ErrorAction SilentlyContinue) { $jmeter = (Get-Command jmeter).Source }
if (-not $jmeter -or -not (Test-Path $jmeter)) { throw 'No se encontró JMeter. Agrega su carpeta bin al PATH o define JMETER_HOME / -JmeterHome.' }

# Comprueba que la aplicación responda antes de cargarla.
try {
    $null = Invoke-WebRequest -Uri "$($props.BASE_URL)/api/publico/convocatorias/$($props.CONVOCATORIA)" -UseBasicParsing -TimeoutSec 10 -Headers @{ Accept = 'application/json' }
} catch {
    throw "La convocatoria $($props.CONVOCATORIA) no responde en $($props.BASE_URL): $($_.Exception.Message)"
}

if ($PrepararCuentas) {
    Push-Location (Split-Path $dir -Parent)
    try {
        docker compose exec -T app php stress/preparar-cuentas.php "--cantidad=$($props.HILOS)" "--password=$($props.PASSWORD)" "--convocatoria=$($props.CONVOCATORIA)" --reiniciar
        if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar las cuentas de estrés.' }
    } finally { Pop-Location }
}

$salida = Join-Path $dir ("resultados\" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force $salida | Out-Null
$jtl = Join-Path $salida 'resultados.jtl'

$argumentos = @('-n', '-t', (Join-Path $dir 'formulario-inscripcion.jmx'), '-q', $config,
    '-l', $jtl, '-j', (Join-Path $salida 'jmeter.log'), '-e', '-o', (Join-Path $salida 'reporte'))
foreach ($k in $sobrescritos.Keys) { $argumentos += "-J$k=$($sobrescritos[$k])" }

Write-Host "Prueba de estrés: $($props.BASE_URL) | objetivo $($props.OBJETIVO_RPS) peticiones/s | $($props.HILOS) hilos | $($props.DURACION_SEG) s (rampa $($props.RAMPA_SEG) s)"
& $jmeter @argumentos
if ($LASTEXITCODE -ne 0) { throw "JMeter terminó con código $LASTEXITCODE. Revisa $salida\jmeter.log" }

# Veredicto contra los criterios de config.properties (escribe resumen.txt).
$java = if ($env:JAVA_HOME) { Join-Path $env:JAVA_HOME 'bin\java.exe' } else { 'java' }
$criterios = foreach ($k in $sobrescritos.Keys) { "$k=$($sobrescritos[$k])" }
[Console]::OutputEncoding = [Text.Encoding]::UTF8
& $java '-Dstdout.encoding=UTF-8' (Join-Path $dir 'Resumen.java') $jtl $config @criterios
$codigo = $LASTEXITCODE
Write-Host "`nResultados: $salida (abre reporte\index.html)"
exit $codigo
