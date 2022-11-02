<?php declare(strict_types=1);

use Exacodis\Pilot;
use rawsrc\PDOPlusPlus\InjectorInByRef;
use rawsrc\PDOPlusPlus\PDOPlusPlus;

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

//region TRUNCATE THE TABLE
$pilot->getResource('run_truncate_the_table')('stored_procedure_001');
//endregion

$ppp->reset();

//region INSERT ALL RECORDS - NO BINARY DATA
$pilot->run(
    id: 'stored_procedure_002',
    description: 'Insert all films, without binary data',
    test: fn() => $pilot->getResource('insert_all_plain_sql')($ppp)
);
$pilot->assertIsArray();
$pilot->assertEqual(['1', '2', '3']);
//endregion

//region CREATE A STORED PROCEDURE THAT RETURNS ONE ROWSET
$pilot->run(
    id: 'stored_procedure_003',
    description: 'Create a stored procedure that returns one rowset',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films()
BEGIN
    SELECT * FROM t_video;
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP
$pilot->run(
    id: 'stored_procedure_004',
    description: 'Call SP that returns one rowset (all films, 3 records)',
    test: fn() => $ppp->call('CALL sp_list_films()', true)
);
$pilot->assertIsArray();
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 3,
    test_name: 'Count the records',
    expected: 3
);
//endregion

//region CREATE A STORED PROCEDURE THAT RETURNS TWO ROWSET
$pilot->run(
    id: 'stored_procedure_005',
    description: 'Create a stored procedure that returns two rowset',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films_group_by_support()
BEGIN
    SELECT * FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT * FROM t_video WHERE video_support = 'DVD';
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP
$pilot->run(
    id: 'stored_procedure_006',
    description: 'Call SP that returns two rowsets (blu-rays + dvd)',
    test: fn() => $ppp->call('CALL sp_list_films_group_by_support()', true)
);
$pilot->assertIsArray();
$pilot->assertCount(2);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 2,
    test_name: 'Count the blu-rays',
    expected: 2
);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[1]) === 1,
    test_name: 'Count the dvd',
    expected: 1
);
//endregion

//region CREATE A STORED PROCEDURE WITH ONE IN PARAMETER
$pilot->run(
    id: 'stored_procedure_007',
    description: 'Create a stored procedure with one in parameter',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films_one_in_param(p_support VARCHAR(30))
BEGIN
    SELECT * FROM t_video WHERE video_support = p_support;
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP PLAIN SQL PARAM
$pilot->run(
    id: 'stored_procedure_008',
    description: 'Call SP with one in parameter: all dvds, plain sql escape',
    test: fn() => $ppp->call("CALL sp_list_films_one_in_param({$ppp('DVD')})", true)
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()) === 1,
    test_name: 'Count the dvd',
    expected: 1
);
//endregion

