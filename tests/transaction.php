<?php declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('transaction_001');
//endregion

//region START TRANSACTION
$pilot->run(
    id: 'transaction_002',
    description: 'Start a transaction',
    test: fn() => $ppp->startTransaction()
);
$pilot->assertNotException();
//endregion

//region INSERT FIRST FILM USING PLAIN SQL - NO BINARY DATA
$pilot->run(
    id: 'transaction_003',
    description: 'Insert one record using plain SQL, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region NOT COMMITTED - BUT USING THE SAME CONNECTION : 1 RECORD IN SELECT
$pilot->run(
    id: 'transaction_004',
    description: 'Not committed yet - But same connection, select returns one record',
    test: fn() => $ppp->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(1);
//endregion

//region PREVIOUS STMT IS NOT COMMITTED YET - USING ANOTHER CONNECTION : 0 RECORD IN SELECT
$ppp_new = new PDOPlusPlus('user_test');
$pilot->run(
    id: 'transaction_005',
    description: 'Previous connection is not committed - For another connection select is empty',
    test: fn() => $ppp_new->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(0);
//endregion

//region COMMIT
$pilot->run(
    id: 'transaction_006',
    description: 'Commit',
    test: fn() => $ppp->commit()
);
$pilot->assertNotException();
//endregion

//region PREVIOUS STMT IS COMMITTED - USING ANOTHER CONNECTION : 1 RECORD IN SELECT
$pilot->run(
    id: 'transaction_007',
    description: 'Previous connection is committed - For another connection select returns one record',
    test: fn() => $ppp_new->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(1);
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('transaction_008');
//endregion

//region START TRANSACTION
$pilot->run(
    id: 'transaction_009',
    description: 'Start a transaction',
    test: fn() => $ppp->startTransaction()
);
$pilot->assertNotException();
//endregion

//region INSERT FIRST FILM USING PLAIN SQL - NO BINARY DATA
$pilot->run(
    id: 'transaction_010',
    description: 'Insert first film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region INSERT SECOND FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_011',
    description: 'Insert second film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film2', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('2');
//endregion

//region ROLLBACK ALL
$pilot->run(
    id: 'transaction_012',
    description: 'ROLLBACK all statements',
    test: fn() => $ppp->rollbackAll()
);
$pilot->assertNotException();
//endregion

//region COMMIT
$pilot->run(
    id: 'transaction_013',
    description: 'Commit',
    test: fn() => $ppp->commit()
);
$pilot->assertNotException();
//endregion

//region PREVIOUS STMT IS NOT COMMITTED YET - USING ANOTHER CONNECTION : 0 RECORD IN SELECT
$pilot->run(
    id: 'transaction_014',
    description: 'Transaction is rollback - select is empty',
    test: fn() => $ppp->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(0);
//endregion

//region START TRANSACTION
$pilot->run(
    id: 'transaction_015',
    description: 'Start a transaction',
    test: fn() => $ppp->startTransaction()
);
$pilot->assertNotException();
//endregion

//region INSERT FIRST FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_016',
    description: 'Insert first film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film1', $ppp)
);
$pilot->assertIsString();
//endregion

//region CREATE SAVEPOINT
$pilot->run(
    id: 'transaction_017',
    description: 'Transaction is started, create a first savepoint (first)',
    test: fn() => $ppp->createSavePoint('first')
);
$pilot->assertNotException();
//endregion

//region INSERT SECOND FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_018',
    description: 'Insert second film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film2', $ppp)
);
$pilot->assertIsString();
//endregion

//region CREATE SAVEPOINT
$pilot->run(
    id: 'transaction_019',
    description: 'Transaction is started, create a second savepoint (second)',
    test: fn() => $ppp->createSavePoint('second')
);
$pilot->assertNotException();
//endregion

//region INSERT THIRD FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_020',
    description: 'Insert third film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film3', $ppp)
);
$pilot->assertIsString();
//endregion

// region ROLLBACK TO
$pilot->run(
    id: 'transaction_021',
    description: 'Rollback to the first savepoint',
    test: fn() => $ppp->rollbackTo('first')
);
$pilot->assertNotException();
//endregion

//region COMMIT
$pilot->run(
    id: 'transaction_022',
    description: 'Commit',
    test: fn() => $ppp->commit()
);
$pilot->assertNotException();
//endregion

//region COMMITTED : 1 RECORD IN SELECT
$pilot->run(
    id: 'transaction_023',
    description: 'Committed after rolling back to the first savepoint, only one record in the database',
    test: fn() => $ppp->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(1);
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('transaction_024');
//endregion

//region START TRANSACTION
$pilot->run(
    id: 'transaction_025',
    description: 'Start a transaction',
    test: fn() => $ppp->startTransaction()
);
$pilot->assertNotException();
//endregion

//region INSERT FIRST FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_026',
    description: 'Insert first film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film1', $ppp)
);
$pilot->assertIsString();
//endregion

//region CREATE SAVEPOINT
$pilot->run(
    id: 'transaction_027',
    description: 'Transaction is started, create a first savepoint (first)',
    test: fn() => $ppp->createSavePoint('first')
);
$pilot->assertNotException();
//endregion

//region INSERT SECOND FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_028',
    description: 'Insert second film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film2', $ppp)
);
$pilot->assertIsString();
//endregion

//region CREATE SAVEPOINT
$pilot->run(
    id: 'transaction_029',
    description: 'Transaction is started, create a second savepoint (second)',
    test: fn() => $ppp->createSavePoint('second')
);
$pilot->assertNotException();
//endregion

//region INSERT THIRD FILM - NO BINARY DATA
$pilot->run(
    id: 'transaction_030',
    description: 'Insert third film, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film3', $ppp)
);
$pilot->assertIsString();
//endregion

// region ROLLBACK
$pilot->run(
    id: 'transaction_031',
    description: 'Rollback the last statement',
    test: fn() => $ppp->rollback()
);
$pilot->assertNotException();
//endregion

//region COMMIT
$pilot->run(
    id: 'transaction_032',
    description: 'Commit',
    test: fn() => $ppp->commit()
);
$pilot->assertNotException();
//endregion

//region COMMITTED : 2 RECORD IN SELECT
$pilot->run(
    id: 'transaction_033',
    description: 'Committed after rolling back to the first savepoint, two records in the database',
    test: fn() => $ppp->select('SELECT * FROM t_video')
);
$pilot->assertIsArray();
$pilot->assertCount(2);
//endregion