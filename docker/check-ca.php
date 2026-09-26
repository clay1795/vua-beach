<?php

$path = $argv[1] ?? '';

if ($path === '' || ! is_file($path) || ! is_readable($path)) {
    fwrite(STDERR, "MYSQL_ATTR_SSL_CA không trỏ tới tệp CA đọc được.\n");
    exit(1);
}

$certificate = openssl_x509_read((string) file_get_contents($path));
if ($certificate === false) {
    fwrite(STDERR, "Tệp CA MySQL không phải chứng thư PEM hợp lệ.\n");
    exit(1);
}

echo "Đã kiểm tra CA MySQL: {$path}\n";
