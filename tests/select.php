<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region SELECT PLAIN SQL
$pilot->run(
    id: 'select_001',
    description: 'Select all blu-rays using plain sql',
    test: fn() => $ppp->select("SELECT * FROM t_video WHERE video_support = {$ppp('BLU-RAY')}")
);
$pilot->assertIsArray();
$pilot->assertCount(2);
$pilot->assertEqual([[
    'video_id'           => 1,
    'video_title'        => "The Lord of the Rings - The Fellowship of the Ring",
    'video_support'      => 'BLU-RAY',
    'video_multilingual' => 1,
    'video_chapter'      => 1,
    'video_year'         => 2001,
    'video_summary'      => null,
    'video_stock'        => 10,
    'video_img'          => null
], [
    'video_id'           => 2,
    'video_title'        => "The Lord of the Rings - The two towers",
    'video_support'      => 'BLU-RAY',
    'video_multilingual' => 1,
    'video_chapter'      => 2,
    'video_year'         => 2002,
    'video_summary'      => null,
    'video_stock'        => 0,
    'video_img'          => null
]]);
// endregion

//region SELECT USING ->bindValue()
$in = $ppp->getInjectorInByVal();
$pilot->run(
    id: 'select_002',
    description: 'Select all blu-rays using ->bindValue()',
    test: fn() => $ppp->select("SELECT * FROM t_video WHERE video_support = {$ppp('BLU-RAY')}")
);
$pilot->assertCount(2);
$pilot->assertEqual([[
    'video_id'           => 1,
    'video_title'        => "The Lord of the Rings - The Fellowship of the Ring",
    'video_support'      => 'BLU-RAY',
    'video_multilingual' => 1,
    'video_chapter'      => 1,
    'video_year'         => 2001,
    'video_summary'      => null,
    'video_stock'        => 10,
    'video_img'          => null
], [
    'video_id'           => 2,
    'video_title'        => "The Lord of the Rings - The two towers",
    'video_support'      => 'BLU-RAY',
    'video_multilingual' => 1,
    'video_chapter'      => 2,
    'video_year'         => 2002,
    'video_summary'      => null,
    'video_stock'        => 0,
    'video_img'          => null
]]);
//endregion

//region SELECT USING GENERIC CHAR
$pilot->run(
    id: 'select_003',
    description: 'Select using generic char, LIKE %Lord%',
    test: fn() => $ppp->select("SELECT * FROM t_video WHERE video_title LIKE {$ppp("%Lord%")}")
);
$pilot->assertCount(3);
$pilot->assertEqual($pilot->getResource('db_films'));
//endregion

