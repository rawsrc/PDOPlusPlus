<?php declare(strict_types=1);

/**
 * TESTS ARE WRITTEN FOR EXACODIS PHP TEST ENGINE
 * AVAILABLE AT https://github.com/rawsrc/exacodis
 *
 * To run the tests, you must only define a db user granted with all privileges
 */



//region setup test environment
include_once '../vendor/exacodis/Pilot.php';
include_once '../PDOPlusPlus.php';

use rawsrc\PDOPlusPlus\PDOPlusPlus;
use Exacodis\Pilot;

$pilot = new Pilot('PDOPlusPlus - A PHP PDO Wrapper');
$pilot->injectStandardHelpers();

$ppp = new PDOPlusPlus();
//endregion

include 'connections.php';
include 'resources.php';
include 'main_env.php';
include 'insert.php';
include 'select.php';
include 'auto_reset.php';
include 'transaction.php';
include 'binary_data.php';
include 'stored_procedure.php';
include 'update.php';
include 'delete.php';
include 'bigint.php';

$pilot->createReport();
