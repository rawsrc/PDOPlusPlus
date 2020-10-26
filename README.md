# **PDOPlusPlus : a new generation of PDO Wrapper**

`2020-10-26` `PHP 7.1+`

## **A PHP full object PDO Wrapper in one class**

`PDOPlusPlus` (alias `PPP`) is **a one single class PDO Wrapper for PHP** with a
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
     
### **THE CONCEPT**
 
The power of `PDOPlusPlus` is directly linked to the way the instance is
 called as a function using the PHP magic function `__invoke()`<br>
All you have to choose is the right injector to write the SQL and at
 the same time pass the different values to the query.<br>
Here's the global scheme for the concept:
 
 ![PDOPlusPlus Concept](/PDOPlusPlusConcept.png)

Please note that by default, PDOPlusPlus will escape your values in plain sql.
If you want to have another behavior, like using a `PDOStatement` or calling 
a stored procedure, then you must use a specific injector.

 ![PDOPlusPlus Concept](/PDOPlusPlusInOut.png)

 ![PDOPlusPlus Concept](/PDOPlusPlusOut.png)

Since, the version 3.x, it is also possible to mix the way you inject your values within the same SQL string !

To cover all use cases, there's now 7 different injectors:
- `injectorInSql()`: injected values are directly escaped (plain sql). **THIS IS THE DEFAULT INJECTOR**
- `injectorInByVal()`: injected values are escaped using the `PDOStatement->bindValue()` mechanism
- `injectorInByRef()`: injected values are escaped using the `PDOStatement->bindParam()` mechanism
- `injectorOut()`: for stored procedure with only OUT param
- `injectorInOutSql()`: for stored procedure with INOUT param, IN param is directly escaped (plain sql)
- `injectorInOutByVal()`: for stored procedure with INOUT param, IN param is escaped using the `PDOStatement->bindValue()` mechanism
- `injectorInOutByRef()`: for stored procedure with INOUT param, IN param is escaped using the `PDOStatement->bindParam()` mechanism

### **WHAT'S NEW AND CHANGES FROM VERSION 2.x**

**This version breaks the compatibilty with the previous 2.0**<br>

As there's no more need to define a global way of injecting values, the engine will let you to proceed as you want.
That's mean your are even able now to mix in the same SQL string different ways of passing the values.
For example: some can be escaped in plain SQL, others can use the injector for `->bindValue()` and/or `->bindParams()`.
All you have is to choose the right injector for your needs. 

There's a new mechanism to intercept potential crashes on executing/querying SQL.<br>
Using this, you do not have anymore to get your SQL code embedded in the a `try {...} catch (Exception $e) {...}` block.<br>
See chapter **ERRORS** below.

### **WHY PDOPlusPlus ?**