//region CALL SP BOUND BY VAL PARAM
$pilot->run(
    id: 'stored_procedure_009',
    description: 'Call SP with one in parameter: all blu-rays, using PDOStatement->bindValue()',
    test: function() use ($ppp) {
        $in = $ppp->getInjectorInByVal('str');
        return $ppp->call("CALL sp_list_films_one_in_param({$in('Blu-ray')})", true);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 2,
    test_name: 'Count the blu-rays',
    expected: 2
);
//endregion

//region CALL SP BOUND BY REF PARAM
$pilot->run(
    id: 'stored_procedure_010',
    description: 'Call SP with one in parameter: all blu-rays, using PDOStatement->bindRef()',
    test: function() use ($ppp) {
        $support = 'Blu-ray';
        $in = $ppp->getInjectorInByRef('str');
        return $ppp->call("CALL sp_list_films_one_in_param({$in($support)})", true);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 2,
    test_name: 'Count the blu-rays',
    expected: 2
);
//endregion

$ppp->reset();

//region CREATE A STORED PROCEDURE WITH TWO IN PARAMETER
$pilot->run(
    id: 'stored_procedure_011',
    description: 'Create a stored procedure with two in parameter (support and year)',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE 
    db_pdo_plus_plus.sp_list_films_two_in_param(p_support VARCHAR(30), p_year INT)
BEGIN
    SELECT * FROM t_video WHERE video_support = p_support AND video_year = p_year;
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP PLAIN SQL PARAM
$pilot->run(
    id: 'stored_procedure_012',
    description: 'Call SP with two in parameter: all dvds, year 2001, plain sql escape',
    test: fn() => $ppp->call("CALL sp_list_films_two_in_param({$ppp('DVD')}, {$ppp('2001', 'int')})", true)
);
$pilot->assertIsArray();
$pilot->assertCount(0);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()) === 0,
    test_name: 'Count the dvd of year 2001 (zero)',
    expected: 0
);
//endregion

//region CALL SP BOUND BY VAL PARAM
$pilot->run(
    id: 'stored_procedure_013',
    description: 'Call SP with two in parameter: all blu-rays, year 2002, using PDOStatement->bindValue()',
    test: function() use ($ppp) {
        $in = $ppp->getInjectorInByVal();
        return $ppp->call("CALL sp_list_films_two_in_param({$in('Blu-ray')}, {$in('2002', 'int')})", true);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 1,
    test_name: 'Count the blu-rays',
    expected: 1
);
//endregion

//region CALL SP BOUND BY REF PARAM
$pilot->run(
    id: 'stored_procedure_014',
    description: 'Call SP with two in parameter: all blu-rays, year 2001, using PDOStatement->bindRef()',
    test: function() use ($ppp) {
        $support = 'Blu-ray';
        $year = 2001;
        $in = $ppp->getInjectorInByRef();
        return $ppp->call("CALL sp_list_films_two_in_param({$in($support)}, {$in($year, 'int')})", true);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()[0]) === 1,
    test_name: 'Count the blu-rays',
    expected: 1
);
//endregion

$ppp->reset();

//region CREATE A STORED PROCEDURE WITH ONE OUT PARAMETER
$pilot->run(
    id: 'stored_procedure_015',
    description: 'Create a stored procedure with one out parameter',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_one_out_param(OUT p_nb INT)
BEGIN
    SELECT COUNT(video_id) INTO p_nb FROM t_video;
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP OUT PARAM
$pilot->run(
    id: 'stored_procedure_016',
    description: 'Call SP with one out parameter',
    test: function() use ($ppp) {
        $out = $ppp->getInjectorOut();
        return $ppp->call("CALL sp_nb_films_one_out_param({$out('@nb')})", false);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()['out']) === 1,
    test_name: 'Count the number of out params',
    expected: 1
);
$pilot->assert(
    test: fn() => $pilot->getRunner()->getResult()['out']['@nb'] === 3,
    test_name: 'Count the videos',
    expected: 3
);
//endregion

//region CREATE A STORED PROCEDURE WITH TWO OUT PARAMETER
$pilot->run(
    id: 'stored_procedure_017',
    description: 'Create a stored procedure with two out parameters',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_two_out_param(OUT p_nb_blu_ray INT,
 OUT p_nb_dvd INT)
BEGIN
    SELECT COUNT(video_id) INTO p_nb_blu_ray FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT COUNT(video_id) INTO p_nb_dvd FROM t_video WHERE video_support = 'DVD';
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP OUT PARAM
$pilot->run(
    id: 'stored_procedure_018',
    description: 'Call SP with two out parameters',
    test: function() use ($ppp) {
        $out = $ppp->getInjectorOut();
        return $ppp->call("CALL sp_nb_films_two_out_param({$out('@nb_blu_ray')}, {$out('@nb_dvd')})", false);
    }
);
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($pilot->getRunner()->getResult()['out']) === 2,
    test_name: 'Count the number of out params',
    expected: 1
);
$pilot->assert(
    test: fn() => $pilot->getRunner()->getResult()['out']['@nb_blu_ray'] === 2,
    test_name: 'Count the blu-ray',
    expected: 2
);
$pilot->assert(
    test: fn() => $pilot->getRunner()->getResult()['out']['@nb_dvd'] === 1,
    test_name: 'Count the dvd',
    expected: 1
);
//endregion

//region CREATE A STORED PROCEDURE WITH A ROWSET AND TWO OUT PARAMETERS
$pilot->run(
    id: 'stored_procedure_019',
    description: 'Create a stored procedure with a rowset and two out parameters',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_rowset_two_out_param(OUT p_nb_blu_ray 
INT,
 OUT p_nb_dvd INT)
BEGIN
    SELECT * FROM t_video ORDER BY video_year ASC;
    SELECT COUNT(video_id) INTO p_nb_blu_ray FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT COUNT(video_id) INTO p_nb_dvd FROM t_video WHERE video_support = 'DVD';
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP ROWSET + OUT PARAM
$pilot->run(
    id: 'stored_procedure_020',
    description: 'Call SP with a rowset and two out parameters',
    test: function() use ($ppp) {
        $out = $ppp->getInjectorOut();
        return $ppp->call("CALL sp_nb_films_rowset_two_out_param({$out('@nb_blu_ray')}, {$out('@nb_dvd')})", true);
    }
);
$result = $pilot->getRunner()->getResult();
$pilot->assertIsArray();
$pilot->assertCount(2);
$pilot->assert(
    test: fn() => count($result['out']) === 2,
    test_name: 'Count the number of out params',
    expected: 2
);
$pilot->assert(
    test: fn() => $result['out']['@nb_blu_ray'] === 2,
    test_name: 'Count the blu-ray',
    expected: 2
);
$pilot->assert(
    test: fn() => $result['out']['@nb_dvd'] === 1,
    test_name: 'Count the dvd',
    expected: 1
);
$pilot->assert(
    test: fn() => count($result[0]) === 3,
    test_name: 'Count the number of videos',
    expected: 3
);
$pilot->assert(
    test: fn() => $result[0] === $pilot->getResource('db_films'),
    test_name: 'Check the returned records',
    expected: $pilot->getResource('db_films')
);
//endregion

//region CREATE A STORED PROCEDURE WITH A ROWSET ONE INOUT AND TWO OUT PARAMETERS
$pilot->run(
    id: 'stored_procedure_021',
    description: 'Create a stored procedure with a rowset and one inout parameter and two out parameters',
    test: fn() => $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_one_inout_two_out_param(
INOUT p_qty INT, OUT p_nb_blu_ray INT, OUT p_nb_dvd INT)
BEGIN
    DECLARE v_nb INT;
    SELECT SUM(video_stock) INTO v_nb FROM t_video;
    SET p_qty = v_nb - p_qty;
    SELECT COUNT(video_id) INTO p_nb_blu_ray FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT COUNT(video_id) INTO p_nb_dvd FROM t_video WHERE video_support = 'DVD';
END;
sql));
$pilot->assertNotException();
//endregion

//region CALL SP ROWSET + OUT PARAM
$pilot->run(
    id: 'stored_procedure_022',
    description: 'Call SP with a rowset and two out parameters',
    test: function() use ($ppp) {
        $io = $ppp->getInjectorInOut();
        $out = $ppp->getInjectorOut();
        return $ppp->call("CALL sp_nb_films_one_inout_two_out_param({$io('25', '@stock', 'int')}, {$out('@nb_blu_ray')}, {$out('@nb_dvd')})", false);
    }
);
$result = $pilot->getRunner()->getResult();
$pilot->assertIsArray();
$pilot->assertCount(1);
$pilot->assert(
    test: fn() => count($result['out']) === 3,
    test_name: 'Count the number of out params',
    expected: 3
);
$pilot->assert(
    test: fn() => $result['out']['@stock'] === -14,
    test_name: 'Check the stock',
    expected: 14
);
$pilot->assert(
    test: fn() => $result['out']['@nb_blu_ray'] === 2,
    test_name: 'Count the blu-ray',
    expected: 2
);
$pilot->assert(
    test: fn() => $result['out']['@nb_dvd'] === 1,
    test_name: 'Count the dvd',
    expected: 1
);
//endregion
