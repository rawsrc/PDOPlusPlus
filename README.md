# PDOPlusPlus : a new generation of PDO Wrapper

`2021-11-01` `PHP 8.0+` `v.4.0.0`

## A PHP full object PDO Wrapper in one class

`PDOPlusPlus` (alias `PPP`) is **a single class PDO Wrapper for PHP** with a
 revolutionary fluid SQL syntax. 
You do not have anymore to use PDO in classical way, you can completely omit the notions of 
`prepare()`, `bindValue()`, `bindParam()`. The usage of these mechanisms is now hidden by `PDOPlusPlus`. 
All you have to do is to write directly a clean SQL query and inject directly your values.

The engine, will automatically escape the values and will let you concentrate only on the SQL syntax.

`PDOPlusPlus` is totally compliant with:
- INSERT
- UPDATE
- DELETE
- SELECT
- STORED PROCEDURE
- TRANSACTIONS (EVEN NESTED ONES)

For stored procedures, you'll be able to use any `IN`, `OUT` or `INOUT` params.<br>
`PDOPlusPlus` is also fully compatible with those returning multiple dataset
 at once.

A true Swiss knife for PDO.

**INSTALLATION**
```bash
composer require rawsrc/pdoplusplus
```
     
### THE CONCEPT
 
The power of `PDOPlusPlus` is directly linked to the way the instance is
 called as a function using the PHP magic function `__invoke()`<br>
All you have to choose is the right **injector** that will take care, in a secure 
way, of the values to be injected into the SQL.<br>

To cover all use cases, there's 5 different injectors:
- `getInjectorIn()`: injected values are directly escaped (plain sql). **THIS IS THE DEFAULT INJECTOR**
- `getInjectorInByVal()`: injected values are escaped using the `PDOStatement->bindValue()` mechanism
- `getInjectorInByRef()`: injected values are escaped using the `PDOStatement->bindParam()` mechanism
- `getInjectorOut()`: for stored procedure with only OUT param
- `getInjectorInOut()`: for stored procedure with INOUT param, IN param is directly escaped (plain sql)

Please note that by default, `PDOPlusPlus` will escape your values in plain sql.
If you want to have another behavior, like using a `PDOStatement` or calling
a stored procedure, then you must use a specific injector.

### CHANGELOG FROM VERSION 3.1

**This version 4.0.x is a major update and breaks the compatibility 
with the code based on version 3.1**<br>

NEW FEATURES:
- Auto-reset
- Support for binary data
- Support for bound columns
- Support for scrollable cursor
- Better support of transactions
- Many code improvements 
- Fully tested

REMOVED:
- `getInjectorInOutByVal()`
- `getInjectorInOutByRef()`

