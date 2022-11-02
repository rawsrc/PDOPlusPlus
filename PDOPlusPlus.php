<?php declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

include_once 'AbstractInjector.php';

use InvalidArgumentException;
use rawsrc\PDOPlusPlus\AbstractInjector;

use BadMethodCallException;
use Closure;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use TypeError;

use function array_key_first;
use function array_keys;
use function array_reduce;
use function array_search;
use function array_splice;
use function count;
use function implode;
use function in_array;
use function is_scalar;
use function str_replace;
use function substr;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper with a new revolutionary fluid SQL syntax
 *
 * @link        https://github.com/rawsrc/PDOPlusPlus
 * @author      rawsrc
 * @copyright   MIT License
 *
 *              Copyright (c) 2022+ rawsrc
 *
 *              Permission is hereby granted, free of charge, to any person obtaining a copy
 *              of this software and associated documentation files (the "Software"), to deal
 *              in the Software without restriction, including without limitation the rights
 *              to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *              copies of the Software, and to permit persons to whom the Software is
 *              furnished to do so, subject to the following conditions:
 *
 *              The above copyright notice and this permission notice shall be included in all
 *              copies or substantial portions of the Software.
 *
 *              THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *              IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *              FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *              AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *              LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *              OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *              SOFTWARE.
 */
class PDOPlusPlus
{
    /**
     * Used by tag generator
     */
    protected const ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    /**
     * User data: 6 different injectors
     */
    protected const VAR_IN = 'in';
    protected const VAR_IN_AS_REF = 'in_as_ref';
    protected const VAR_IN_BY_REF = 'in_by_ref';
    protected const VAR_IN_BY_VAL = 'in_by_val';
    protected const VAR_INOUT = 'inout';
    protected const VAR_OUT = 'out';
    /**
     * @var array [cnx id => PDO instance]
     */
    protected static array $pdo = [];
    /**
     * @var array [cnx id => [key => value]]
     */
    protected static array $cnx_params = [];
    /**
     * @var int|string|null
     */
    protected static int|string|null $default_cnx_id = null;
    /**
     * @var int|string|null
     */
    protected int|string|null $current_cnx_id = null;
    /**
     * @var PDO|null
     */
    protected PDO|null $current_pdo = null;
    /**
     * List all generated tags during the current session
     * @var array [tag]
     */
    protected static array $tags = [];
    /**
     * @var bool Default behavior for the auto-reset feature
     */
    protected bool $auto_reset = true;
    /**
     * User data injected in the sql string
     * @var array [tag => [value => user value, type => user type]
     */
    protected array $data = [
        self::VAR_IN => [],
        self::VAR_IN_AS_REF => [],
        self::VAR_IN_BY_REF => [],
        self::VAR_IN_BY_VAL => [],
        self::VAR_INOUT => [],
        self::VAR_OUT => [],
    ];
    /**
     * @var string Build sql string
     */
    protected string $sql = '';
    /**
     * @var PDOStatement|null
     */
    protected PDOStatement|null $stmt = null;
    /**
     * @var array used by nested transactions
     */
    protected array $save_points = [];
    /**
     * Closure that wraps and captures an exception thrown from PDO
     * @var Closure
     */
    protected static Closure $exception_wrapper;
    /**
     * Stores the result returned by the closure $exception_wrapper
     * @var mixed
     */
    protected mixed $error_from_wrapper;
    /**
     * @var bool
     */
    protected bool $throw = true;
    /**
     * @var bool
     */
    protected bool $has_failed = false;
    /**
     * @var bool
     */
    protected bool $params_already_bound = false;
    /**
     * @var array [tag => PDO::PARAM_]
     */
    protected array $last_bound_type = [];
    /**
     * @var array
     */
    protected array $bound_columns = [];
    /**
     * @var bool
     */
    protected bool $bind_before_execute = false;

    /**
     * The auto-reset feature prepare automatically the current instance for a new sql statement if there's no
     * BY REF values
     *
     * @param string|null $cnx_id if null then the default connection will be used
     * @param bool $auto_reset
     */
    public function __construct(?string $cnx_id = null, bool $auto_reset = true)
    {
        $this->current_cnx_id = $cnx_id;
        $this->auto_reset = $auto_reset;
    }

