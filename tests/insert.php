<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region PLAIN SQL - ONE RECORD - NO BINARY DATA
$pilot->run(
    id: 'insert_001',
    description: 'Insert one record using plain SQL, without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film1', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region BIND VALUES - ONE RECORD - NO BINARY DATA
$pilot->run(
    id: 'insert_002',
    description: 'Insert one record using PDOStatement->bindValue(), without binary data',
    test: fn() => $pilot->getResource('insert_plain_sql')('film2', $ppp)
);
$pilot->assertIsString();
$pilot->assertEqual('2');
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('insert_003');
//endregion

//region BIND PARAMS - ALL RECORDS - NO BINARY DATA
$in = $ppp->getInjectorInByRef();
$films = $pilot->getResource('films');
$sql = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
    {$in($title)}, {$in($support)}, {$in($multilingual, 'bool')}, {$in($chapter, 'int')}, {$in($year, 'int')}, 
    {$in($summary)}, {$in($stock, 'int')}
);
sql;
$pilot->run(
    id: 'insert_004',
    description: 'Insert all records using PDOStatement->bindParam(), without binary data',
    test: function() use ($ppp, $films, $sql, &$title, &$support, &$multilingual, &$chapter, &$year, &$summary, &$stock) {
        $ids = [];
        foreach ($films as $film) {
            extract($film);
            $ids[] = $ppp->insert($sql);  // we save the new id
        }

        return $ids;
    }
);
$pilot->assertIsArray();
$pilot->assertCount(3);
$pilot->assertEqual(['1', '2', '3']);
//endregion

// as the previous run used by ref values, the auto reset is manual
$ppp->reset();

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('insert_005');
//endregion

//region PLAIN SQL - ALL RECORDS - NO BINARY DATA
$pilot->run(
    id: 'insert_006',
    description: 'Insert all records using plain SQL, without binary data',
    test: function() use ($pilot, $ppp) {
        $insert = $pilot->getResource('insert_plain_sql');
        $ids = [];
        $ids[] = $insert('film1', $ppp);
        $ids[] = $insert('film2', $ppp);
        $ids[] = $insert('film3', $ppp);

        return $ids;
    }
);
$pilot->assertIsArray();
$pilot->assertCount(3);
$pilot->assertEqual(['1', '2', '3']);
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('insert_007');
//endregion

//region BIND VALUES - ALL RECORDS (LOOP) - NO BINARY DATA
$run = 8;
$in = $ppp->getInjectorInByVal();
foreach ($pilot->getResource('films') as $film) {
    $sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
   {$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')}, 
   {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, {$in($film['stock'], 'int')}
);
sql;
    $pilot->run(
        id: 'insert_'.sprintf('%03d', $run++),
        description: 'Insert all records using PDOStatement->bindValue() (loop), without binary data',
        test: fn() => $ppp->insert($sql)
    );
    $pilot->assertIsString();
    $pilot->assertIn(['1', '2', '3']);
}
//endregion