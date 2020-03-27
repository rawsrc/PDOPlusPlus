# **PDOPlusPlus : a new generation of PDO Wrapper**

`2020-03-27` `PHP 7+`

## **A PHP full object PDO Wrapper in one class**

`PDOPlusPlus` (alias `PPP`) is **a one class PDO Wrapper for PHP** with a
 revolutionary fluid SQL syntax. 
You do not have anymore to use PDO in a classical way, you can completely omit the notions of 
`prepare()`, `bindValue()`, `bindParam()`. The usage of these mechanisms is now hidden by `PDOPlusPlus`. 
All you have to do is to write directly a clean SQL query and inject directly your values.

The engine, will automatically escape the values and will let you concentrate only on the SQL syntax.

`PDOPlusPlus` is compliant with:
- INSERT
- UPDATE
- DELETE
- SELECT
- STORED PROCEDURE

For stored procedures, you'll be able to use any `IN`, `OUT` or `INOUT` params.<br>
`PDOPlusPlus` is also fully compatible with those returning multiple dataset
 at once.

If you read french, you will find a complete tutorial with tons of explanations on my blog : [rawsrc](https://www.developpez.net/forums/blogs/32058-rawsrc/b9083/pdoplusplus-ppp-nouvelle-facon-dutiliser-pdo/)
     
### **THE CONCEPT**
 
The power of `PDOPlusPlus` is directly linked to the way the instance is
 called as a function using the PHP magic function `__invoke()`<br>
All you have to choose is the right injector to write the SQL and at
 the same time pass the different values to the query.<br>
Here's the global scheme for the standard injector:
 
 ![PDOPlusPlus Concept](/PDOPlusPlusConcept.png)

There's two other specific injectors for stored procedure having `OUT` or
 `INOUT` params.<br><br>
 ![PDOPlusPlus Out](/PDOPlusPlusOut.png)
 ![PDOPlusPlus InOut](/PDOPlusPlusInOut.png)

On error, any function will just log internally the system error using `error_log()` and return `null`.
 
### **WHY PDOPlusPlus ?**

Just because, this:
```php
try {
    $film = [
        'title'        => "The Lord of the Rings - The Fellowship of the Ring",
        'support'      => 'BLU-RAY',
        'multilingual' => true,
        'chapter'      => 1,
        'year'         => 2001,
        'summary'      => null,
        'stock'        => 10
    ];

    $pdo = new PDO("mysql:host=HOST;dbname=DB;port=PORT;connect_timeout=TIMEOUT;", "USER", "PWD", [
       PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES   => false
    ]);
    
    $sql = <<<'sql'
    INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock) 
         VALUES (:video_title, :video_support, :video_multilingual, :video_chapter, :video_year, :video_summary, :video_stock)
    sql;

    $stmt = $pdo->prepare($sql);
    
    $stmt->bindValue(':video_title', $film['title']);
    $stmt->bindValue(':video_support', $film['support']);
    $stmt->bindValue(':video_multilingual', (bool)$film['multilingual'], PDO::PARAM_BOOL);
    if ($film['chapter'] === null) {
        $stmt->bindValue(':video_chapter', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':video_chapter', (int)$film['chapter'], PDO::PARAM_INT);
    }   
    $stmt->bindValue(':video_year', (int)$film['chapter'], PDO::PARAM_INT);
    if ($film['summary'] === null) {
        $stmt->bindValue(':video_summary', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':video_summary', $film['summary']);
    }   
    $stmt->bindValue(':video_stock', (int)$film['stock'], PDO::PARAM_INT);
    
    $stmt->execute();
    $id = $pdo->lastInsertId();
} catch (PDOException $e) {
    throw $e;
}
```
is replaced by:
```php
$film = [
    'title'        => "The Lord of the Rings - The Fellowship of the Ring",
    'support'      => 'BLU-RAY',
    'multilingual' => true,
    'chapter'      => 1,
    'year'         => 2001,
    'summary'      => null,
    'stock'        => 10
];

$ppp = new PPP(PPP::MODE_PREPARE_VALUES);
$sql  = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
   {$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')}, 
   {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, {$ppp($film['stock'], 'int')}
);
sql;
$id = $ppp->insert($sql);
```

### **HOW TO USE IT**

As written, `PDOPlusPlus` is as PDO Wrapper, so it will have to connect to your database using PDO of course.
So the first step is to provide the connexion parameters to the class. It's highly recommended to use constants : 
```php
define('DB_SCHEME', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_pdo_plus_plus');
define('DB_USER', '');       // please fill here
define('DB_PWD', '');        // please fill here
define('DB_PORT', '3306');
define('DB_TIMEOUT', '5');
define('DB_PDO_PARAMS', []);
define('DB_DSN_PARAMS', []);
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
	video_summary text null,
	video_stock int not null
);   
``` 

### **SAMPLE DATASET**
```php
$data = [[
    'title'        => "The Lord of the Rings - The Fellowship of the Ring",
    'support'      => 'BLU-RAY',
    'multilingual' => true,
    'chapter'      => 1,
    'year'         => 2001,
    'summary'      => null,
    'stock'        => 10
], [
    'title'        => "The Lord of the Rings - The two towers",
    'support'      => 'BLU-RAY',
    'multilingual' => true,
    'chapter'      => 2,
    'year'         => 2002,
    'summary'      => null,
    'stock'        => 0
], [
    'title'        => "The Lord of the Rings - The return of the King",
    'support'      => 'DVD',
    'multilingual' => true,
    'chapter'      => 3,
    'year'         => 2003,
    'summary'      => null,
    'stock'        => 1
]];
```
### **3 DIFFERENT MODES**
When you create a new instance of PPP, you must choose between 3 different modes:
- `PDOPlusPlus::MODE_SQL_DIRECT`: omits the preparation mechanism and escape directly the values
- `PDOPlusPlus::MODE_PREPARE_VALUES`: use the preparation mechanism with `bindValue()`
- `PDOPlusPlus::MODE_PREPARE_PARAMS`: use the preparation mechanism with `bindParam()`

### **ADD A RECORD**
Let's add the first movie into the database using `PDOPlusPlus`:<br>
I will use the SQL DIRECT mode omitting the `PDOStatement` step. 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_SQL_DIRECT); // or shortly : new PPP(PPP::MODE_SQL_DIRECT); 
$film = $data[0];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, {$ppp($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = 1 (lastInsertId())
```
Let's add the second movie into the database using `PDOPlusPlus`:<br>
I will use a `PDOStatement` based on values (`->bindValue()`). 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_PREPARE_VALUES); // or shortly : new PPP(PPP::MODE_PREPARE_VALUES);
$film = $data[1];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual
, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, {$ppp($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = 2 (lastInsertId())
```
Look at he the SQL generated internally by `PDOPlusPlus` : 
```sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock) 
     VALUES (:XMEDem6153, :oASqvP7440, :mbfaTY4236, :FJzRWx7446, :FVHvqL4843, :tcCvZo8956, :JRtazM4176);
```
Please note that between the 2 modes, you've just switched from `$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_SQL_DIRECT);` to
`$ppp  = new PDOPlusPlus(PDOPlusPlus::MODE_PREPARE_VALUES);`. <br>
The rest of the code remain unchanged whereas internally, the generated code is completely different!<br><br>

Let's truncate the table and then add the whole list of films at once.<br>
This time, I will use a `PDOStatement` based on references (`->bindParam()`) as there are many iterations to do.

Please note, to pass the references to the `PDOPlusPlus` instance, you **MUST** use a specific reference injector
returned by `->injector();`. Otherwise it will not work.
```php
include 'PDOPlusPlus.php';
// when there's no parameters, use the MODE_SQL_DIRECT
$ppp = new PPP(PPP::MODE_SQL_DIRECT);
$ppp->execute('TRUNCATE TABLE t_video');

$ppp = new PPP(PPP::MODE_PREPARE_PARAMS);
$in  = $ppp->injector();   // to pass references you **MUST** use this reference injector 
$sql = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$in($title)}, {$in($support)}, {$in($multilingual, 'bool')}, {$in($chapter, 'int')}, {$in($year, 'int')}, 
             {$in($summary)}, {$in($stock, 'int')})
sql;
foreach ($data as $film) {
    extract($film); // destructuring the array into components used to populate the references declared just above
    $ppp->insert($sql); 
}
``` 
### **UPDATE A RECORD**
Very simple : 
```php
include 'PDOPlusPlus.php';
$support = 'DVD';
$id      = 1;

$ppp = new PPP(PPP::MODE_PREPARE_VALUES);
$sql = "UPDATE t_video SET video_support = {$ppp($support)} WHERE video_id = {$ppp($id, 'int')}";
$nb  = $ppp->update($sql);  // nb of affected rows
```
### **DELETE A RECORD** 
```php
include 'PDOPlusPlus.php';
$id = 1;

$ppp = new PPP(PPP::MODE_PREPARE_VALUES);
$sql = "DELETE FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$nb  = $ppp->delete($sql); // nb of affected rows
```
### **SELECT A RECORD** 
```php
include 'PDOPlusPlus.php';

$id   = 1;
$ppp  = new PPP(PPP::MODE_PREPARE_VALUES);
$sql  = "SELECT * FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$data = $ppp->select($sql);
```
```php
include 'PDOPlusPlus.php';

$ppp  = new PPP(PPP::MODE_PREPARE_VALUES);
$sql  = "SELECT * FROM t_video WHERE video_support LIKE {$ppp('%RAY%')}";
$data = $ppp->select($sql);
```
### **STORED PROCEDURE**

Because of having the possibility to extract many dataset at once or/and also passing multiple parameters 
`IN`, `OUT` or `INOUT`, most of the time you will have to use a specific value injector as shown below.

#### **ONE DATASET**
Let's create a SP that just return a simple dataset:
```php
// ONE ROWSET
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films()
BEGIN
    SELECT * FROM t_video;
END;
sql
);
```
And now, call it:
```php
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$rows = $ppp->call('CALL sp_list_films()', true);   // the true tells PPP that SP is a query
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of all films  
```
#### **TWO DATASET AT ONCE**
Let's create a SP that just return a double dataset at once:
```php
// TWO ROWSET
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films_group_by_support()
BEGIN
    SELECT * FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT * FROM t_video WHERE video_support = 'DVD';
END;
sql
);
```
And now, call it:
```php
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$rows = $ppp->call('CALL sp_list_films_group_by_support()', true); // the true tells PPP that SP is a query
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of films (BLU-RAY) 
// $row[1] => for the second dataset which is an array of films (DVD)
```
#### **ONE IN PARAM**
Let's create a SP with one IN Param:
```php
// WITH ONE IN PARAM
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films_one_in_param(p_support VARCHAR(30))
BEGIN
    SELECT * FROM t_video WHERE video_support = p_support;
END;
sql
);

// AND CALL IT
// FIRST METHOD : SQL DIRECT
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$rows = $ppp->call("CALL sp_list_films_one_in_param({$ppp('DVD')})", true);
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of films (DVD)

// EXACTLY THE SAME USING PREPARE VALUES
$ppp  = new PPP(PPP::MODE_PREPARE_VALUES);
$rows = $ppp->call("CALL sp_list_films_one_in_param({$ppp('DVD')})", true);

// AND IF YOU WANT TO USE PREPARE PARAMS
// DO NOT FORGET TO USE AN INJECTOR BECAUSE THAT MODE IS BY REFERENCE
$ppp  = new PPP(PPP::MODE_PREPARE_PARAMS);
$in   = $ppp->injector();
$sup  = 'DVD';
$rows = $ppp->call("CALL sp_list_films_one_in_param({$in($sup)})", true); 
```
Chain directly the variables within the SQL as many as IN params you have to pass to the stored procedure.

#### **ONE OUT PARAM**
Let's create a SP with an `OUT` Param:
```php
// WITH ONE OUT PARAM
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_one_out_param(OUT p_nb INT)
BEGIN
    SELECT COUNT(video_id) INTO p_nb FROM t_video;
END;
sql
);
```
And call it using the specific injector for the `OUT` param:
```php
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$out  = $ppp->injector('out');
$exec = $ppp->call("CALL sp_nb_films_one_out_param({$out('@nb')})", false);
$nb   = $exec['out']['@nb'];
```
**Please note that all `OUT` values are always stored in the result array with the key `out`** 

#### **ONE DATASET AND TWO OUT PARAMS**
It is also possible to mix, dataset and `OUT` param:
```php
// WITH ROWSET AND TWO OUT PARAM
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_rowset_two_out_param(
    OUT p_nb_blu_ray INT, 
    OUT p_nb_dvd INT
)
BEGIN
    SELECT * FROM t_video ORDER BY video_year DESC;
    SELECT COUNT(video_id) INTO p_nb_blu_ray FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT COUNT(video_id) INTO p_nb_dvd FROM t_video WHERE video_support = 'DVD';
END;
sql
);

$ppp   = new PPP(PPP::MODE_PREPARE_VALUES);
$out   = $ppp->injector('out');
$exec  = $ppp->call("CALL sp_nb_films_rowset_two_out_param({$out('@nb_blu_ray')}, {$out('@nb_dvd')})", true);
$rows  = $exec[0];  // $exec[0] => for the first dataset which is an array of all films ordered by year DESC
$nb_br = $exec['out']['@nb_blu_ray']; // note the key 'out'
$nb_dv = $exec['out']['@nb_dvd'];
```
#### **ONE INOUT PARAM WITH TWO OUT PARAMS**
Finally, let's create a SP that use a mix between `INOUT` and `OUT` params:
```php
// WITH ONE INOUT PARAM AND TWO OUT PARAM
$ppp  = new PPP(PPP::MODE_SQL_DIRECT);
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_one_inout_two_out_param(
    INOUT p_qty INT, 
    OUT p_nb_blu_ray INT, 
    OUT p_nb_dvd INT
)
BEGIN
    DECLARE v_nb INT;
    SELECT SUM(video_stock) INTO v_nb FROM t_video;
    SET p_qty = v_nb - p_qty;
    SELECT COUNT(video_id) INTO p_nb_blu_ray FROM t_video WHERE video_support = 'BLU-RAY';
    SELECT COUNT(video_id) INTO p_nb_dvd FROM t_video WHERE video_support = 'DVD';
END;
sql
);
```
And call it using the specific injectors: one for `INOUT` and another one for `OUT` params.<br>
Please be careful with the syntax for the `INOUT` injector.
```php
$ppp   = new PPP(PPP::MODE_SQL_DIRECT);
$io    = $ppp->injector('inout');       // io => input/output
$out   = $ppp->injector('out');
$exec  = $ppp->call("CALL sp_nb_films_one_inout_two_out_param({$io('25', '@stock', 'int')}, {$out('@nb_blu_ray')}, {$out('@nb_dvd')})");
$stock = $exec['out']['@stock'];
$nb_br = $exec['out']['@nb_blu_ray'];
$nb_dv = $exec['out']['@nb_dvd'];
```
### **CONCLUSION**
Hope this will help you to produce in a more comfortable way a better SQL code and use PDO natively in your PHP code.
This code is fully tested. To be compliant with the standards, i have to rewrite all the tests for PHPUNit.
I'll do so in the next few days.

Ok guys, that's all folks.
Enjoy ! 

**rawsrc**  