    /**
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->has_failed;
    }

    /**
     * @return bool
     */
    protected function hasOutTags(): bool
    {
        return ! (empty($this->data[self::VAR_OUT])
            && empty($this->data[self::VAR_INOUT])
        );
    }

    /**
     * @param int|string|null $cnx_id null => default connection
     * @return PDO
     * @throws BadMethodCallException|Exception
     */
    public static function getPdo(int|string|null $cnx_id = null): PDO
    {
        $cnx_id ??= static::getDefaultCnxId();

        if (isset(static::$pdo[$cnx_id])) {
            return static::$pdo[$cnx_id];
        } elseif (isset(static::$cnx_params[$cnx_id])) {
            $params = static::$cnx_params[$cnx_id];
            self::$pdo[$cnx_id] = self::connect(
                $params['scheme'],
                $params['host'],
                $params['database'],
                $params['user'],
                $params['pwd'],
                $params['port'],
                $params['timeout'] ?? '5',
                $params['pdo_params'] ?? [],
                $params['dsn_params'] ?? [],
            );

            return static::$pdo[$cnx_id];
        }

        throw new BadMethodCallException('Unknown connection id');
    }

    /**
     * @param int|string|null $cnx_id
     */
    public static function setDefaultConnection(int|string|null $cnx_id): void
    {
        self::$default_cnx_id = $cnx_id;
    }

    /**
     * @return int|string
     * @throws Exception
     */
    protected static function getDefaultCnxId(): int|string
    {
        if (isset(static::$default_cnx_id)) {
            return static::$default_cnx_id;
        } elseif (count(static::$cnx_params) === 1) {
            return array_key_first(static::$cnx_params);
        } else {
            throw new Exception('No available connection');
        }
    }

    /**
     * @param int|string|null $cnx_id
     */
    public function setCurrentConnection(int|string|null $cnx_id): void
    {
        $this->current_cnx_id = $cnx_id;
    }

    /**
     * Create a pool of database connections
     * You juste have to store all the parameters for a connection
     * $params = [
     *     'scheme'     => string (mysql pgsql...)
     *     'host'       => string (server host)
     *     'database'   => string (database name)
     *     'user'       => string (username)
     *     'pwd'        => string (password)
     *     'port'       => string (port number)
     *     'timeout'    => string (seconds)
     *     'pdo_params' => others parameters for PDO: array [key => value]
     *     'dsn_params' => other parameter for the dsn string: array [string]
     * ]
     *
     * Careful all keys except 'timeout', 'pdo_params' and 'dsn_params' are required
     * If one is missing then an Exception will be thrown
     *
     * @param string $cnx_id
     * @param array $params
     * @param bool $is_default
     * @throws BadMethodCallException
     */
    public static function addCnxParams(string $cnx_id, array $params, bool $is_default): void
    {
        if (isset(
            $params['scheme'],
            $params['host'],
            $params['database'],
            $params['user'],
            $params['pwd'],
            $params['port'],
        )) {
            self::$cnx_params[$cnx_id] = $params;
            if ($is_default) {
                self::$default_cnx_id = $cnx_id;
            }
        } else {
            throw new BadMethodCallException('Invalid connection parameters');
        }
    }

    //region DATABASE CONNECTION

    /**
     * Default overridable parameters for PDO are :
     *      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     *      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     *      PDO::ATTR_EMULATE_PREPARES   => false
     *
     * @param string $scheme Ex: mysql pgsql...
     * @param string $host server host
     * @param string $database database name: may be an empty string
     * @param string $user username
     * @param string $pwd password
     * @param string $port port number
     * @param string $timeout
     * @param array $pdo_params others parameters for PDO [key => value]
     * @param array $dsn_params other parameter for the dsn string [string]
     * @return PDO
     *
     * @throws PDOException
     */
    protected static function connect(
        string $scheme,
        string $host,
        string $database,
        string $user,
        string $pwd,
        string $port,
        string $timeout,
        array  $pdo_params = [],
        array  $dsn_params = [],
    ): PDO
    {

        $dsn = "{$scheme}:host={$host};";

        if ($database !== '') {
            $dsn .= "dbname={$database};";
        }

        if ((int)($port)) {
            $dsn .= 'port='.(int)$port.';';
        }

        if ((int)$timeout) {
            $dsn .= 'connect_timeout='.(int)$timeout.';';
        }

        if ( ! empty($dsn_params)) {
            $dsn .= implode(';', $dsn_params).';';
        }

        $params = $pdo_params + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

        return new PDO($dsn, $user, $pwd, $params);
    }
    //endregion

