<?php

declare(strict_types=1);

use Doctrine\ORM\Tools\Console\ConsoleRunner;

// Bootstrap the application and get EntityManager
$entityManager = require_once __DIR__ . '/bootstrap.php';

// Create and return the helper set
return ConsoleRunner::createHelperSet($entityManager);