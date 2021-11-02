<?php

declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('binary_001');
//endregion

//region INSERT FILM 1 USING PLAIN SQL WITH BINARY DATA
$path = './img/loftr1web.jpg';
$size = filesize($path);
$file = fopen($path, 'rb');
$bin_img = fread($file, $size);
fclose($file);

$film = $pilot->getResource('film1');
$pilot->run(
    id: 'binary_002',
    description: 'Insert the first movie using plain sql with binary data',
    test: function() use ($ppp, $film, $bin_img) {
        $sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock, video_img
) VALUES (
   {$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')}, 
   {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, 
   {$ppp($film['stock'], 'int')}, {$ppp($bin_img, 'binary')}   
);
sql;
    return $ppp->insert($sql);
});
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

//region READ BINARY DATA
$pilot->run(
    id: 'binary_003',
    description: 'Read binary from table and compare to the original file',
    test: function() use ($ppp) {
        return $ppp->select("SELECT video_img FROM t_video");
    }
);
$pilot->assertIsArray();
$data = $pilot->getRunner()->getResult();
$image = $data[0]['video_img'];
$pilot->assert(
    test: fn() => $image === $bin_img,
    test_name: 'Compare extracted binary field to the original binary file',
    expected: 'Same files'
);
// here if you want to test in the browser
//$b64 = base64_encode($image);
//echo <<<html
//<body>
//<img src="data:image/jpeg;base64,{$b64}">
//</body>
//html;
//endregion

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('binary_004');
//endregion

//region INSERT FILM 1 USING PLAIN SQL AND BOUND PARAMETER FOR BINARY DATA
//$path = './img/loftr1web.jpg';
//$size = filesize($path);
//$file = fopen($path, 'rb');
//$bin_img = fread($file, $size);
//fclose($file);
$film = $pilot->getResource('film1');
$in = $ppp->getInjectorInByRef('binary'); // we lock the type
$pilot->run(
    id: 'binary_005',
    description: 'Insert the first movie using plain sql and bound parameter for binary data',
    test: function() use ($ppp, $film, $bin_img, $in) {
        $sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock, video_img
) VALUES (
   {$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')}, 
   {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, 
   {$ppp($film['stock'], 'int')}, {$in($bin_img, 'binary')}   
);
sql;
        return $ppp->insert($sql);
    });
$pilot->assertIsString();
$pilot->assertEqual('1');
//endregion

$ppp->reset();

//region READ BINARY DATA
$pilot->run(
    id: 'binary_006',
    description: 'Read binary from table and compare to the original file',
    test: fn() => $ppp->select("SELECT video_img FROM t_video")
);
$pilot->assertIsArray();
$data = $pilot->getRunner()->getResult();
$image = $data[0]['video_img'];
$pilot->assert(
    test: fn() => $image === $bin_img,
    test_name: 'Compare extracted binary field to the original binary file',
    expected: 'Same files'
);
// here if you want to test in the browser
//$b64 = base64_encode($image);
//echo <<<html
//<body>
//<img src="data:image/jpeg;base64,{$b64}">
//</body>
//html;
//endregion

//region SELECT BINARY DATA USING BOUND COLUMN
$columns = [
    'video_title' => [&$video_title, 'str'],
    'video_img' => [&$video_img, 'binary'],
];
$pilot->run(
    id: 'binary_007',
    description: 'Select using bound columns',
    test: function() use ($ppp, &$columns) {
        $ppp->setBoundColumns($columns);

        return $ppp->selectStmt("SELECT video_title, video_img FROM t_video WHERE video_id = {$ppp(1, 'int')}");
    }
);
$pilot->assertIsInstanceOf(PDOStatement::class);
$stmt = $pilot->getRunner()->getResult();
$stmt->fetch(PDO::FETCH_BOUND);
$pilot->assert(
    test: fn() => $video_title === 'The Lord of the Rings - The Fellowship of the Ring',
    test_name: 'Check the bound $video_title value',
    expected: 'The Lord of the Rings - The Fellowship of the Ring'
);
$pilot->assert(
    test: fn() => $video_img === $bin_img,
    test_name: 'Check the bound $video_img binary value',
    expected: 'Same as the original binary image'
);
//endregion