    /**
     * 2 parameters allowed :
     *    - the first : the user value
     *    - the second : a string for the type among: 'int', 'str', 'float', 'double', 'num', 'numeric', 'bool'
     *
     * By default, the value is directly escaped in the SQL string and all fields are strings
     *
     * @param array $args
     * @return mixed
     */
    public function __invoke(...$args): mixed
    {
        return $this->getInjectorIn()(...$args);
    }

    //region TRANSACTION

    /**
     * The SQL "SET TRANSACTION" parameters must be defined before starting
     * a new transaction otherwise it will be ignored
     *
     * @param string $sql
     * @throws Exception
     */
    public function setTransaction(string $sql): void
    {
        $this->execTransaction($sql, 'setTransaction');
    }

    /**
     * @throws Exception
     */
    public function startTransaction(): void
    {
        $this->setAutocommit(false);
        $this->execTransaction(
            sql : 'START TRANSACTION;',
            func_name : 'startTransaction',
        );
    }

    /**
     * Careful: If autocommit is set to true then all pending statements will be committed
     *
     * @param bool $value
     * @throws Exception
     */
    protected function setAutocommit(bool $value): void
    {
        $v = (int)$value;
        $this->execTransaction(
            sql : "SET AUTOCOMMIT={$v};",
            func_name : 'setAutocommit',
        );
    }

    /**
     * The commit always apply to the whole transaction at once
     *
     * @param bool $enable_autocommit
     * @throws Exception
     */
    public function commit(bool $enable_autocommit = true): void
    {
        $this->execTransaction(
            sql : 'COMMIT;',
            func_name : 'commit',
        );
        if ($enable_autocommit) {
            $this->setAutocommit(true);
        }
        $this->autoReset();
    }

    /**
     * Rollback only the last transaction
     *
     * @throws Exception
     */
    public function rollback(): void
    {
        if (empty($this->save_points) || (count($this->save_points) === 1)) {
            $this->rollbackAll();
        } else {
            $save_point = end($this->save_points);
            $this->rollbackTo($save_point);
        }
    }

    /**
     * @param string $save_point
     * @throws Exception
     */
    public function rollbackTo(string $save_point): void
    {
        if (in_array($save_point, $this->save_points, true)) {
            // rollback all commands that were executed after the savepoint was established.
            // implicitly destroys all save points that were established after the named savepoint
            $this->execTransaction(
                sql : "ROLLBACK TO {$save_point};",
                func_name : 'rollbackTo',
            );
            $pos = array_search($save_point, $this->save_points, true);
            array_splice($this->save_points, $pos);
        }
    }

    /**
     * Rollback all the transactions at once (even nested ones)
     *
     * @throws Exception
     */
    public function rollbackAll(): void
    {
        $this->execTransaction(
            sql : 'ROLLBACK;',
            func_name : 'rollback',
        );
        $this->save_points = [];
    }

    /**
     * Create a save point
     *
     * @param string $save_point_name
     * @throws Exception
     */
    public function createSavePoint(string $save_point_name): void
    {
        $this->execTransaction(
            sql : "SAVEPOINT {$save_point_name};",
            func_name : 'savePoint',
        );
        $this->save_points[] = $save_point_name;
    }

    /**
     * Release a save point
     *
     * @param string $save_point
     * @throws Exception
     */
    public function release(string $save_point): void
    {
        if (in_array($save_point, $this->save_points, true)) {
            $this->execTransaction(
                sql : "RELEASE SAVEPOINT {$save_point};",
                func_name : 'release',
            );
            $pos = array_search($save_point, $this->save_points, true);
            unset($this->save_points[$pos]);
        }
    }

    /**
     * @throws Exception
     */
    public function releaseAll(): void
    {
        foreach ($this->save_points as $save_point) {
            $this->execTransaction(
                sql : "RELEASE SAVEPOINT {$save_point};",
                func_name : 'release',
            );
        }
        $this->save_points = [];
    }

