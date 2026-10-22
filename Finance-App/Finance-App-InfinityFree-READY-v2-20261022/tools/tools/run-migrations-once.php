<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$corePath = __DIR__.'/core';
$environment = parse_ini_file($corePath.'/.env', false, INI_SCANNER_RAW);

if (! is_array($environment)
    || ($environment['ALLOW_WEB_MIGRATIONS'] ?? '') !== 'true'
    || empty($environment['MIGRATION_WEB_TOKEN'])) {
    http_response_code(404);
    exit;
}

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Run database migrations</title>';
    echo '<h1>Run pending database migrations</h1>';
    echo '<p>Only continue after taking a database backup and confirming this database is the intended target.</p>';
    echo '<form method="post"><label>Migration token <input type="password" name="token" required autocomplete="off"></label> ';
    echo '<button type="submit">Run pending migrations</button></form></html>';
    exit;
}

$providedToken = (string) ($_POST['token'] ?? '');
$expectedToken = (string) $environment['MIGRATION_WEB_TOKEN'];

if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

try {
    require $corePath.'/vendor/autoload.php';

    $app = require $corePath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $exitCode = Artisan::call('migrate', [
        '--force' => true,
        '--no-interaction' => true,
    ]);

    if ($exitCode !== 0) {
        throw new RuntimeException('Migration command returned a failure status.');
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "Pending migrations completed.\n";
    echo Artisan::output();
} catch (Throwable $exception) {
    error_log('Temporary migration runner failed: '.$exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration failed. Check the private application log before retrying.\n";
}
