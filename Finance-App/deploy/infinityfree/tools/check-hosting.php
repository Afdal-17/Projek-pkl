<?php

$checks = [
    'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'BCMath' => extension_loaded('bcmath'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'OpenSSL' => extension_loaded('openssl'),
    'Mbstring' => extension_loaded('mbstring'),
    'JSON' => extension_loaded('json'),
];

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo 'PHP '.PHP_VERSION."\n";

$passed = true;

foreach ($checks as $label => $result) {
    echo ($result ? '[OK]   ' : '[FAIL] ').$label."\n";
    $passed = $passed && $result;
}

echo $passed
    ? "\nHosting meets the Finance App PHP requirements."
    : "\nDo not upload/run the app until failed requirements are resolved.";

if (! $passed) {
    http_response_code(500);
}
