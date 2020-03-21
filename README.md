# **PDOPlusPlus : a new generation of PDO Wrapper**

`2020-03-21` `PHP 7+`

## **A PHP full object PDO Wrapper in one class**

`PDOPlusPlus` (alias `PPP`) is a one class PDO Wrapper for PHP with a revolutionary fluid SQL syntax. 
You do not have anymore to use PDO in a classical way, you can completely omit the notion of 
`prepare()`, `bindValue()`, `bindParam()`. The use of these mechanisms is now hidden by `PDOPlusPlus`. 
All you have to do is to write directly a clean SQL query and injecting directly your values.

The engine, will automatically escape the values and will let you concentrate only on the SQL syntax.

If you read french, you will find a complete tutorial with tons of explanations on my blog : [rawsrc](https://www.developpez.net/forums/blogs/32058-rawsrc/b9083/pdoplusplus-ppp-nouvelle-facon-dutiliser-pdo/)
     
 **THE CONCEPT**
 
 The power of `PDOPlusPlus` is directly linked to the way the instance is called as a function using the PHP magic function `__invoke()`
 
 Here's the global scheme for calling the class inside a plain SQL string:
 
 ![PDOPlusPlus Concept](https://github.com/rawsrc/PDOPlusPlus/PDOPlusPlusConcept.png)
 
**HOW TO USE IT**

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

**SAMPLE DATASET**
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

You can work in 3 different modes with `PDOPlusPlus`.
- `PDOPlusPlus::MODE_SQL_DIRECT` : omits the prepare mechanism and escape directly the values
- `PDOPlusPlus::MODE_PREPARE_VALUES` : use the prepare mechanism with `bindValue()`
- `PDOPlusPlus::MODE_PREPARE_PARAMS` : use the prepare mechanism with `bindParam()`

You must define the mode when you create a new instance of `PDOPlusPlus`.

**ADD A RECORD**

Let's add the first movie into the database using `PDOPlusPlus`:
I will use the SQL DIRECT mode omitting the `PDOStatement` step. 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_SQL_DIRECT); // or shortly : new PPP(PPP::MODE_SQL_DIRECT); 
$film = $data[0];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary)
     VALUES ({$ppp($film['title'], false)}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool', false)},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int', false)}, {$ppp($film['summary'])})
sql;
$new_id = $ppp->insert($sql);
```

Let's add the second movie into the database using `PDOPlusPlus`:
I will use a `PDOStatement` based on values (`->bindValue()`). 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_PREPARE_VALUES); // or shortly : new PPP(PPP::MODE_PREPARE_VALUES);
$film = $data[1];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary)
     VALUES ({$ppp($film['title'], false)}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool', false)},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int', false)}, {$ppp($film['summary'])})
sql;
$new_id = $ppp->insert($sql);
```
Look at he the SQL generated internally by `PDOPlusPlus` : 
```sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary) 
     VALUES (:XMEDem6153, :oASqvP7440, :mbfaTY4236, :FJzRWx7446, :FVHvqL4843, :tcCvZo8956);
```

Let's truncate the table and then add the whole list of films at once.
This time, I will use a `PDOStatement` based on references (`->bindParam()`) as there are many iterations to do.
Please note, to pass the references to the `PDOPlusPlus` instance, you **MUST** use the reference injector
returned by `->modePrepareParamsInjector();`. Otherwise it will not work.
```php
include 'PDOPlusPlus.php';
// when there's no parameters, use the MODE_SQL_DIRECT
$ppp = new PPP(PPP::MODE_SQL_DIRECT);
$ppp->execute('TRUNCATE TABLE t_video');

$ppp = new PPP(PPP::MODE_PREPARE_PARAMS);
$inj = $ppp->modePrepareParamsInjector();   // to pass references you MUST use this injector 
$sql = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary)
     VALUES ({$inj($title)}, {$inj($support)}, {$inj($multilingual, 'bool')},
             {$inj($chapter, 'int')}, {$inj($year, 'int')}, {$inj($summary)})
sql;
foreach ($data as $film) {
    extract($film); // destructuring the array into components used to populate the references declared just above
    $ppp->insert($sql); 
}
``` 

**UPDATE A RECORD**

Very simple : 
```php
include 'PDOPlusPlus.php';
$support = 'DVD';
$id      = 1;

$ppp  = new PPP(PPP::MODE_PREPARE_VALUES);
$sql  = <<<sql
UPDATE t_video SET video_support = {$ppp($support)} WHERE video_id = {$ppp($id, 'int')}
sql;
$new_id = $ppp->update($sql);
```

**DELETE A RECORD** 
```php
include 'PDOPlusPlus.php';
$id = 1;

$ppp  = new PPP(PPP::MODE_PREPARE_VALUES);
$sql  = <<<sql
DELETE FROM t_video WHERE video_id = {$ppp($id, 'int')}
sql;
$new_id = $ppp->delete($sql);
```

Hope this will help you to produce in a more comfortable way a better SQL code and use PDO natively in your PHP code.

Enjoy ! 

**rawsrc**  
