<?php
function log_error($message, $file = 'error_log.txt') {
    $date = date('Y-m-d H:i:s');
    $log = "[$date] $message" . PHP_EOL;
    $logPath = __DIR__ . '/../logs/' . $file;

    if (!file_exists(dirname($logPath))) {
        mkdir(dirname($logPath), 0775, true);
    }

    if (!@file_put_contents($logPath, $log, FILE_APPEND)) {
        error_log("Log dosyasına yazılamadı: $logPath");
    }
}
