<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__, 2).'/.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = $_SERVER['APP_DEBUG'] ?? true;

$kernel = new Kernel(is_string($env) ? $env : 'dev', filter_var($debug, \FILTER_VALIDATE_BOOL));
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
assert($doctrine instanceof ManagerRegistry);

return $doctrine->getManager();
