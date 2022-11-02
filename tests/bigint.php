<?php declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

// unsigned bigint = 0 TO 18446744073709551615
// signed bigint = -9.223.372.036.854.775.808 TO 9.223.372.036.854.775.807

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('bigint_001');
//endregion

//region INSERTING ONE RECORD PLAIN SQL WITH BIGINT
$film = $pilot->getResource('film1');
$film['video_bigint_unsigned'] = '18446744073709551600';
$film['video_bigint'] = '-9223372036854775000';
$sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock, video_bigint_unsigned, video_bigint
) VALUES (
   {$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')}, 
   {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, 
   {$ppp($film['stock'], 'int')}, {$ppp($film['video_bigint_unsigned'], 'bigint')}, {$ppp($film['video_bigint'], 'bigint')}
);
sql;
$pilot->run(
    id: 'bigint_002',
    test: fn() => $ppp->insert($sql),
    description: 'Inserting one record plain sql with signed and unsigned bigint'
);
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region INSERTING ONE RECORD BIND VALUE WITH BIGINT
$film = $pilot->getResource('film2');
$film['video_bigint_unsigned'] = '18446744073709551600';
$film['video_bigint'] = '-9223372036854775000';
$in = $ppp->getInjectorInByVal();
$sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock, video_bigint_unsigned, video_bigint
) VALUES (
   {$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')}, 
   {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, 
   {$in($film['stock'], 'int')}, {$in($film['video_bigint_unsigned'], 'bigint')}, {$in($film['video_bigint'], 'bigint')}
);
sql;
$pilot->run(
    id: 'bigint_003',
    test: fn() => $ppp->insert($sql),
    description: 'Inserting one record using binding value with signed and unsigned bigint'
);
$pilot->assertIsString();
$pilot->assertEqual('2');
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('bigint_004');
//endregion

//region BIND PARAMS - ALL RECORDS - SIGNED AND UNSIGNED BIGINT
$in = $ppp->getInjectorInByRef();
$films = $pilot->getResource('films');
$sql = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock, video_bigint_unsigned, video_bigint
) VALUES (
    {$in($title)}, {$in($support)}, {$in($multilingual, 'bool')}, {$in($chapter, 'int')}, {$in($year, 'int')}, 
    {$in($summary)}, {$in($stock, 'int')}, {$in($bigint_unsigned, 'bigint')}, {$in($bigint_signed, 'bigint')} 
);
sql;
$pilot->run(
    id: 'bigint_005',
    description: 'Insert all records using PDOStatement->bindParam(),with signed and unsigned bigint',
    test: function() use ($ppp, $films, $sql, &$title, &$support, &$multilingual, &$chapter, &$year, &$summary, &$stock, &$bigint_unsigned, &$bigint_signed) {
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

$ppp->reset();

//region SELECT PLAIN SQL
$pilot->run(
    id: 'bigint_006',
    description: 'Select all blu-rays using plain sql with bigint',
    test: fn() => $ppp->select("SELECT * FROM t_video WHERE video_support = {$ppp('BLU-RAY')}")
);
$pilot->assertIsArray();
$pilot->assertCount(2);
$pilot->assertEqual([[
    'video_id'              => 1,
    'video_title'           => "The Lord of the Rings - The Fellowship of the Ring",
    'video_support'         => 'BLU-RAY',
    'video_multilingual'    => 1,
    'video_chapter'         => 1,
    'video_year'            => 2001,
    'video_summary'         => null,
    'video_stock'           => 10,
    'video_img'             => null,
    'video_bigint_unsigned' => '18446744073709551600',
    'video_bigint'          => -9223372036854775000,
], [
    'video_id'              => 2,
    'video_title'           => "The Lord of the Rings - The two towers",
    'video_support'         => 'BLU-RAY',
    'video_multilingual'    => 1,
    'video_chapter'         => 2,
    'video_year'            => 2002,
    'video_summary'         => null,
    'video_stock'           => 0,
    'video_img'             => null,
    'video_bigint_unsigned' => '18446744073709551600',
    'video_bigint'          => -9223372036854775000,
]]);
// endregion

//region UPDATE BIGINT
$pilot->run(
    id: 'bigint_007',
    description: 'Update bingint value',
    test: fn() => $ppp->update("UPDATE t_video SET video_bigint_unsigned = {$ppp('18000000000000000000', 'bigint')} WHERE video_id = {$ppp(1, 'int')}")
);
$pilot->assertIsInt();
$pilot->assertEqual(1);

$pilot->run(
    id: 'bigint_008',
    description: 'Select last updated record',
    test: fn() => $ppp->select("SELECT * FROM t_video WHERE video_bigint_unsigned = {$ppp('18000000000000000000', 'bigint')}")
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assertEqual([[
    'video_id'              => 1,
    'video_title'           => "The Lord of the Rings - The Fellowship of the Ring",
    'video_support'         => 'BLU-RAY',
    'video_multilingual'    => 1,
    'video_chapter'         => 1,
    'video_year'            => 2001,
    'video_summary'         => null,
    'video_stock'           => 10,
    'video_img'             => null,
    'video_bigint_unsigned' => '18000000000000000000',
    'video_bigint'          => -9223372036854775000,
]]);
//endregion

