<?php

return [
    'database.host' => 'localhost',
    'database.port' => 1234,
    'database.connection' => 'connect(get:database.host, get:database.port, get:database.user, get:database.password)'
];