The test code is now available. All tests are written for another of my projects: 
[Exacodis, a minimalist test engine for PHP](https://github.com/rawsrc/exacodis) 

### AUTO-RESET FEATURE

Previously, you had to create a new instance of `PDOPlusPlus` for each statement
you wanted to execute. With the auto-reset feature (enabled by default) you can
reuse the same instance of `PDOPlusPlus` as many times as necessary.

The auto-reset is automatically disabled just in 2 cases:
- if the statement fails
- if there's any by ref variable

In those cases, the instance keeps the data and the parameters that were defined.<br>
You must force the reset of the instance using: `$ppp->reset();`

Everything is cleaned except save points in transactions which are reset
with `$ppp->releaseAll();`

You can activate/deactivate this feature using:
- `$ppp->setAutoResetOn()`
- `$ppp->setAutoResetOff()`

### ABOUT INJECTORS

When you create an injector, you can define and lock the data type of its value.
The different allowed data types are : int str float double num numeric bool binary

Every injector is invocable with its own parameters.
- `getInjectorIn(mixed $value, string $type = 'str')`
- `getInjectorInByVal(mixed $value, string $type = 'str')`
- `getInjectorInByRef(mixed &$value, string $type = 'str')`
- `getInjectorOut(string $out_tag)`
- `getInjectorInOut(mixed $value, string $inout_tag, string $type = 'str')`

Note that binary data is a type like another. Just internally the engine, 
the process is different. 

Please have a look below how to use them in a SQL context.

#### LOCK THE TYPE OF THE INJECTED VALUE

You can once for all define and lock simultaneously the type of the variable for every injector.
```php
$in = $ppp->getInjectorInByVal('int');
// now all injected values using $in() are considered by the engine as integer even if you try to redefine it on the fly
$var = $in('123', 'int');
// is equivalent to:  
$var = $in('123');  
// and in that example 'str' is ignored: 
$var = $in('123', 'str');
```
You can also define and lock the type of injector after creating it, only if the 
final type was not yet defined:
```php
$in = $ppp->getInjectorInByVal();
$in->setFinalInjectorType('int');
```

### CONNECTION TO THE DATABASE

As written, `PDOPlusPlus` is as PDO Wrapper, so it will have to connect to your database using PDO of course.
You can declare as many connections profiles as necessary. Each connection has a unique id: 
```php
// first profile: power user
PDOPlusPlus::addCnxParams(
    cnx_id: 'user_root',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => '',
        'user' => 'root',
        'pwd' => '**********',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: true
);
// second profile: basic user
PDOPlusPlus::addCnxParams(
    cnx_id: 'user_test',
    params: [
        'scheme' => 'mysql',
        'host' => 'localhost',
        'database' => 'db_pdo_plus_plus',
        'user' => 'user_test',
        'pwd' => '**********',
        'port' => '3306',
        'timeout' => '5',
        'pdo_params' => [],
        'dsn_params' => []
    ],
    is_default: false
);
```
You can define the connection for the SQL you have to execute on the server 
when initializing a new instance `$ppp = new PDOPlusPlus('user_root');` 
or `$ppp = new PDOPlusPlus('user_test');`,<br>
If the id is omitted then the connection by default will be used.
It is also possible to change the default connection's id once defined, 
see: `$ppp->setDefaultConnection()`<br>

### LET'S PLAY A LITTLE 

For the course, I will use a very simple database of one table :
```sql
DROP DATABASE IF EXISTS db_pdo_plus_plus;
CREATE DATABASE db_pdo_plus_plus;
USE db_pdo_plus_plus;
CREATE TABLE t_video
(
 video_id           int auto_increment primary key,
 video_title        varchar(255)         not null,
 video_support      varchar(30)          not null comment 'DVD DIVX BLU-RAY',
 video_multilingual tinyint(1) default 0 not null,
 video_chapter      int                  null,
 video_year         int                  not null,
 video_summary      text                 null,
 video_stock        int        default 0 not null,
 video_img          mediumblob           null,
 constraint t_video_video_titre_index
  unique (video_title)
);
``` 

### SAMPLE DATASET
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
### ADD A RECORD
Let's add the first movie into the database using `PDOPlusPlus`:<br>
I will use the SQL DIRECT mode omitting the `PDOStatement` step. 
```php
include 'PDOPlusPlus.php';

$ppp = new PDOPlusPlus(); // here the default connection wil be used and the auto-reset is enabled
$film = $data[0];
$sql = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, 
             {$ppp($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = '1'
```
Let's add the second movie into the database using `PDOPlusPlus`:<br>
I will use a `PDOStatement` based on values (`->bindValue()`).

```php
$in = $ppp->getInjectorInByVal();
$film = $data[1];
$sql = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')},
             {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, 
             {$in($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = '2' 
```
Let's truncate the table and then add the whole list of films at once.<br>
This time, I will use a `PDOStatement` based on references (`->bindParam()`) as there are many iterations to do. 
I will use the injector returned by `->injectorInByRef();`.

```php
$ppp->execute('TRUNCATE TABLE t_video');

$in = $ppp->getInjectorInByRef(); 
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
Please note that the previous statement has "by ref" variables and the auto-reset is disabled in that case.

### **UPDATE A RECORD**
So, to be able to reuse the same instance of `PDOPlusPlus`, we must clean it first.
```php
// we clean the instance
$ppp->reset();

$id = 1;
$support = 'DVD';
$sql = "UPDATE t_video SET video_support = {$ppp($support)} WHERE video_id = {$ppp($id, 'int')}";
$nb = $ppp->update($sql);  // nb of affected rows
```
### **DELETE A RECORD** 
```php
$id = 1;
$sql = "DELETE FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$nb = $ppp->delete($sql); // nb of affected rows
```
### **SELECT A RECORD** 
```php
$id = 1;
$sql = "SELECT * FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$data = $ppp->select($sql);
```
```php
$sql  = "SELECT * FROM t_video WHERE video_support LIKE {$ppp('%RAY%')}";
$data = $ppp->select($sql);
```
If you need a more powerful way of extracting data from a query, there's a specific 
method `selectStmt()` that gives you access to the `PDOStatement` generated by the engine.
```php
$sql  = "SELECT * FROM t_video WHERE video_support LIKE {$ppp('%RAY%')}";
$stmt = $ppp->selectStmt($sql);
$data = $stmt->fetchAll(PDO::FETCH_OBJ);
```
It is also possible to have a scrollable cursor (here you also have access 
to the `PDOStatement` created by the engine):
```php
$sql = "SELECT * FROM t_video WHERE video_support LIKE {$ppp('%RAY%')}";
$stmt = $ppp->selectStmtAsScrollableCursor($sql);
while ($row = $stmt->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
    // ... // 
}
```
### BOUND COLUMNS

Since v.4.0.0, it is possible to define bound columns as you'd do using 
`PDOStatement->bindColumn(...)`. This is useful when you work especially with
binary data.<br>

This feature only works with `$ppp->selectStmt()` and `$ppp->selectStmtAsScrollableCursor()`.
```php
// First, you have to prepare the bound variables.
$columns = [
    'video_title' => [&$video_title, 'str'], // watch carefully the & before the var
    'video_img' => [&$video_img, 'binary'], // watch carefully the & before the var
];

// you have to declare into the instance the bound columns
$ppp->setBoundColumns($columns);

// then call the selectStmt()
$ppp->selectStmt("SELECT video_title, video_img FROM t_video WHERE video_id = {$ppp(1, 'int')}");
// then read the result
while ($row = $stmt->fetch(PDO::FETCH_BOUND)) {
    // here $video_title and $video_img are available and well defined 
}
```

### STORED PROCEDURE

Because of having the possibility to extract many datasets at once or/and also passing multiple parameters 
`IN`, `OUT` or `INOUT`, most of the time you will have to use a specific value injector as shown below.

#### **ONE DATASET**
Let's create a SP that just return a simple dataset:
```php
$ppp = new PPP();
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
$rows = $ppp->call('CALL sp_list_films()', true);   // the true tells PPP that SP is a query
// $rows is a multidimensional array: 
// $rows[0] => for the first dataset which is an array of all films  
```
#### TWO DATASET AT ONCE
Let's create a SP that just return a double dataset at once:
```php
// TWO ROWSET
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
$rows = $ppp->call('CALL sp_list_films_group_by_support()', true); // the true tells PPP that SP is a query
// $rows is a multidimensional array: 
// $rows[0] => for the first dataset which is an array of films (BLU-RAY) 
// $rows[1] => for the second dataset which is an array of films (DVD)
```
#### ONE IN PARAM
Let's create a SP with one IN Param:

```php
// WITH ONE IN PARAM
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_list_films_one_in_param(
    p_support VARCHAR(30)
)
BEGIN
    SELECT * FROM t_video WHERE video_support = p_support;
END;
sql
);

