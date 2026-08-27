<?php
// Bootstrap script to create database if not exists

// Connect without a database first (no dbname in DSN)
try {
    $bootstrap = new PDO(
        'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $bootstrap->exec("CREATE DATABASE IF NOT EXISTS konekt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database <strong>konekt_db</strong> is ready.<br>";
    echo '<a href="index.php">Go to homepage →</a>';
} catch (PDOException $e) {
    http_response_code(500);
    echo "❌ Could not connect to MySQL: " . htmlspecialchars($e->getMessage());
    echo "<br>Make sure Laragon is running and MySQL is started.";
}
