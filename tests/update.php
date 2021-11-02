<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\InjectorInByRef;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('update_001');
//endregion

//region INSERT ALL RECORDS - NO BINARY DATA
$pilot->run(
    id: 'update_002',
    description: 'Insert all films, without binary data',
    test: fn() => $pilot->getResource('insert_all_plain_sql')($ppp)
);
$pilot->assertIsArray();
$pilot->assertEqual(['1', '2', '3']);
//endregion

//region UPDATE PLAIN SQL
$pilot->run(
    id: 'update_003',
    description: 'Update using plain sql',
    test: fn() => $ppp->update("UPDATE t_video SET video_support = {$ppp('BLU-RAY')} WHERE video_id = {$ppp(3, 'int')}")
);
$pilot->assertIsInt();
$pilot->assertEqual(1);
//endregion



