# **PDOPlusPlus : a new generation of PDO Wrapper**

`2020-03-21` `PHP 7+`

## **A PHP full object PDO Wrapper in one class**

`PDOPlusPlus` (alias `PPP`) is a one class PDO Wrapper for PHP with a revolutionary fluid syntax. 
You do not have anymore to use PDO in a classical way, you can completely omit the notion of 
`prepare()`, `bindValue()`, `bindParam()`. The use of these mechanisms is now hidden by PDOPlusPlus. 
All you have to do is to write directly a clean SQL query and injecting directly your values.

The engine, will automatically escape the values and will let you concentrate only on the SQL syntax.

If you read french, you will find a complete tutorial with tons of explanations on my blog : [rawsrc](https://www.developpez.net/forums/blogs/32058-rawsrc/b9083/pdoplusplus-ppp-nouvelle-facon-dutiliser-pdo/)
     
**How to use it**

As written, `PDOPlusPlus` is as PDO Wrapper, so it will have to connect to your database using PDO of course.
So the first step is to provide the connexion parameter to the class. It's highly recommended to use constants : 
```php
define('DB_SCHEME', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_pdo_plus_plus');
define('DB_USER', '');       // please fill here
define('DB_PWD', '');        // please fill here
define('DB_PORT', '3306');
define('DB_TIMEOUT', '5');
```
You can also add some personal parameters to the connexion, see `private static function connect()`.

For the course, i will use a very simple database of one table :
```sql
create or replace table db_pdo_plus_plus.t_video
(
	video_id int auto_increment primary key,
	video_title varchar(255) not null,
	video_support varchar(30) not null comment 'DVD DIVX BLU-RAY',
	video_multilingual tinyint(1) default 0 not null,
	video_chapter int null,
	video_year int not null,
	video_summary text null
);   
```
You can work in 3 different modes with `PDOPlusPlus`.
- `PDOPlusPlus::MODE_SQL_DIRECT` : omits the prepare mechanism and escape directly the values
- `PDOPlusPlus::MODE_PREPARE_VALUES` : use the prepare mechanism with `bindValue()`
- `PDOPlusPlus::MODE_PREPARE_PARAMS` : use the prepare mechanism with `bindParam()`

You must define the mode when you create a new instance of `PDOPlusPlus`.

**DATASET SAMPLE**
```php
$data = [[
    'title'        => "The Lord of the Rings - The Fellowship of the Ring",
    'support'      => 'BLU-RAY',
    'multilingual' => true,
    'chapter'      => 1,
    'year'         => 2001,
    'summary'      => null
], [
    'title'        => "The Lord of the Rings - The Two Towers",
    'support'      => 'BLU-RAY',
    'multilingual' => true,
    'chapter'      => 2,
    'year'         => 2002,
    'summary'      => null
], [
    'title'        => "The Lord of the Rings - The Return of the King",
    'support'      => 'DVD',
    'multilingual' => true,
    'chapter'      => 3,
    'year'         => 2003,
    'summary'      => null
]];
```
**ADD A RECORD**

Add the first movie into the database using `PDOPlusPlus`:


