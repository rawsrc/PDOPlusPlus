<?php declare(strict_types=1);

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region CONNECTIONS
//region main user, no database selected
PDOPlusPlus::addCnxParams(
    cnx_id: 'user_root',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => '',
        'user' => 'root',
        'pwd' => 'PLEASE INSERT YOUR PWD',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: true
);
//endregion

//region another valid user (for transactions tests)
PDOPlusPlus::addCnxParams(
    cnx_id: 'user_test',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => 'db_pdo_plus_plus',
        'user' => 'user_test',
        'pwd' => 'PLEASE INSERT YOUR PWD',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: false
);
//endregion

//region invalid user
PDOPlusPlus::addCnxParams(
    cnx_id: 'user_ko',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => '',
        'user' => 'root',
        'pwd' => 'wrong_password',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: false
);
//endregion

//region valid user with valid database
PDOPlusPlus::addCnxParams(
    cnx_id: 'db_user_ok',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => 'db_pdo_plus_plus',  // database is defined
        'user' => 'root',
        'pwd' => 'PLEASE INSERT YOUR PWD',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: false
);
//endregion

//region valid user with unknown database
PDOPlusPlus::addCnxParams(
    cnx_id: 'db_user_ko',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => 'unknown_database',
        'user' => 'root',
        'pwd' => 'PLEASE INSERT YOUR PWD',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: false
);
//endregion
//endregion CONNECTIONS

//region OPEN PDO CONNECTION
$pilot->run(
    id: 'connections_001',
    description: 'Open a PDO connection: main user without database selected',
    test: fn() => PDOPlusPlus::getPdo('user_root')
);
$pilot->assertIsInstanceOf(PDO::class);

$pilot->run(
    id: 'connections_002',
    description: 'Open a PDO connection: main user with database selected',
    test: fn() => PDOPlusPlus::getPdo('db_user_ok')
);
$pilot->assertIsInstanceOf(PDO::class);

$pilot->run(
    id: 'connections_003',
    description: 'Try to open a PDO connection with bad credentials',
    test: fn() => PDOPlusPlus::getPdo('user_ko')
);
$pilot->assertException();

$pilot->run(
    id: 'connections_004',
    description: 'Try to open a PDO connection with the main user and an unknown database',
    test: fn() => PDOPlusPlus::getPdo('db_user_ko')
);
$pilot->assertException();
//endregion OPEN PDO
