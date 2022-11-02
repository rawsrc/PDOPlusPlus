<?php declare(strict_types=1);

/** @var Pilot $pilot */
/** @var PDOPlusPlus $ppp */

use rawsrc\PDOPlusPlus\PDOPlusPlus;

//region DROP DB + CREATE DB AND TABLE

$sql = <<<sql
DROP DATABASE IF EXISTS db_pdo_plus_plus;
CREATE DATABASE db_pdo_plus_plus;
USE db_pdo_plus_plus;
CREATE TABLE t_video
(
    video_id              int auto_increment primary key,
    video_title           varchar(255)         not null,
    video_support         varchar(30)          not null comment 'DVD DIVX BLU-RAY',
    video_multilingual    tinyint(1) default 0 not null,
    video_chapter         int                  null,
    video_year            int                  not null,
    video_summary         text                 null,
    video_stock           int        default 0 not null,
    video_img             mediumblob           null, 
    video_bigint_unsigned bigint unsigned      null,
    video_bigint          bigint               null,
    constraint t_video_video_titre_index
        unique (video_title)
);

create index t_video_video_support_index
    on t_video (video_support);
sql;

$pilot->run(
    id: 'main_001',
    description: 'Drop DB + create DB and TABLE using the default connection',
    test: fn() => $ppp->execute($sql)
);
$pilot->assertIsInt();

$ppp = new PDOPlusPlus('user_root');
$pilot->run(
    id: 'main_002',
    description: 'Drop DB + create DB and TABLE using a named connection',
    test: fn() => $ppp->execute($sql)
);
$pilot->assertIsInt();

$ppp = new PDOPlusPlus('unknown_connection');
$pilot->run(
    id: 'main_005',
    description: 'Try to drop DB + create DB and TABLE using an unknown connection',
    test: fn() => $ppp->execute($sql)
);
$pilot->assertException();
//endregion

// we clean the instance and all previous errors
$ppp->reset();

// we set the default and the current connection to the one with the database defined
PDOPlusPlus::setDefaultConnection('db_user_ok');
$ppp->setCurrentConnection('db_user_ok');
