<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 42mm 20mm 22mm 20mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #000; line-height: 1.45; }
    header { position: fixed; top: -34mm; left: 0; right: 0; height: 28mm; border-bottom: 1px solid #000; }
    header img { position: absolute; left: 0; top: 2mm; height: 22mm; max-width: 35mm; }
    header .institucion { text-align: center; font-size: 14pt; font-weight: bold; padding-top: 8mm; margin: 0 38mm; }
    h1 { text-align: center; font-size: 13pt; margin: 4mm 0 2mm; text-transform: uppercase; letter-spacing: 1px; }
    .folio { text-align: center; font-size: 10pt; margin-bottom: 6mm; }
    .datos td { padding: 1mm 3mm 1mm 0; font-size: 10.5pt; }
    table.notas { width: 100%; border-collapse: collapse; margin-top: 4mm; }
    table.notas thead { display: table-header-group; }
    table.notas th, table.notas td { border: 1px solid #000; padding: 1.5mm 2mm; font-size: 10pt; }
    table.notas th { background: #fff; font-weight: bold; text-align: left; }
    table.notas tr { page-break-inside: avoid; }
    .firma { page-break-inside: avoid; margin-top: 14mm; text-align: center; }
    .firma .espacio { height: 40mm; }
    .firma .linea { border-top: 1px solid #000; width: 75mm; margin: 0 auto 1mm; }
    .num { text-align: right; }
</style>
</head>
<body>
<header>
    <img src="data:{{ $inst['logo_mime'] }};base64,{{ $inst['logo_base64'] }}" alt="Logo">
    <div class="institucion">{{ $inst['nombre'] }}</div>
</header>

<h1>{{ $doc->tipo === 'certificacion_notas' ? 'Certificación de notas' : 'Constancia de estudio' }}</h1>
<div class="folio">Folio {{ $doc->folio }} · {{ $i['lugar'] }}, {{ $i['fecha'] }}</div>

<table class="datos">
    <tr><td><b>Estudiante:</b></td><td>{{ $i['nombre'] }}</td></tr>
    <tr><td><b>Carnet:</b></td><td>{{ $i['carnet'] }}</td></tr>
    <tr><td><b>Carrera:</b></td><td>{{ $i['carrera'] }} ({{ $i['codigo_carrera'] }})</td></tr>
    <tr><td><b>Plan de estudios:</b></td><td>{{ $i['pensum'] }}</td></tr>
    <tr><td><b>Estado académico:</b></td><td>{{ $i['estado'] }}</td></tr>
    <tr><td><b>Fecha de ingreso:</b></td><td>{{ $i['fecha_ingreso'] }}</td></tr>
</table>

@if ($doc->tipo === 'certificacion_notas')
    <p>Se certifica que, según los registros académicos publicados, el estudiante obtuvo los siguientes resultados:</p>
    <table class="notas">
        <thead>
        <tr><th>Ciclo</th><th>Código</th><th>Materia</th><th class="num">Nota</th><th>Condición</th><th class="num">Créditos</th></tr>
        </thead>
        <tbody>
        @foreach ($i['resultados'] as $r)
            <tr><td>{{ $r['ciclo'] }}</td><td>{{ $r['codigo'] }}</td><td>{{ $r['materia'] }}</td><td class="num">{{ $r['nota'] }}</td><td>{{ $r['condicion'] }}</td><td class="num">{{ number_format($r['creditos'], 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <p>Créditos aprobados: <b>{{ number_format($i['creditos_aprobados'], 2) }}</b>.</p>
@else
    <p>Se hace constar que el estudiante arriba indicado se encuentra <b>Activo</b> y cursa el {{ $i['ciclo'] }} con las siguientes materias inscritas:</p>
    <table class="notas">
        <thead><tr><th>Código</th><th>Materia</th></tr></thead>
        <tbody>
        @foreach ($i['materias_inscritas'] as $m)
            <tr><td>{{ $m['codigo'] }}</td><td>{{ $m['materia'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <p>Se extiende la presente para los usos que el interesado estime convenientes.</p>
@endif

<div class="firma">
    <div class="espacio"></div>
    <div class="linea"></div>
    <div><b>{{ $doc->nombre_autoridad }}</b></div>
    <div>{{ $doc->cargo_autoridad }}</div>
</div>
</body>
</html>