// AND CALL IT
// FIRST METHOD : plain sql
$rows = $ppp->call("CALL sp_list_films_one_in_param({$ppp('DVD')})", true);
// $rows is a multidimensional array: 
// $rows[0] => for the first dataset which is an array of films (DVD)

// EXACTLY THE SAME USING ->bindValue()
$in = $ppp->getInjectorInByVal();
$rows = $ppp->call("CALL sp_list_films_one_in_param({$in('DVD')})", true);

// AND IF YOU WANT TO USE A REFERENCE INSTEAD
$in   = $ppp->getInjectorInByRef();
$sup  = 'DVD';
$rows = $ppp->call("CALL sp_list_films_one_in_param({$in($sup)})", true);
$ppp->reset(); // do not forget to reset the instance to be able to reuse it 
```
Chain directly the variables within the SQL as many as IN params you have to pass to the stored procedure.

#### **ONE OUT PARAM**
Let's create a SP with an `OUT` Param:
```php
// WITH ONE OUT PARAM
$exec = $ppp->execute(<<<'sql'
CREATE OR REPLACE DEFINER = root@localhost PROCEDURE db_pdo_plus_plus.sp_nb_films_one_out_param(
    OUT p_nb INT
)
BEGIN
    SELECT COUNT(video_id) INTO p_nb FROM t_video;
END;
sql
);
```
And call it using the specific injector for the `OUT` param:

```php
$out = $ppp->getInjectorOut();
$exec = $ppp->call("CALL sp_nb_films_one_out_param({$out('@nb')})", false);
$nb = $exec['out']['@nb'];
```
**Please note that all `OUT` values are always stored in the result array with the key `out`** 

#### **ONE DATASET AND TWO OUT PARAMS**
It is also possible to mix dataset and `OUT` param:

```php
// WITH ROWSET AND TWO OUT PARAM
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

