<?php

use Onion\Framework\Common\Config\Container;
use Onion\Framework\Common\Config\Loader;
use Onion\Framework\Common\Config\Reader\PhpReader;
use Onion\Framework\Common\Config\Reader\IniReader;
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$loader = new Loader();
$loader->registerReader(['php', 'inc'], new PhpReader);
$loader->registerReader(['ini', 'env'], new IniReader);

$configuration = new Container(
    $loader->loadDirectory('development', __DIR__ . '/config'),
    [
        'env' => 'getenv',
        'connect' => function (string $host, int $port, string $user, string $password) {
            return "db://{$user}:{$password}@{$host}:{$port}";
        }
    ]
);

var_dump(
    $configuration->get('database'),
    $configuration->get('database.connection'),
    $configuration->get('common')
);

