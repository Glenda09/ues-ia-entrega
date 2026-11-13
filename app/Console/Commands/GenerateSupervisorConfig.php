<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSupervisorConfig extends Command
{
    protected $signature = 'queue:supervisor-config
                            {--target=production : Entorno destino (production|dev). En dev añade --verbose}
                            {--output= : Ruta del archivo .conf de salida (default: .docker/supervisord/<target>/queues.conf)}
                            {--app-path=/var/www/html : Ruta a la raíz de la app}
                            {--php-bin=php : Binario PHP usado por el worker}
                            {--user=www-data : Usuario bajo el cual corren los workers}
                            {--print : Imprime la conf en stdout en vez de escribirla a disco}';

    protected $description = 'Genera la configuración de supervisord para los workers definidos en config/workers.php';

    public function handle(): int
    {
        $target = (string) $this->option('target');
        $appPath = rtrim((string) $this->option('app-path'), '/');
        $phpBin = (string) $this->option('php-bin');
        $user = (string) $this->option('user');
        $output = $this->option('output') ?: base_path(".docker/supervisord/$target/queues.conf");
        $defaults = config('workers.defaults', []);
        $workers = config('workers.workers', []);

        if ($workers === []) {
            $this->error('No hay workers definidos en config/workers.php');

            return self::FAILURE;
        }

        $blocks = [
            '; ============================================================',
            "; Generado por: php artisan queue:supervisor-config --target=$target",
            '; NO EDITAR A MANO. Editar config/workers.php y regenerar.',
            '; ============================================================',
            '',
        ];

        foreach ($workers as $name => $worker) {
            $blocks[] = $this->renderProgram(
                (string) $name,
                $worker,
                $defaults,
                $appPath,
                $phpBin,
                $user,
                $target === 'dev'
            );
            $blocks[] = '';
        }

        $contents = implode("\n", $blocks);

        if ($this->option('print')) {
            $this->line($contents);

            return self::SUCCESS;
        }

        $directory = dirname((string) $output);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("No se pudo crear directorio $directory");

            return self::FAILURE;
        }

        if (is_file($output) && file_get_contents($output) === $contents) {
            $this->info("Configuración sin cambios en: $output");
            $this->info('Programas: '.count($workers));

            return self::SUCCESS;
        }

        $temporary = tempnam($directory, '.queues-');
        if ($temporary === false) {
            $this->error("No se pudo crear un archivo temporal en $directory");

            return self::FAILURE;
        }

        $written = file_put_contents($temporary, $contents);
        $replaced = $written !== false
            && chmod($temporary, 0644)
            && rename($temporary, (string) $output);

        if (! $replaced) {
            @unlink($temporary);
            $this->error("No se pudo escribir la configuración en $output");

            return self::FAILURE;
        }

        $this->info("Configuración generada en: $output");
        $this->info('Programas: '.count($workers));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $worker
     * @param  array<string, mixed>  $defaults
     */
    private function renderProgram(
        string $name,
        array $worker,
        array $defaults,
        string $appPath,
        string $phpBin,
        string $user,
        bool $verbose
    ): string {
        $queues = $worker['queues'] ?? [];
        $numprocs = $worker['numprocs'] ?? $defaults['numprocs'] ?? 1;
        $timeout = $worker['timeout'] ?? $defaults['timeout'] ?? 3600;
        $memory = $worker['memory'] ?? $defaults['memory'] ?? 512;
        $tries = $worker['tries'] ?? $defaults['tries'] ?? 1;
        $sleep = $worker['sleep'] ?? $defaults['sleep'] ?? 3;
        $startsecs = $worker['startsecs'] ?? $defaults['startsecs'] ?? 5;
        $stopwaitsecs = $worker['stopwaitsecs'] ?? $defaults['stopwaitsecs'] ?? null;
        $stopwaitsecs ??= $timeout + 30;

        if ($queues === []) {
            throw new \InvalidArgumentException("El worker '$name' no declara colas");
        }

        $command = "$phpBin $appPath/artisan queue:work"
            .' --queue='.implode(',', $queues)
            ." --timeout=$timeout"
            ." --memory=$memory"
            ." --tries=$tries"
            ." --sleep=$sleep";

        if ($verbose) {
            $command .= ' --verbose';
        }

        $programName = "queue-$name";
        $logFile = "$appPath/storage/logs/supervisor/$programName.log";

        return <<<INI
[program:$programName]
process_name=%(program_name)s_%(process_num)02d
command=$command
directory=$appPath
autostart=true
autorestart=true
startsecs=$startsecs
startretries=1000
numprocs=$numprocs
user=$user
redirect_stderr=true
stdout_logfile=$logFile
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=$stopwaitsecs
stopsignal=TERM
stopasgroup=true
killasgroup=true
INI;
    }
}
