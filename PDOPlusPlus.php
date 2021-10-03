<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

include_once 'AbstractInjector.php';

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
use function array_merge;
use function array_pop;
use function array_search;
use function array_splice;
use function count;
use function implode;
use function in_array;
use function is_object;
use function is_scalar;
use function method_exists;
use function str_replace;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper with a new revolutionary fluid SQL syntax
 *
 * @link        https://github.com/rawsrc/PDOPlusPlus
 * @author      rawsrc
 * @copyright   MIT License
 *
 *              Copyright (c) 2021+ rawsrc
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
     * User data: 7 different injectors
     */
    protected const VAR_IN = 'in';
    protected const VAR_IN_BY_REF = 'in_by_ref';
    protected const VAR_IN_BY_VAL = 'in_by_val';
    protected const VAR_INOUT = 'inout';
    protected const VAR_INOUT_BY_REF = 'inout_by_ref';
    protected const VAR_INOUT_BY_VAL = 'inout_by_val';
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
        self::VAR_IN_BY_REF => [],
        self::VAR_IN_BY_VAL => [],
        self::VAR_INOUT => [],
        self::VAR_INOUT_BY_REF => [],
        self::VAR_INOUT_BY_VAL => [],
        self::VAR_OUT => [],
    ];
    /**
     * @var string Build sql string
     */
    protected string $sql = '';
    /**
     * @var PDOStatement|null
     */
    protected PDOStatement|null $stmt;
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
     * @var array
     */
    protected array $last_bound_type_tags_by_ref = [];

    /**
     * The auto-reset feature prepare automatically the current instance for a new sql statement if there's no
     * BY REF
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
            && empty($this->data[self::VAR_INOUT_BY_REF])
            && empty($this->data[self::VAR_INOUT_BY_VAL])
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
            static::$pdo[$cnx_id] = self::connect(
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
            throw new Exception('No connection is available');
        }
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
            static::$cnx_params[$cnx_id] = $params;
            if ($is_default) {
                static::$default_cnx_id = $cnx_id;
            }
        } else {
            throw new BadMethodCallException('Invalid connection parameters');
        }
    }

    /**
     * @param int|string|null $cnx_id
     */
    public static function setDefaultConnection(int|string|null $cnx_id): void
    {
        static::$default_cnx_id = $cnx_id;
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
     * @param string $database database name
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
        array $pdo_params = [],
        array $dsn_params = [],
    ): PDO {
        $dsn = "{$scheme}:host={$host};dbname={$database};";

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
        return $this->getInjectorIn(self::VAR_IN)(...$args);
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
        $this->execTransaction(
            sql: 'START TRANSACTION;',
            func_name: 'startTransaction',
        );
    }

    /**
     * The commit always apply to the whole transaction at once
     *
     * @throws Exception
     */
    public function commit(): void
    {
        $this->execTransaction(
            sql: 'COMMIT;',
            func_name: 'commit',
        );
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
            $save_point = array_pop($this->save_points);
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
            // roll back all commands that were executed after the savepoint was established.
            // implicitly destroys all save points that were established after the named savepoint
            $this->execTransaction(
                sql: "ROLLBACK TO {$save_point};",
                func_name: 'rollbackTo',
            );
            $pos = array_search($save_point, $this->save_points, true);
            array_splice($this->save_points, $pos + 1);
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
            sql: 'ROLLBACK;',
            func_name: 'rollback',
        );
        $this->save_points = [];
    }

    /**
     * Create a save point
     *
     * @param string $save_point_name
     * @throws Exception
     */
    public function createSavePoint(string $save_point_name)
    {
        $this->execTransaction(
            sql: "SAVEPOINT {$save_point_name};",
            func_name: 'savePoint',
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
                sql: "RELEASE SAVEPOINT {$save_point};",
                func_name: 'release',
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
                sql: "RELEASE SAVEPOINT {$save_point};",
                func_name: 'release',
            );
        }
        $this->save_points = [];
    }

    /**
     * @param string $sql
     * @param string $func_name
     * @return void|null
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
     *
     * @param string|null $final_injector_type Define and lock the type of the value among: int str float double num numeric bool binary
     * @return AbstractInjector
     * @throws TypeError
     */
    public function getInjectorIn(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             * @throws TypeError
             */
            public function __invoke(mixed $value, string $type = 'str'): string
            {
                $is_scalar = fn(mixed $p): bool => ($p === null) || is_scalar($p) || (is_object($p) && method_exists($p, '__toString'));

                if ( ! $is_scalar($value)) {
                    throw new TypeError('Null or scalar value expected or class with __toString() implemented');
                }

                $tag = PDOPlusPlus::getTag();
                $this->data['in'][$tag] = [
                    'value' => $value,
                    'type' => $this->final_injector_type ?? $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for referenced values using a statement with ->bindParam()
     *
     * @param string|null $final_injector_type Define and lock the type of the value among: int str float double num numeric bool binary
     * @return AbstractInjector
     */
    public function getInjectorInByRef(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             */
            public function __invoke(mixed &$value, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data['in_by_ref'][$tag] = [
                    'value' => &$value,
                    'type' => $this->final_injector_type ?? $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for values using a statement with ->bindValue()
     *
     * @param string|null $final_injector_type Define and lock the type of the value among: int str float double num numeric bool binary
     * @return AbstractInjector
     * @throws TypeError
     */
    public function getInjectorInByVal(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             * @throws TypeError
             */
            public function __invoke(mixed $value, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data['in_by_val'][$tag] = [
                    'value' => $value,
                    'type' => $this->final_injector_type ?? $type,
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
     * @param string|null $final_injector_type  Define and lock the type of the value among: int str float double num numeric bool
     * @return AbstractInjector
     */
    public function getInjectorInOut(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             */
            public function __invoke(mixed $value, string $inout_tag, string $type = 'str'): string
            {
                $this->data['inout'][$inout_tag] = [
                    'value' => $value,
                    'type' => $this->final_injector_type ?? $type,
                ];

                return $inout_tag;
            }
        };
    }

    /**
     * Injector for a referenced INOUT attribute using a prepared statement with ->bindParam()
     *
     * @param string|null $final_injector_type Define and lock the type of the value among: int str float double num numeric bool
     * @return AbstractInjector
     */
    public function getInjectorInOutByRef(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             */
            public function __invoke(mixed &$value, string $inout_tag, string $type = 'str'): string
            {
                $this->data['inout_by_ref'][$inout_tag] = [
                    'value' => &$value,
                    'type' => $this->final_injector_type ?? $type,
                ];

                return $inout_tag;
            }
        };
    }

    /**
     * Injector for an INOUT attribute using a prepared statement with ->bindValue()
     *
     * @param string|null $final_injector_type Define and lock the type of the value among: int str float double num numeric bool
     * @return AbstractInjector
     */
    public function getInjectorInOutByVal(?string $final_injector_type = null): AbstractInjector
    {
        return new class($this->data, $final_injector_type) extends AbstractInjector {
            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             */
            public function __invoke(mixed $value, string $inout_tag, string $type = 'str'): string
            {
                $this->data['inout_by_val'][$inout_tag] = [
                    'value' => $value,
                    'type' => $this->final_injector_type ?? $type,
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
    public function getInjectorOut(): AbstractInjector
    {
        return new class($this->data) extends AbstractInjector {
            /**
             * @param string $out_tag ex:'@id'
             * @param string $type among: int str float double num numeric bool binary
             * @return string
             */
            public function __invoke(string $out_tag, string $type = 'str'): string
            {
                $this->data['out'][$out_tag] = [
                    'type' => $this->final_injector_type ?? $type,
                ];

                return $out_tag;
            }
        };
    }
    //endregion OUT
    //endregion INJECTORS

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
            $this->autoReset();

            return $this->current_pdo->lastInsertId();
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'insert');

            return null;
        }
    }

    /**
     * For select statements with selected binary columns, the method binds the columns
     * using the array $bound_columns and return the PDOStatement instead of an array of result
     * As binary columns may be very large and usable as a stream, it's not possible to fetch them all at once as usual
     *
     * The string type is among: int str bool binary null
     * The size is not required, it's depends on the database engine requirements'
     *
     * When you have a PDOStatement, you have to read the data using PDO::FETCH_BOUND
     * while ($row = $stmt->fetch(PDO::FETCH_BOUND) {
     *     ...
     * }
     *
     * @link https://www.php.net/manual/en/pdo.lobs.php
     *
     * @param mixed $sql
     * @param array $bound_columns [sql col name => [0 => $var name, 1 => string type, 2 => size]]
     * @return array|PDOStatement|null
     * @throws Exception
     */
    public function select(string $sql, array &$bound_columns = []): array|PDOStatement|null
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->stmt->execute();
            } else {
                $this->stmt = $this->current_pdo->query($this->sql);
            }

            if (empty($bound_columns)) {
                $data = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $this->bindColumns($bound_columns);

                return $this->stmt;
            }
            $this->autoReset();

            return $data;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'select');

            return null;
        }
    }

    /**
     * @see select()
     *
     * @param string $sql
     * @param array $bound_columns
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmt(string $sql, array &$bound_columns = []): PDOStatement|null
    {
        try {
            $this->buildSql($sql);
            if ($this->useStatement()) {
                $this->stmt->execute();
            } else {
                $this->stmt = $this->current_pdo->query($this->sql);
            }

            if ( ! empty($bound_columns)) {
                $this->bindColumns($bound_columns);
            }

            return $this->stmt;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'selectStmt');

            return null;
        }
    }

    /**
     * @see select()
     *
     * @param string $sql
     * @param array $bound_columns
     * @param array $driver_options Others options than scrollable cursor (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL)
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmtAsScrollableCursor(string $sql, array &$bound_columns = [], array $driver_options = []): PDOStatement|null
    {
        $driver_options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL] + $driver_options;

        try {
            $this->buildSql($sql, $driver_options);
            if ( ! $this->useStatement()) {
                $this->stmt = $this->current_pdo->prepare($this->sql, $driver_options);
            }
            $this->stmt->execute();

            if ( ! empty($bound_columns)) {
                $this->bindColumns($bound_columns);
            }

            return $this->stmt;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, 'selectStmtAsScrollableCursor');

            return null;
        }
    }

    /**
     * The string type is among: int str bool binary null
     * The size is not required, it's depends on the database engine requirements'
     *
     * @param array $bound_columns [sql col name => [0 => $var name, 1 => string type, 2 => size]]
     */
    protected function bindColumns(array &$bound_columns): void
    {
        foreach ($bound_columns as $sql_col => &$var) {
            $type = self::getPDOType($var[1]);
            if (isset($var[2])) {
                $this->stmt->bindColumn($sql_col, $var[0], $type, $var[2]);
            } else {
                $this->stmt->bindColumn($sql_col, $var[0], $type);
            }
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
            $pdo = self::getPdo($this->current_cnx_id);
            $this->current_pdo = $pdo;

            // FOR STORED PROCEDURE: UNIVERSAL SQL SYNTAX : "SET @io_param = value"
            $params = [];
            $set = fn(string $tag, array &$v) =>
                "SET {$tag} = ".self::sqlValue(
                    value: $v['value'],
                    type: $v['type'],
                    for_pdo: false,
                    pdo: $pdo,
                );

            foreach ($this->data[self::VAR_INOUT] as $tag => $v) {
                $params[] = $set($tag, $v);
            }
            foreach ($this->data[self::VAR_INOUT_BY_REF] as $tag => &$v) {
                $params[] = $set($tag, $v);
            }

            $pdo->exec(implode(';', $params));

            if ($is_query) {
                // SQL Direct and Query
                $this->stmt = $pdo->query($sql);
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
                    $pdo->exec($sql);
                    $out = $this->extractOutTags();
                    $this->autoReset();

                    return ['out' => $out];
                } else {
                    $nb = $pdo->exec($sql);
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
                array_keys($this->data[self::VAR_INOUT_BY_REF]),
                array_keys($this->data[self::VAR_INOUT_BY_VAL]),
            );

            try {
                $sql = 'SELECT '.implode(', ', $out_tags);
                $stmt = self::getPdo($this->current_cnx_id)->query($sql);

                return $stmt->fetchAll()[0];
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
        return ! (empty($this->data[self::VAR_IN_BY_VAL])
            && empty($this->data[self::VAR_IN_BY_REF])
            && empty($this->data[self::VAR_INOUT_BY_VAL])
            && empty($this->data[self::VAR_INOUT_BY_REF]));
    }

    /**
     * @return bool
     */
    protected function hasBinaryData(): bool
    {
        foreach ($this->data as $k => $values) {
            foreach ($values as $tag => $v) {
                if ($v['type'] === 'binary') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param ...$keys Key from $this->data
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
        $this->current_pdo = self::getPdo($this->current_cnx_id);

        $replace = fn(string $tag, array &$v, string $sql) =>
            str_replace($tag, (string)self::sqlValue(
                value: $v['value'],
                type: $v['type'],
                for_pdo: false,
                pdo: $this->current_pdo
            ), $sql);

        // replace internal tags by plain escaped sql values
        foreach ($this->getData(self::VAR_IN, self::VAR_INOUT) as $tag => &$v) {
            $sql = $replace($tag, $v, $sql);
        }

        $this->sql = $sql;

        // stop if there's no need to create a statement
        if ( ! $this->useStatement()) {
           return;
        }

        // initial binding
        if ( ! ($this->stmt instanceof PDOStatement)) {
            $this->stmt = $this->current_pdo->prepare($this->sql, $prepare_options);

            // using ->bindValue()
            foreach ($this->getData(self::VAR_IN_BY_VAL, self::VAR_INOUT_BY_VAL) as $tag => $v) {
                [$value, $type] = self::sqlValue($v['value'], $v['type'], for_pdo: true);
                $this->stmt->bindValue($tag, $value, $type);
            }
            // using ->bindParam()
            foreach ($this->getData(self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF) as $tag => &$v) {
                $type = self::getPDOType($v['type']);
                $this->stmt->bindParam($tag, $v['value'], $type);
                $this->last_bound_type_tags_by_ref[$tag] = $type;
            }
        }

        // explicit cast for values by ref and rebinding for null values
        $data_by_ref = $this->getData(self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF);
        foreach ($data_by_ref as $tag => &$v) {
            $current = self::sqlValue(
                value: $v['value'],
                type: $v['type'],
                for_pdo: true,
            );
            // if the current value is null and the previous is not then rebind explicitly the param according to null
            // and the same in the opposite way
            $current_type = $current[1];
            $previous_type = $this->last_bound_type_tags_by_ref[$tag];
            if (($current_type === PDO::PARAM_NULL) && ($previous_type !== PDO::PARAM_NULL)) {
                $this->stmt->bindParam($tag, $v['value'], PDO::PARAM_NULL);
                $this->last_bound_type_tags_by_ref[$tag] = PDO::PARAM_NULL;
            } elseif (($current_type !== PDO::PARAM_NULL) && ($previous_type === PDO::PARAM_NULL)) {
                // rebind the parameter to the initial type
                $type = self::getPDOType($v['type']);
                $this->stmt->bindParam($tag, $v['value'], $type);
                $this->last_bound_type_tags_by_ref[$tag] = $type;
            }

            // explicit cast for var by ref
            if ($v['value'] !== null) {
                $v['value'] = $current[0];
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
    public function isAutoResetActivated(): bool
    {
        return $this->auto_reset;
    }

    protected function autoReset(): void
    {
        if ($this->auto_reset && ($this->has_failed === false)) {
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
            self::VAR_IN_BY_REF => [],
            self::VAR_IN_BY_VAL => [],
            self::VAR_INOUT => [],
            self::VAR_INOUT_BY_REF => [],
            self::VAR_INOUT_BY_VAL => [],
            self::VAR_OUT => [],
        ];
        $this->sql = '';
        $this->stmt = null;
        $this->current_pdo = null;
        $this->has_failed = false;
        $this->last_bound_type_tags_by_ref = [];
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
            static::$exception_wrapper = $exception_wrapper;
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
     * Closure prototype: function(Exception $e, PDOPlus $pp, string $sql, string $func_name, ...$args) {
     *                         // ...
     *                     };
     * The result of the closure will be available through the method ->getErrorFromWrapper()
     * @param Closure $p
     * @see $this->exceptionInterceptor()
     */
    public static function setExceptionWrapper(Closure $p)
    {
        static::$exception_wrapper = $p;
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
     * Unique tag generator
     * The tag is always unique for the whole current session
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
        for ($i = 0, $nb = count($keys) ; $i < $nb ; ++$i) {
            $tags[] = self::getTag();
        }

        return array_combine($keys, $tags);
    }

    /**
     * @param $value
     * @param string $type among: int str float double num numeric bool
     * @param bool $for_pdo
     * @param PDO|null $pdo
     * @param string|null $cnx_id if null => default connection
     * @return array|bool|int|string if $for_pdo => [0 => value, 1 => pdo type] | plain escaped value
     * @throws Exception
     */
    public static function sqlValue($value, string $type, bool $for_pdo, ?PDO $pdo, ?string $cnx_id = null): array|bool|int|string
    {
        if ($value === null) {
            return $for_pdo ? [null, PDO::PARAM_NULL] : 'NULL';
        } elseif ($type === 'int') {
            $v = (int)$value;

            return $for_pdo ? [$v, PDO::PARAM_INT] : $v;
        } elseif ($type === 'bool') {
            $v = (bool)$value;

            return $for_pdo ? [$v, PDO::PARAM_BOOL] : $v;
        } elseif (in_array($type, ['float', 'double', 'num', 'numeric'], true)) {
            $v = (string)(double)$value;

            return $for_pdo ? [$v, PDO::PARAM_STR] : $v;
        } elseif ($type === 'binary') {
            if ($for_pdo) {
                return [$value, PDO::PARAM_LOB];
            } else {
                return '0x'.$value;
            }
        } else {
            $v = (string)$value;
            if ($for_pdo) {
                return [$v, PDO::PARAM_STR];
            } else {
                $pdo ??= self::getPdo($cnx_id);

                return $pdo->quote($v, PDO::PARAM_STR);
            }
        }
    }

    /**
     * @param string $user_type among: int str float double num numeric bool binary
     * @return int
     */
    protected static function getPDOType(string $user_type): int
    {
        return [
            'int' => PDO::PARAM_INT,
            'bool' => PDO::PARAM_BOOL,
            'null' => PDO::PARAM_NULL,
            'binary' => PDO::PARAM_LOB,
        ][$user_type] ?? PDO::PARAM_STR;
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PPP', false);            // PPP is an official alias for PDOPlusPlus