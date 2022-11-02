<?php declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\InjectorInByRef;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region SETUP TEST DATA
$pilot->addResource('film1', [
    'title'           => "The Lord of the Rings - The Fellowship of the Ring",
    'support'         => 'BLU-RAY',
    'multilingual'    => true,
    'chapter'         => 1,
    'year'            => 2001,
    'summary'         => null,
    'stock'           => 10,
    'bigint_unsigned' => '18446744073709551600',
    'bigint_signed'   => '-9223372036854775000',
]);

$pilot->addResource('film2', [
    'title'           => "The Lord of the Rings - The two towers",
    'support'         => 'BLU-RAY',
    'multilingual'    => true,
    'chapter'         => 2,
    'year'            => 2002,
    'summary'         => null,
    'stock'           => 0,
    'bigint_unsigned' => '18446744073709551600',
    'bigint_signed'   => '-9223372036854775000',
]);

$pilot->addResource('film3', [
    'title'           => "The Lord of the Rings - The return of the King",
    'support'         => 'DVD',
    'multilingual'    => true,
    'chapter'         => 3,
    'year'            => 2003,
    'summary'         => null,
    'stock'           => 1,
    'bigint_unsigned' => '18446744073709551600',
    'bigint_signed'   => '-9223372036854775000',
]);

$pilot->addResource('films', [
    'film1' => $pilot->getResource('film1'),
    'film2' => $pilot->getResource('film2'),
    'film3' => $pilot->getResource('film3'),
]);

$pilot->addResource('db_films', [[
    'video_id'              => 1,
    'video_title'           => "The Lord of the Rings - The Fellowship of the Ring",
    'video_support'         => 'BLU-RAY',
    'video_multilingual'    => 1,
    'video_chapter'         => 1,
    'video_year'            => 2001,
    'video_summary'         => null,
    'video_stock'           => 10,
    'video_img'             => null,
    'video_bigint_unsigned' => null,
    'video_bigint'          => null,
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
    'video_bigint_unsigned' => null,
    'video_bigint'          => null,
], [
    'video_id'              => 3,
    'video_title'           => "The Lord of the Rings - The return of the King",
    'video_support'         => 'DVD',
    'video_multilingual'    => 1,
    'video_chapter'         => 3,
    'video_year'            => 2003,
    'video_summary'         => null,
    'video_stock'           => 1,
    'video_img'             => null,
    'video_bigint_unsigned' => null,
    'video_bigint'          => null,
]]);

$pilot->addResource('db_films_bigint', [[
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
    'video_bigint'          => '-9223372036854775000',
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
    'video_bigint'          => '-9223372036854775000',
], [
    'video_id'              => 3,
    'video_title'           => "The Lord of the Rings - The return of the King",
    'video_support'         => 'DVD',
    'video_multilingual'    => 1,
    'video_chapter'         => 3,
    'video_year'            => 2003,
    'video_summary'         => null,
    'video_stock'           => 1,
    'video_img'             => null,
    'video_bigint_unsigned' => '18446744073709551600',
    'video_bigint'          => '-9223372036854775000',
]]);
//endregion SETUP TEST DATA

//region COMMON ACTIONS
//region insert film - plain sql
$pilot->addResource('insert_plain_sql', function(string $film_resource_id, PDOPlusPlus $ppp) use ($pilot) {
    $film = $pilot->getResource($film_resource_id);
    $sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
   {$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')}, 
   {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, {$ppp($film['stock'], 'int')}
);
sql;
    return $ppp->insert($sql);
});
//endregion

//region insert film - PDOStatement->bindValue()
$pilot->addResource('insert_bind_values', function(string $film_resource_id, PDOPlusPlus $ppp) use ($pilot) {
    $film = $pilot->getResource($film_resource_id);
    $in = $ppp->getInjectorInByVal();
    $sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
   {$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')}, 
   {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, {$in($film['stock'], 'int')}
);
sql;
    return $ppp->insert($sql);
});
//endregion

//region insert all films - plain sql
$pilot->addResource('insert_all_plain_sql', function(PDOPlusPlus $ppp) use ($pilot) {
    $ids = [];
    $insert = $pilot->getResource('insert_plain_sql');
    $ids[] = $insert('film1', $ppp);
    $ids[] = $insert('film2', $ppp);
    $ids[] = $insert('film3', $ppp);

    return $ids;
});
//endregion

//region exacodis run : truncate the table
$pilot->addResource('run_truncate_the_table', function(string $test_id, string $description = 'Truncate the table') use ($pilot, $ppp) {
    $pilot->run(
        id: $test_id,
        description: $description,
        test: fn() => $ppp->execute('TRUNCATE TABLE t_video')
    );
    $pilot->assertNotException();
});
//endregion
//endregion COMMON ACTIONS