Just because, this:
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
```
```php
try {
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
$ppp = new PPP();
$in  = $ppp->injectorInByVal(); // PPP will use ->bindValue() internally
$sql = <<<sql
INSERT INTO t_video (
    video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock
) VALUES (
   {$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')}, 
   {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, 
   {$in($film['stock'], 'int')}
);
sql;
$id = $ppp->insert($sql);
```
With exactly the same level of security !

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

For the course, I will use a very simple database of one table :
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

### **ADD A RECORD**
Let's add the first movie into the database using `PDOPlusPlus`:<br>
I will use the SQL DIRECT mode omitting the `PDOStatement` step. 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus();
$film = $data[0];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$ppp($film['title'])}, {$ppp($film['support'])}, {$ppp($film['multilingual'], 'bool')},
             {$ppp($film['chapter'], 'int')}, {$ppp($film['year'], 'int')}, {$ppp($film['summary'])}, 
             {$ppp($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = 1 (lastInsertId())
```
Let's add the second movie into the database using `PDOPlusPlus`:<br>
I will use a `PDOStatement` based on values (`->bindValue()`). 
```php
include 'PDOPlusPlus.php';

$ppp  = new PDOPlusPlus();
$in   = $ppp->injectorInByVal();
$film = $data[1];
$sql  = <<<sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock)
     VALUES ({$in($film['title'])}, {$in($film['support'])}, {$in($film['multilingual'], 'bool')},
             {$in($film['chapter'], 'int')}, {$in($film['year'], 'int')}, {$in($film['summary'])}, 
             {$in($film['stock'], 'int')})
sql;
$new_id = $ppp->insert($sql);   // $new_id = 2 (lastInsertId())
```
Look at he the SQL generated internally by `PDOPlusPlus` : 
```sql
INSERT INTO t_video (video_title, video_support, video_multilingual, video_chapter, video_year, video_summary, video_stock) 
     VALUES (:XMEDem6153, :oASqvP7440, :mbfaTY4236, :FJzRWx7446, :FVHvqL4843, :tcCvZo8956, :JRtazM4176);
```
Let's truncate the table and then add the whole list of films at once.<br>
This time, I will use a `PDOStatement` based on references (`->bindParam()`) as there are many iterations to do.

Please note, to pass the references to the `PDOPlusPlus` instance, you **MUST** use a specific reference injector
returned by `->injectorInByRef();`. Otherwise it will not work.
```php
include 'PDOPlusPlus.php';
$ppp = new PPP();
$ppp->execute('TRUNCATE TABLE t_video');

$ppp = new PPP();
$in  = $ppp->injectorInByRef();   // to pass references you **MUST** use this reference injector 
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

$ppp = new PPP();
$sql = "UPDATE t_video SET video_support = {$ppp($support)} WHERE video_id = {$ppp($id, 'int')}";
$nb  = $ppp->update($sql);  // nb of affected rows
```
### **DELETE A RECORD** 
```php
include 'PDOPlusPlus.php';
$id = 1;

$ppp = new PPP();
$sql = "DELETE FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$nb  = $ppp->delete($sql); // nb of affected rows
```
### **SELECT A RECORD** 
```php
include 'PDOPlusPlus.php';

$id   = 1;
$ppp  = new PPP();
$sql  = "SELECT * FROM t_video WHERE video_id = {$ppp($id, 'int')}";
$data = $ppp->select($sql);
```
```php
include 'PDOPlusPlus.php';

$ppp  = new PPP();
$sql  = "SELECT * FROM t_video WHERE video_support LIKE {$ppp('%RAY%')}";
$data = $ppp->select($sql);
```
### **STORED PROCEDURE**

Because of having the possibility to extract many datasets at once or/and also passing multiple parameters 
`IN`, `OUT` or `INOUT`, most of the time you will have to use a specific value injector as shown below.

#### **ONE DATASET**
Let's create a SP that just return a simple dataset:
```php
// ONE ROWSET
$ppp  = new PPP();
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
$ppp  = new PPP();
$rows = $ppp->call('CALL sp_list_films()', true);   // the true tells PPP that SP is a query
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of all films  
```
#### **TWO DATASET AT ONCE**
Let's create a SP that just return a double dataset at once:
```php
// TWO ROWSET
$ppp  = new PPP();
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
$ppp  = new PPP();
$rows = $ppp->call('CALL sp_list_films_group_by_support()', true); // the true tells PPP that SP is a query
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of films (BLU-RAY) 
// $row[1] => for the second dataset which is an array of films (DVD)
```
#### **ONE IN PARAM**
Let's create a SP with one IN Param:
```php
// WITH ONE IN PARAM
$ppp  = new PPP();
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
$ppp  = new PPP();
$rows = $ppp->call("CALL sp_list_films_one_in_param({$ppp('DVD')})", true);
// $rows is an multidimensional array: 
// $row[0] => for the first dataset which is an array of films (DVD)

// EXACTLY THE SAME USING ->bindValue()
$ppp  = new PPP();
$in   = $ppp->injectorInByVal();
$rows = $ppp->call("CALL sp_list_films_one_in_param({$in('DVD')})", true);

// AND IF YOU WANT TO USE A REFERENCE INSTEAD
$ppp  = new PPP();
$in   = $ppp->injectorInByRef();
$sup  = 'DVD';
$rows = $ppp->call("CALL sp_list_films_one_in_param({$in($sup)})", true); 
```
Chain directly the variables within the SQL as many as IN params you have to pass to the stored procedure.

#### **ONE OUT PARAM**
Let's create a SP with an `OUT` Param:
```php
// WITH ONE OUT PARAM
$ppp  = new PPP();
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
$ppp  = new PPP();
$out  = $ppp->injectorOut();
$exec = $ppp->call("CALL sp_nb_films_one_out_param({$out('@nb')})", false);
$nb   = $exec['out']['@nb'];
```
**Please note that all `OUT` values are always stored in the result array with the key `out`** 

#### **ONE DATASET AND TWO OUT PARAMS**
It is also possible to mix dataset and `OUT` param:
```php
// WITH ROWSET AND TWO OUT PARAM
$ppp  = new PPP();
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

$ppp   = new PPP();
$out   = $ppp->injectorOut();
$exec  = $ppp->call("CALL sp_nb_films_rowset_two_out_param({$out('@nb_blu_ray')}, {$out('@nb_dvd')})", true);
$rows  = $exec[0];  // $exec[0] => for the first dataset which is an array of all films ordered by year DESC
$nb_br = $exec['out']['@nb_blu_ray']; // note the key 'out'
$nb_dv = $exec['out']['@nb_dvd'];
```
#### **ONE INOUT PARAM WITH TWO OUT PARAMS**
Finally, let's create a SP that use a mix between `INOUT` and `OUT` params:
```php
// WITH ONE INOUT PARAM AND TWO OUT PARAM
$ppp  = new PPP();
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
$ppp   = new PPP();
$io    = $ppp->injectorInOutByVal();       // io => input/output
$out   = $ppp->injectorOut();
$exec  = $ppp->call("CALL sp_nb_films_one_inout_two_out_param({$io('25', '@stock', 'int')}, {$out('@nb_blu_ray')}, {$out('@nb_dvd')})", false);
$stock = $exec['out']['@stock'];
$nb_br = $exec['out']['@nb_blu_ray'];
$nb_dv = $exec['out']['@nb_dvd'];
```
### **TRANSACTIONS**
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

### **ERRORS**
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
    // then you must return a result of the treatment
    return 'DB Error, unable to execute the query';
});
```
In case of problem, `PDOPlusPlus` will intercept as usual the `Exception` but instead of throwing it, it will pass it 
to your closure and will return `null` as the result of the called method.

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
using the exception wrapper, you can simply do:
```php
$ppp = new PDOPlusPlus();
$sql = "INSERT INTO t_table (field_a, field_b) VALUES ({$ppp('value_a')}, {$ppp('value_b')})";
$id  = $ppp->insert($sql);
if ($id === null) {
    $error = $ppp->error(); // $error = 'DB Error, unable to execute the query'
}
```

### **CONCLUSION**
Hope this will help you to produce in a more comfortable way a better SQL code and use PDO natively in your PHP code.

Ok guys, that's all folks.
Enjoy ! 

**rawsrc**