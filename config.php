<?php
// Security configuration loader (.env)

function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found. Copy .env.example to .env first.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B'\"");

        if ($name === '') {
            continue;
        }

        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// MySQL
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'notes_app');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Session security
function envBool($name, $default = false) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === null ? $default : $parsed;
}

define('SESSION_LIFETIME', (int)getenv('SESSION_LIFETIME') ?: 86400);
define('CSRF_TOKEN_NAME', getenv('CSRF_TOKEN_NAME') ?: 'csrf_token');
define('SECURE_COOKIES', envBool('SECURE_COOKIES', false));

// Private attachment storage inside the project. Override UPLOAD_DIR in .env when required.
$uploadDir = getenv('UPLOAD_DIR') ?: __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
define('UPLOAD_DIR', rtrim($uploadDir, "\\/"));
define('UPLOAD_MAX_BYTES', (int)getenv('UPLOAD_MAX_BYTES') ?: 10485760);
define('UPLOAD_ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
]);