$out = $ppp->getInjectorOut();
$exec = $ppp->call("CALL sp_nb_films_rowset_two_out_param({$out('@nb_blu_ray')}, {$out('@nb_dvd')})", true);
$rows = $exec[0];  // $exec[0] => for the first dataset which is an array of all films ordered by year DESC
$nb_br = $exec['out']['@nb_blu_ray']; // note the key 'out'
$nb_dv = $exec['out']['@nb_dvd'];
```
#### **ONE INOUT PARAM WITH TWO OUT PARAMS**
Finally, let's create a SP that use a mix between `INOUT` and `OUT` params:
```php
// WITH ONE INOUT PARAM AND TWO OUT PARAM
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
$io = $ppp->getInjectorInOut();       // io => input/output
$out = $ppp->getInjectorOut();
$exec = $ppp->call("CALL sp_nb_films_one_inout_two_out_param({$io('25', '@stock', 'int')}, {$out('@nb_blu_ray')}, {$out('@nb_dvd')})", false);
$stock = $exec['out']['@stock'];
$nb_br = $exec['out']['@nb_blu_ray'];
$nb_dv = $exec['out']['@nb_dvd'];
```
### TRANSACTIONS

PDO++ is fully compatible with the RDBS transaction mechanism.<br>
You have several methods that will help you to manage your SQL code flow: 
* `setTransaction()` to define the execution context of the transaction to come
* `startTransaction()`
* `commit()`     
* `rollback()` that will just rollback to the last save point
* `rollbackTo()` that will just rollback to the given save point
* `rollbackAll()` that will rollback to the beginning
* `savePoint()` to create a new save point (a marker inside a flow of SQL code)
* `release()` to remove a save point
* `releaseAll()` to remove all save points

If you're familiar with the SQL transactions theory, the functions are well named and easy to understand.

Please note, that when you start a transaction, the engine disable the database `AUTOCOMMIT` parameter, 
that way, all sql statements will be saved at once on `$ppp->commit();`.

### ERRORS

To avoid plenty of `try { } catch { }` blocks, I introduced a mechanism that will factorize this part of code.<br>
As `PDOPlusPlus` can throw an `Exception` when a statement fails, you should always intercept that possible issue and
use everywhere in your code a `try { } catch { }` block. It's pretty heavy, isn't it ?

Now you can define a closure that will embed the treatment of the exception.
At the beginning, you just have to define once a unique closure that will receive and treat the thrown `Exception` by `PDOPlusPlus`
```php
// Exception wrapper for PDO
PDOPlusPlus::setExceptionWrapper(function(Exception $e, PDOPlusPlus $ppp, string $sql, string $func_name, ...$args) {
    // here you code whatever you want
    // ...
    // then you must return a result
    return 'DB Error, unable to execute the query';
});
```
Then you can activate/deactivate this feature using:
- `$ppp->setThrowOn();`
- `$ppp->setThrowOff();`

In case of problem and if the throwing is deactivated, `PDOPlusPlus` will intercept 
as usual the `Exception` and will pass it to your closure. 
In taht case, the method will return `null`.

Suppose this code produces an error:
```php
try {
    $ppp = new PDOPlusPlus();
    $sql = "INSERT INTO t_table (field_a, field_b) VALUES ({$ppp('value_a')}, {$ppp('value_b')})";
    $id  = $ppp->insert($sql);
} catch (Exception $e) {
    // bla bla
}
```
using the mechanism of exception wrapper, you can simply do:
```php
$ppp = new PDOPlusPlus();
$ppp->setThrowOff();
$sql = "INSERT INTO t_table (field_a, field_b) VALUES ({$ppp('value_a')}, {$ppp('value_b')})";
$id  = $ppp->insert($sql);
if ($id === null) {
    $error = $ppp->getErrorFromWrapper(); // $error = 'DB Error, unable to execute the query'
}
```

### CONCLUSION
Hope this will help you to produce in a more comfortable way a better SQL code and 
use PDO natively in your PHP code.

Ok guys, that's all folks.
Enjoy ! 

**rawsrc**