    /**
     * @param string $sql
     * @param string $func_name
     * @throws Exception
     */
    protected function execTransaction(string $sql, string $func_name)
    {
        try {
            self::getPdo($this->current_cnx_id)->exec($sql);
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $func_name);

            return null;
        }
    }
    //endregion

    //region INJECTORS
    //region IN
    /**
     * Plain escaped SQL value
     * Possible types: int str float double num numeric bool binary
     *
     * @return AbstractInjector
     * @throws TypeError
     */
    public function getInjectorIn(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float bool binary bigint
             * @return string
             * @throws TypeError
             */
            public function __invoke(mixed $value, string $type = 'str'): string
            {
                if (($value === null) || is_scalar($value)) {
                    $prepend = ($type === 'bigint') ? 'bigint' : '';
                    $tag = PDOPlusPlus::getTag($prepend);
                    $this->data['in'][$tag] = [
                        'value' => $value,
                        'type' => $type,
                    ];

                    return $tag;
                } else {
                    throw new TypeError('Null or scalar value expected');
                }
            }
        };
    }

    /**
     * Plain escaped SQL values passed by reference
     * Possible types: int str float double num numeric bool binary
     *
     * @return AbstractInjector
     */
    public function getInjectorInAsRef(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float bool binary bigint
             * @return string
             */
            public function __invoke(mixed &$value, string $type = 'str'): string
            {
                $prepend = ($type === 'bigint') ? 'bigint' : '';
                $tag = PDOPlusPlus::getTag($prepend);
                $this->data['in_as_ref'][$tag] = [
                    'value' => &$value,
                    'type' => $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for values using a statement with ->bindValue()
     * Possible types: int str float double num numeric bool binary
     *
     * @return AbstractInjector
     */
    public function getInjectorInByVal(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float bool binary bigint
             * @return string
             */
            public function __invoke(mixed $value, string $type = 'str'): string
            {
                if ($type === 'bigint') {
                    $tag = PDOPlusPlus::getTag(prepend : 'bigint');
                    $key = 'in';
                } else {
                    $tag = PDOPlusPlus::getTag();
                    $key = 'in_by_val';
                }
                $this->data[$key][$tag] = [
                    'value' => $value,
                    'type' => $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for referenced values using a statement with ->bindParam()
     *
     * @return AbstractInjector
     */
    public function getInjectorInByRef(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool binary bigint
             * @return string
             */
            public function __invoke(mixed &$value, string $type = 'str'): string
            {
                if ($type === 'bigint') {
                    $tag = PDOPlusPlus::getTag(prepend : 'bigint');
                    $key = 'in_as_ref';
                } else {
                    $tag = PDOPlusPlus::getTag();
                    $key = 'in_by_ref';
                }
                $this->data[$key][$tag] = [
                    'value' => &$value,
                    'type' => $type,
                ];

                return $tag;
            }
        };
    }
    //endregion IN

    //region INOUT
    /**
     * Injector for a plain sql escaped INOUT attribute
     *
     * @return AbstractInjector
     */
    public function getInjectorInOut(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type for the IN PARAMETER among: int str float bool binary bigint
             * @return string
             */
            public function __invoke(mixed $value, string $inout_tag, string $type = 'str'): string
            {
                $this->data['inout'][$inout_tag] = [
                    'value' => $value,
                    'type' => $type,
                ];

                return $inout_tag;
            }
        };
    }
    //endregion INOUT

    //region OUT
    /**
     * Injector for an OUT attribute
     *
     * @return AbstractInjector
     */
    public function getInjectorOut(): object
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param string $out_tag ex:'@id'
             * @return string
             */
            public function __invoke(string $out_tag): string
            {
                $this->data['out'][$out_tag] = true;

                return $out_tag;
            }
        };
    }
    //endregion OUT
    //endregion INJECTORS

    //region BOUND COLUMNS
    /**
     * The string type is among: int str bool binary null
     * The size is not required, it's depends on the database engine requirements'
     * Depending on your database engine, you must sometimes ask to bind columns before executing
     *
     * @param array $bound_columns [sql col name => [0 => $var_name, 1 => string type, 2 => size = 0, 3 => driver options = null]]
     * @param bool $bind_before_execute
     */
    public function setBoundColumns(array &$bound_columns, bool $bind_before_execute = false): void
    {
        $this->bound_columns =& $bound_columns;
        $this->bind_before_execute = $bind_before_execute;
    }

    public function unsetBoundColumns(): void
    {
        $this->bound_columns = [];
        $this->bind_before_execute = false;
    }

    /**
     * @param bool $execute_stmt
     */
    protected function bindColumns(bool $execute_stmt): void
    {
        $bind_cols = function () {
            foreach ($this->bound_columns as $sql_col => &$var) {
                $this->stmt->bindColumn(
                    column : $sql_col,
                    var : $var[0],
                    type : self::getPDOType($var[1]),
                    maxLength : $var[2] ?? 0,
                    driverOptions : $var[3] ?? null
                );
            }
        };

        if (isset($this->stmt)) {
            if ( ! empty($this->bound_columns)) {
                if ($execute_stmt) {
                    if ($this->bind_before_execute) {
                        $bind_cols();
                        $this->stmt->execute();
                    } else {
                        $this->stmt->execute();
                        $bind_cols();
                    }
                } else {
                    $bind_cols();
                }
            } elseif ($execute_stmt) {
                $this->stmt->execute();
            }
        }
    }
    //endregion

    /**
     * $fetch_arg depends on the value of $pdo_fetch_mode
     *
     * Tu use bound columns, you must call ->selectStmt() instead
     *
     * @link https://www.php.net/manual/en/pdostatement.fetchall.php     *
     *
     * @param mixed $sql
     * @param int $pdo_fetch_mode
     * @param mixed ...$pdo_fetch_arg
     * @return array|null
     * @throws Exception
     */
    public function select(string $sql, int $pdo_fetch_mode = PDO::FETCH_ASSOC, ...$pdo_fetch_arg): array|null
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->stmt->execute();
            } else {
                $this->stmt = $this->current_pdo->query($this->sql);
            }
            if (empty($pdo_fetch_arg)) {
                $data = $this->stmt->fetchAll($pdo_fetch_mode);
            } else {
                $data = $this->stmt->fetchAll($pdo_fetch_mode, ...$pdo_fetch_arg);
            }
            $this->autoReset();

            return $data;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'select');

            return null;
        }
    }

    /**
     * For bound columns, you must first declare them using ->setBoundColumns()
     *
     * For select statements having binary columns, the method binds the columns and return the PDOStatement
     * As binary columns may be very large and usable as a stream, it's not possible to fetch them all at once as usual
     *
     * When you select binary columns, you have to read the data using PDO::FETCH_BOUND
     * while ($row = $stmt->fetch(PDO::FETCH_BOUND)) {
     *     ...
     * }
     *
     * @link https://www.php.net/manual/en/pdo.lobs.php
     *
     * @param string $sql
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmt(string $sql): PDOStatement|null
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->bindColumns(true);
            } else {
                $this->stmt = $this->current_pdo->query($this->sql);
                $this->bindColumns(false);
            }

            return $this->stmt;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'selectStmt');

            return null;
        }
    }

    /**
     * @param string $sql
     * @param array $driver_options Others options than scrollable cursor (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL)
     * @return PDOStatement|null
     * @throws Exception
     * @see selectStmt()
     *
     */
    public function selectStmtAsScrollableCursor(string $sql, array $driver_options = []): PDOStatement|null
    {
        $driver_options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL] + $driver_options;

        try {
            $this->buildSql($sql, $driver_options);
            if ($this->useStatement()) {
                $this->bindColumns(true);
            } else {
                $this->stmt = $this->current_pdo->prepare($this->sql, $driver_options);
                $this->bindColumns(true);
            }

            return $this->stmt;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'selectStmtAsScrollableCursor');

            return null;
        }
    }

    /**
     * @param string $sql
     * @return string|null lastInsertId() | null on error
     * @throws Exception
     */
    public function insert(string $sql): string|null
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->stmt->execute();
            } else {
                $this->current_pdo->exec($this->sql);
            }
            $new_id = $this->current_pdo->lastInsertId();
            $this->autoReset();

            return $new_id;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'insert');

            return null;
        }
    }

    /**
     * @param string $sql
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function update(string $sql): ?int
    {
        return $this->execute($sql);
    }

    /**
     * @param string $sql
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function delete(string $sql): ?int
    {
        return $this->execute($sql);
    }

    /**
     * @param string $sql
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function execute(string $sql): ?int
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->stmt->execute();
                $nb = $this->stmt->rowCount();
            } else {
                $nb = $this->current_pdo->exec($this->sql);
            }
            $this->autoReset();

            return $nb;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'execute');

            return null;
        }
    }

    /**
     * @param string $sql
     * @param bool $is_query For SP that returns a dataset using SELECT
     * @return array|int|null
     * @throws Exception
     */
    public function call(string $sql, bool $is_query): array|int|null
    {
        try {
            $this->current_pdo = self::getPdo($this->current_cnx_id);

            $this->buildSql($sql);

            // FOR STORED PROCEDURE: UNIVERSAL SQL SYNTAX : "SET @io_param = value"
            $params = [];
            foreach ($this->getData(self::VAR_INOUT) as $tag => $v) {
                $params[] = "SET {$tag} = ".self::getSQLValue(
                        value : $v['value'],
                        type : $v['type'],
                        pdo : $this->current_pdo,
                    );
            }

            if ( ! empty($params)) {
                $this->current_pdo->exec(implode(';', $params));
            }

            if ($is_query) {
                if ($this->useStatement()) {
                    $this->stmt->execute();
                } else {
                    $this->stmt = $this->current_pdo->query($this->sql);
                }
                $data = [];
                // extracting all data
                do {
                    $row = $this->stmt->fetchAll();
                    if ($row) {
                        $data[] = $row;
                    } else {
                        break;
                    }
                } while ($this->stmt->nextRowset());

                // adding the OUT tags values
                if ($this->hasOutTags()) {
                    $data['out'] = $this->extractOutTags();
                }

                $this->autoReset();

                return $data;
            } else {
                // SQL Direct
                if ($this->hasOutTags()) {
                    $this->current_pdo->exec($this->sql);
                    $out = $this->extractOutTags();
                    $this->autoReset();

                    return ['out' => $out];
                } else {
                    $nb = $this->current_pdo->exec($this->sql);
                    $this->autoReset();

                    return $nb;
                }
            }
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'call');

            return null;
        }
    }

    /**
     * @return array|null [out_tag => value]
     * @throws Exception
     */
    public function extractOutTags(): ?array
    {
        if ($this->hasOutTags()) {
            $out_tags = array_merge(
                array_keys($this->data[self::VAR_OUT]),
                array_keys($this->data[self::VAR_INOUT]),
            );

            try {
                $sql = 'SELECT '.implode(', ', $out_tags);
                $stmt = self::getPdo($this->current_cnx_id)->query($sql);

                return $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
            } catch (Exception $e) {
                $this->exceptionInterceptor($e, 'extractOutTags');

                return null;
            }
        } else {
            return [];
        }
    }

    //region BUILDING SQL OR/AND STATEMENT

    /**
     * @return bool
     */
    protected function useStatement(): bool
    {
        return ! (empty($this->data[self::VAR_IN_BY_VAL]) && empty($this->data[self::VAR_IN_BY_REF]));
    }

    /**
     * @param $keys Key from $this->data
     * @return array
     */
    protected function getData(...$keys): array
    {
        $data = [];
        foreach ($keys as $k) {
            if ( ! empty($this->data[$k])) {
                foreach ($this->data[$k] as $tag => &$v) {
                    $data[$tag] =& $v;
                }
            }
        }

        return $data;
    }

    /**
     * @param string $sql
     * @param array $prepare_options Used for scrollable cursor
     * @return string
     * @throws Exception
     */
    protected function buildSql(string $sql, array $prepare_options = []): void
    {
        $this->sql = $sql;
        $this->replaceTagsByPlainValues();
        $this->replaceTagsByPlainRefValues();

        if ($this->useStatement()) {
            $get_pdo_type = function (&$v): int {
                if ($v['value'] === null) {
                    return self::getPDOType('null');
                } else {
                    $this->castValueByType($v['value'], $v['type']);

                    return self::getPDOType($v['type']);
                }
            };

            if ($this->params_already_bound === false) {
                // initial binding
                $this->stmt = $this->current_pdo->prepare($this->sql, $prepare_options);
                $data_by_val = $this->getData(self::VAR_IN_BY_VAL);
                $data_by_ref = $this->getData(self::VAR_IN_BY_REF);

                // using ->bindValue()
                foreach ($data_by_val as $tag => $v) {
                    $pdo_type = $get_pdo_type($v);
                    $this->stmt->bindValue($tag, $v['value'], $pdo_type);
                }
                // using ->bindParam()
                foreach ($data_by_ref as $tag => &$v) {
                    $pdo_type = $get_pdo_type($v);
                    $this->stmt->bindParam($tag, $v['value'], $pdo_type);
                    $this->last_bound_type[$tag] = $pdo_type;
                }
                $this->params_already_bound = true;
            } else {
                $data_by_ref = $this->getData(self::VAR_IN_BY_REF);
                foreach ($data_by_ref as $tag => &$v) {
                    $pdo_type = $get_pdo_type($v);
                    // if the types between the current and the previous value are different,
                    // then rebind explicitly the tag especially for null values
                    if ($pdo_type !== $this->last_bound_type[$tag]) {
                        $this->stmt->bindParam($tag, $v['value'], $pdo_type);
                        $this->last_bound_type[$tag] = $pdo_type;
                    }
                }
            }
        }
    }

    protected function replaceTagsByPlainValues(): void
    {
        $this->current_pdo = self::getPdo($this->current_cnx_id);
        $data = $this->getData(self::VAR_IN);
        if ( ! empty($data)) {
            foreach ($data as $tag => &$v) {
                if ( ! isset($v['done'])) {
                    $this->sql = str_replace(
                        $tag,
                        (string)self::getSQLValue($v['value'], $v['type'], $this->current_pdo),
                        $this->sql
                    );
                    $v['done'] = true;
                }
            }
        }
    }

    protected function replaceTagsByPlainRefValues(): void
    {
        $this->current_pdo = self::getPdo($this->current_cnx_id);
        $data = $this->getData(self::VAR_IN_AS_REF);
        if ( ! empty($data)) {
            foreach ($data as $tag => &$v) {
                $this->sql = str_replace(
                    $tag,
                    (string)self::getSQLValue($v['value'], $v['type'], $this->current_pdo),
                    $this->sql
                );
            }
        }
    }
    //endregion BUILDING SQL OR/AND STATEMENT

    //region PROPERTY AUTO-RESET
    /**
     * Active the auto-reset feature
     * Prepare automatically the current instance for a new sql statement
     */
    public function setAutoResetOn(): void
    {
        $this->auto_reset = true;
    }

    /**
     * Deactivate the auto-reset feature
     * You'll not be able to run many statements with the same PDOPlus instance
     */
    public function setAutoResetOff(): void
    {
        $this->auto_reset = false;
    }

    /**
     * @return bool
     */
    public function isAutoResetOn(): bool
    {
        return $this->auto_reset;
    }

    protected function autoReset(): void
    {
        if ($this->auto_reset
            && empty($this->data[self::VAR_IN_BY_REF])
            && empty($this->data[self::VAR_IN_AS_REF])
            && ($this->has_failed === false)
        ) {
            $this->reset();
        }
    }

    /**
     * Reset the instance and prepare it for the next statement
     * No reset of created save points, use ->releaseAll()
     * @throws Exception
     */
    public function reset(): void
    {
        $this->data = [
            self::VAR_IN => [],
            self::VAR_IN_AS_REF => [],
            self::VAR_IN_BY_REF => [],
            self::VAR_IN_BY_VAL => [],
            self::VAR_INOUT => [],
            self::VAR_OUT => [],
        ];
        $this->sql = '';
        $this->stmt = null;
        $this->current_pdo = null;
        $this->has_failed = false;
        $this->params_already_bound = false;
        $this->last_bound_type = [];
        $this->bound_columns = [];
        $this->bind_before_execute = false;
    }
    //endregion

    //region PROPERTY THROW
    /**
     * Enable throwing any exception
     */
    public function setThrowOn(): void
    {
        $this->throw = true;
    }

    /**
     * Disable throwing any exception and the $exception_wrapper is used instead (if defined)
     * @param Closure|null $exception_wrapper
     */
    public function setThrowOff(Closure|null $exception_wrapper): void
    {
        if ($exception_wrapper !== null) {
            self::$exception_wrapper = $exception_wrapper;
        }
        $this->throw = false;
    }

    /**
     * @return bool Tells if the exception wrapper is callable
     */
    protected function isNotThrowable(): bool
    {
        return ($this->throw === false) && isset(static::$exception_wrapper);
    }
    //endregion

    //region EXCEPTION INTERCEPTOR
    /**
     * When an exception closure wrapper is defined then
     * every function will always return null instead of throwing an Exception
     *
     * Closure prototype: function(Exception $e, PDOPlusPlus $pp, string $sql, string $func_name, ...$args) {
     *                         // ...
     *                     };
     * The result of the closure will be available through the method ->getErrorFromWrapper()
     * @param Closure $p
     * @see $this->exceptionInterceptor()
     */
    public static function setExceptionWrapper(Closure $p)
    {
        self::$exception_wrapper = $p;
    }

    /**
     * @param Exception $e
     * @param string $func_name
     * @throws Exception
     */
    protected function exceptionInterceptor(Exception $e, string $func_name): void
    {
        $this->has_failed = true;
        if ($this->isNotThrowable()) {
            $hlp = static::$exception_wrapper;
            $this->error_from_wrapper = $hlp($e, $this, $this->sql, $func_name);
        } else {
            throw $e;
        }
    }

    /**
     * Return the result of the exception wrapper
     *
     * @return mixed
     */
    public function getErrorFromWrapper(): mixed
    {
        return $this->error_from_wrapper;
    }

    //endregion EXCEPTION INTERCEPTOR

    public function closeCursor(): void
    {
        if (isset($this->stmt)) {
            $this->stmt->closeCursor();
        }
    }

    /**
     * @return int The number of active tokens in the current instance
     */
    public function getNbTokens(): int
    {
        return array_reduce($this->data, fn($carry, $item) => $carry + count($item), 0);
    }

    /**
     * Unique tag generator
     * The tag is always unique for the whole current session
     * A tag prepend with ':' is intercepted by the PDO method ->prepare()
     *
     * @param string $prepend
     * @return string
     */
    public static function getTag(string $prepend = ':'): string
    {
        do {
            $tag = $prepend.substr(str_shuffle(self::ALPHA), 0, 7).mt_rand(10000, 99999);
        } while (isset(self::$tags[$tag]));
        self::$tags[$tag] = true;

        return $tag;
    }

    /**
     * Unique tags generator
     * The tags are always unique for the whole current session
     *
     * @param array $keys
     * @return array [key => tag]
     */
    public static function getTags(array $keys): array
    {
        $tags = [];
        for ($i = 0, $nb = count($keys); $i < $nb; ++$i) {
            $tags[] = self::getTag();
        }

        return array_combine($keys, $tags);
    }

    /**
     * @param mixed $value
     * @param string $type among: int str float bool bigint
     * @param PDO $pdo
     * @return bool|int|string plain escaped value
     * @throws Exception
     */
    public static function getSQLValue(mixed $value, string $type, PDO $pdo): bool|int|string
    {
        if ($value === null) {
            return 'NULL';
        } elseif ($type === 'int') {
            return (int)$value;
        } elseif ($type === 'bigint') {
            return (string)$value;
        } elseif ($type === 'bool') {
            return (bool)$value;
        } elseif ($type === 'float') {
            return (string)(float)$value;
        } else {
            return $pdo->quote((string)$value);
        }
    }

    /**
     * By default, everything is a string unless one recognized type
     *
     * @param mixed $value Careful: value is passed by reference and will automatically be cast to the right type
     * @param string $type among: int str float bool binary bigint null
     * @throws InvalidArgumentException
     */
    protected function castValueByType(mixed &$value, string $type): void
    {
        if ($type === 'null') {
            $value = null;
        } elseif ($type === 'int') {
            $value = (int)$value;
        } elseif ($type === 'bigint') {
            // intentionally empty
            // UNSIGNED: ON x64 FROM 0 TO 18446744073709551615
            // SIGNED: ON x64 FROM -9223372036854775808 TO 9223372036854775807
        } elseif ($type === 'bool') {
            $value = (bool)$value;
        } elseif ($type === 'float') {
            $value = (string)(float)$value;
        } elseif ($type === 'binary') {
            // intentionally empty
        } else {
            $value = (string)$value;
        }
    }

    /**
     * @param string $user_type among: int str float bool binary null bigint
     * @return int
     */
    protected static function getPDOType(string $user_type): int
    {
        return match ($user_type) {
            'int' => PDO::PARAM_INT,
            'bool' => PDO::PARAM_BOOL,
            'null' => PDO::PARAM_NULL,
            'binary' => PDO::PARAM_LOB,
            'bigint' => 999,
            default => PDO::PARAM_STR,
        };
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PPP', false);            // PPP is an official alias for PDOPlusPlus