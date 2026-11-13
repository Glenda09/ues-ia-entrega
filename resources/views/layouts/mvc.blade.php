<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $marca['textos']['titulo_pestana'] ?: $marca['textos']['nombre'] }}</title>
    <meta name="description" content="{{ $marca['textos']['descripcion'] }}">
    <link rel="icon" href="{{ $marca['assets']['favicon'] ?: 'data:,' }}">
    <script>window.__PERSONALIZACION__ = {{ Illuminate\Support\Js::from($marca) }};</script>
    @if (file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        @php($manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true))
        @if (!empty($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="/build/{{ $manifest['resources/css/app.css']['file'] }}">
        @endif
        @foreach ($manifest['resources/js/app.js']['css'] ?? [] as $css)
            <link rel="stylesheet" href="/build/{{ $css }}">
        @endforeach
        @if (!empty($manifest['resources/js/app.js']['file']))
            <script type="module" src="/build/{{ $manifest['resources/js/app.js']['file'] }}"></script>
        @endif
    @endif
</head>

<body>
    <div id="app"></div>
</body>

</html>
