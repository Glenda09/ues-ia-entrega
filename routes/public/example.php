<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response('pong');
});

Route::get('/stream/text', function () {
    return response()->stream(function () {
        for ($i = 1; $i <= 10; $i++) {
            $payload = [
                'done' => false,
                'message' => "Chunk {$i}/10 - " . str_repeat('Este es el contenido del chunk. ', 5)
            ];
            echo json_encode($payload) . ',';
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            sleep(1);
        }
        $final = ['done' => true, 'message' => 'Stream completed.'];
        echo json_encode($final);
        if (ob_get_level()) { ob_flush(); }
        flush();
    }, 200, ['Content-Type' => 'application/json']);
});

Route::get('/stream/file', function () {
    $filePath = public_path('test.pdf');

    if (! file_exists($filePath)) {
        abort(404, 'File not found');
    }

    $fileSize = filesize($filePath) ?: 0;
    $minChunks = 5;
    // Asegura al menos 5 chunks para archivos con tamaño >= 5 bytes.
    $chunkSize = (int) max(1, floor($fileSize / $minChunks));

    return response()->stream(function () use ($filePath, $chunkSize) {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            while (!feof($handle)) {
                $buffer = fread($handle, $chunkSize);
                if ($buffer === false) {
                    break;
                }
                echo $buffer;
                if (ob_get_level()) {
                    @ob_flush();
                }
                @flush();
                sleep(1);
            }
        } finally {
            fclose($handle);
        }
    }, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="test.pdf"',
        'Content-Length' => (string) $fileSize,
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
});
