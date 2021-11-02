<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\InjectorInByRef;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('delete_001');
//endregion

//region INSERT ALL RECORDS - NO BINARY DATA
$pilot->run(
    id: 'delete_002',
    description: 'Insert all films, without binary data',
    test: fn() => $pilot->getResource('insert_all_plain_sql')($ppp)
);
$pilot->assertIsArray();
$pilot->assertEqual(['1', '2', '3']);
//endregion

//region DELETE PLAIN SQL
$pilot->run(
    id: 'delete_003',
    description: 'Delete all blu-rays using plain sql',
    test: fn() => $ppp->delete("DELETE FROM t_video WHERE video_support LIKE {$ppp("%blu%")}")
);
$pilot->assertIsInt();
$pilot->assertEqual(2);
//endregion