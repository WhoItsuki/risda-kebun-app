<?php
// Database configuration for Laragon MySQL environment
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Laragon default username
define('DB_PASS', '');          // Laragon default password (blank)
define('DB_NAME', 'risda_kebun');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // Stop execution and show clean error during prototype stage
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    return $pdo;
}