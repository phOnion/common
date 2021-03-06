<?php

use Onion\Framework\Common\Config\Container;
use Onion\Framework\Common\Config\Loader;
use Onion\Framework\Common\Config\Reader\PhpReader;
use Onion\Framework\Common\Config\Reader\IniReader;
use Onion\Framework\Common\Config\Reader\YamlReader;
use Onion\Framework\Common\Config\Reader\JsonReader;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$loader = new Loader();
$loader->registerReader(['php', 'inc'], new PhpReader);
$loader->registerReader(['ini', 'env'], new IniReader);
$loader->registerReader(['json'], new JsonReader);

// Requires 'symfony/yaml'
// $loader->registerReader(['yaml', 'yml'], new YamlReader());

$configuration = new Container(
    $loader->loadDirectory('development', __DIR__ . '/config'),
    [
        'connect' => function (string $host, int $port, string $user, string $password) {
            return "db://{$user}:{$password}@{$host}:{$port}";
        },
    ]
);

var_dump(
    $configuration->get('common.site.header'),
    $configuration->get('database'),
    $configuration->get('database.connection'),
    $configuration->get('common'),
    $configuration->get('user.name')
);

