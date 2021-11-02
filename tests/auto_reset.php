<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('auto_reset_001');
//endregion

//region DISABLING THE AUTO-RESET FEATURE
// the instance is not cleaned after every call and keep the data
$pilot->run(
    id: 'auto_reset_002',
    test: fn() => $ppp->setAutoResetOff(),
    description: 'Disabling the auto-reset feature'
);
$pilot->assertNotException();
//endregion

//region AUTO-RESET DISABLED - TABLE IS EMPTY - INSERT FIRST RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_003',
    description: 'Auto-reset disabled, table is empty, insert first film using PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

$nb_tokens_film1 = $ppp->getNbTokens();

//region AUTO-RESET DISABLED - TRY TO INSERT SECOND RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_004',
    description: 'Auto-reset disabled, try to insert second film using PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film2', $ppp)
);
$pilot->assertException(PDOException::class);
//endregion

$nb_tokens_film2 = $ppp->getNbTokens();

$pilot->run(
    id: 'auto_reset_005',
    description: 'Count the number of active tokens inside the instance, should be (7 first record + 7 second record)',
    test: fn() => $ppp->getNbTokens()
);
// the tokens are kept between the calls
$pilot->assertEqual(14);

// we reset the instance
$ppp->reset();

//region AUTO-RESET DISABLED - TRY TO INSERT SECOND RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_006',
    description: 'Auto-reset disabled, after resetting manually the instance, try to insert second film using 
                  PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film2', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('3');
//endregion

// we reset the instance
$ppp->reset();

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('auto_reset_007');
//endregion

//region ENABLING THE AUTO-RESET FEATURE
// the instance is cleaned after every call and ready for the next statement
$pilot->run(
    id: 'auto_reset_008',
    test: fn() => $ppp->setAutoResetOn(),
    description: 'Enabling the auto-reset feature'
);
$pilot->assert(
    test: fn() => $pilot->getRunner()->getResult() === null,
    test_name: 'Enabling the auto-reset feature',
    expected: 'null|void'
);
//endregion

//region AUTO-RESET ENABLED - TABLE IS EMPTY - INSERT FIRST RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_009',
    description: 'Auto-reset enabled, table is empty, insert first film using PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region AUTO-RESET ENABLED - TRY TO INSERT SECOND RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_010',
    description: 'Auto-reset enabled, try to insert second film using PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film2', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('2');
//endregion

$pilot->run(
    id: 'auto_reset_011',
    description: 'Count the number of active tokens inside the instance, should be 0, automatically reset after the call',
    test: fn() => $ppp->getNbTokens()
);
// the tokens are kept between the calls
$pilot->assertEqual(0);

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('auto_reset_012');
//endregion

//region BIND PARAMS - ONE RECORDS - NO BINARY DATA
// if there's any var by ref then the auto-reset is automatically disabled
$in = $ppp->getInjectorInByRef();
$film = $pilot->getResource('film1');
$sql = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
    {$in($title)}, {$in($support)}, {$in($multilingual, 'bool')}, {$in($chapter, 'int')}, {$in($year, 'int')}, 
    {$in($summary)}, {$in($stock, 'int')}
);
sql;
$pilot->run(
    id: 'auto_reset_013',
    description: 'Insert the first record using PDOStatement->bindParam(), without binary data',
    test: function() use ($ppp, $film, $sql, &$title, &$support, &$multilingual, &$chapter, &$year, &$summary, &$stock) {
        extract($film);
        return $ppp->insert($sql);  // we save the new id
    }
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

$pilot->run(
    id: 'auto_reset_014',
    description: 'Count the number of active tokens inside the instance, should be 7, because there\'s by ref vars',
    test: fn() => $ppp->getNbTokens()
);
// the tokens are kept
$pilot->assertEqual(7);

//region
$film = $pilot->getResource('film2');
$pilot->run(
    id: 'auto_reset_015',
    description: 'Insert the second record using PDOStatement->bindParam(), without binary data',
    test: function() use ($ppp, $film, $sql, &$title, &$support, &$multilingual, &$chapter, &$year, &$summary, &$stock) {
        extract($film);
        return $ppp->insert($sql);  // we save the new id
    }
);
$pilot->assertIsString();
$pilot->assertEqual('2');
//endregion

$pilot->run(
    id: 'auto_reset_016',
    description: 'Count the number of active tokens inside the instance, should be 7, because there\'s by ref vars',
    test: fn() => $ppp->getNbTokens()
);
// the tokens are kept
$pilot->assertEqual(7);

// as there's active tokens in the instance, the next sql should fail
// the active tokens are still read and the statement prepared
//region TRUNCATE THE TABLE
$pilot->run(
    id: 'auto_reset_017',
    description: 'Try to truncate the table with active tokens in the instance',
    test: fn() => $ppp->execute('TRUNCATE TABLE t_video')
);
$pilot->assertException();
//endregion

// we reset the instance
$ppp->reset();

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('auto_reset_018');
//endregion

// on error the auto-reset is not called

//region AUTO-RESET ENABLED - TABLE EMPTY - INSERT FIRST RECORD USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_019',
    description: 'Auto-reset enabled, table is empty, insert first film using PDOStatement->bindValue() (7 fields), without binary data',
    test: fn() => $pilot->getResource('insert_bind_values')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

$pilot->run(
    id: 'auto_reset_020',
    description: 'Count the number of active tokens inside the instance, should be 0',
    test: fn() => $ppp->getNbTokens()
);
$pilot->assertEqual(0);

//region AUTO-RESET ENABLED - TRY TO INSERT THE FIRST RECORD AGAIN USING PDOStatement->bindValue() - NO BINARY DATA
$pilot->run(
    id: 'auto_reset_021',
    description: 'Auto-reset enabled, try to insert the first film again using PDOStatement->bindValue(), without binary data, 
    PDOException raised',
    test: fn() => $pilot->getResource('insert_bind_values')('film1', $ppp)
);
$pilot->assertException(PDOException::class);
//endregion

$pilot->run(
    id: 'auto_reset_022',
    description: 'Count the number of active tokens inside the instance, should be 7, because of previous exception',
    test: fn() => $ppp->getNbTokens()
);
// the tokens are kept
$pilot->assertEqual(7);

// we reset the instance
$ppp->reset();

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('auto_reset_023');
//endregion