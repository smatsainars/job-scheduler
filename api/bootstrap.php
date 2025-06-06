<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate required environment variables
$requiredEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET'];
foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var])) {
        throw new Exception("Required environment variable {$var} is not set");
    }
}

// Configure Doctrine ORM
$isDevMode = $_ENV['APP_DEBUG'] === 'true';

// Create configuration
if ($isDevMode) {
    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/src/Entity'],
        isDevMode: true
    );
} else {
    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: [__DIR__ . '/src/Entity'],
        isDevMode: false
    );
}

// Database connection parameters
$connectionParams = [
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'] ?? '',
    'host' => $_ENV['DB_HOST'],
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

try {
    // Create database connection
    $connection = DriverManager::getConnection($connectionParams);
    
    // Create EntityManager
    $entityManager = new EntityManager($connection, $config);
    
    // Test connection
    $connection->connect();
    
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw new Exception("Database connection failed: " . $e->getMessage());
}

// Return EntityManager for use in other files
return $entityManager;