<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '');
$pdo->exec("CREATE DATABASE IF NOT EXISTS konekt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database ready\n";
