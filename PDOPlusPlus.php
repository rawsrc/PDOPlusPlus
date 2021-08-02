<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

use BadMethodCallException;
use Closure;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use TypeError;

use function array_keys;
use function array_merge;
use function implode;
use function in_array;
use function str_replace;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper with a new revolutionary fluid SQL syntax
 *
 * @link        https://github.com/rawsrc/PDOPlusPlus
 * @author      rawsrc - https://www.developpez.net/forums/u32058/rawsrc/
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
    protected const VAR_IN_SQL = 'in_sql';
    protected const VAR_IN_BY_VAL = 'in_by_val';
    protected const VAR_IN_BY_REF = 'in_by_ref';
    protected const VAR_INOUT_SQL = 'inout_sql';
    protected const VAR_INOUT_BY_VAL = 'inout_by_val';
    protected const VAR_INOUT_BY_REF = 'inout_by_ref';
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
     * @var string|null
     */
    protected static string|null $default_cnx_id = null;
    /**
     * @var string|null
     */
    protected string|null $current_cnx_id = null;
    /**
     * List all generated tags during the current session
     * @var array [tag]
     */
    protected static array $tags = [];
    /**
     * User data injected in the sql string
     * @var array [tag => [value => user value, type => user type]
     */
    protected array $data = [
        self::VAR_IN_SQL => [],
        self::VAR_IN_BY_VAL => [],
        self::VAR_IN_BY_REF => [],
        self::VAR_INOUT_SQL => [],
        self::VAR_INOUT_BY_VAL => [],
        self::VAR_INOUT_BY_REF => [],
        self::VAR_OUT => [],
    ];
    /**
     * @var array
     */
    protected array $last_bound_type_tags_by_ref = [];
    /**
     * @var bool
     */
    protected bool $debug;
    /**
     * @var bool
     */
    protected bool $params_already_bound = false;
    /**
     * @var bool
     */
    protected bool $is_transactional = false;
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
     * @return bool
     */
    protected function hasInOutTags(): bool
    {
        return ! (empty($this->data[self::VAR_INOUT_SQL])
            && empty($this->data[self::VAR_INOUT_BY_VAL])
            && empty($this->data[self::VAR_INOUT_BY_REF]));
    }

    /**
     * @return bool
     */
    protected function hasOutTags(): bool
    {
        return ! (empty($this->data[self::VAR_OUT])
            && empty($this->data[self::VAR_INOUT_BY_VAL])
            && empty($this->data[self::VAR_INOUT_BY_REF])
        );
    }

    /**
     * @param string|null $cnx_id if null then the default connection will be used
     * @param bool $debug
     */
    public function __construct(?string $cnx_id = null, bool $debug = false)
    {
        $this->current_cnx_id = $cnx_id;
        $this->debug = $debug;
    }

    /**
     * When an exception closure wrapper is defined then
     * every function will always return null instead of throwing an Exception
     *
     * Closure prototype: function(Exception $e, PDOPlusPlus $ppp, string $sql, string $func_name, ...$args) {
     *                         // ...
     *                     }
     * The result of the closure will be available through the method ->getError()
     * @param Closure $p
     * @see $this->exceptionInterceptor()
     */
    public static function setExceptionWrapper(Closure $p)
    {
        static::$exception_wrapper = $p;
    }

    /**
     * Return the result of the exception wrapper
     *
     * @return mixed
     */
    public function getErrorFromWapper(): mixed
    {
        return $this->error_from_wrapper;
    }

    /**
     * @param string|null $cnx_id null => default connection
     * @return PDO
     * @throws BadMethodCallException|Exception
     */
    public static function getPdo(?string $cnx_id = null): PDO
    {
        $cnx_id ??= static::$default_cnx_id ?? null;

        if (isset($cnx_id, static::$pdo[$cnx_id])) {
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
     * @param string $cnx_id
     */
    public static function setDefaultConnection(string $cnx_id): void
    {
        static::$default_cnx_id = $cnx_id;
    }

    //region DATABASE CONNECTION

    /**
     * Default parameters for PDO are :
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
        return $this->getInjectorInSqlOrByVal(self::VAR_IN_SQL)(...$args);
    }

    //region TRANSACTION
    /**
     * The SQL "SET TRANSACTION" must be defined before starting
     * a new transaction otherwise it will be ignored
     *
     * @param string $sql
     * @throws Exception
     */
    public function setTransaction(string $sql)
    {
        if ( ! $this->is_transactional) {
            $this->execTransaction($sql, 'setTransaction', false);
        }
    }

    /**
     * @throws Exception
     */
    public function startTransaction()
    {
        // transaction already started
        if ($this->is_transactional) {
            // for nested transaction create internally a save point
            // to be able to rollback only the current transaction
            // as PDO only rollback all transactions at once
            $save_point = self::getTag(prepend: '');
            $this->savePoint($save_point);
        } else {
            $this->execTransaction(
                sql: 'START TRANSACTION;',
                func_name: 'startTransaction',
                final_transaction_status: true,
            );
        }
    }

    /**
     * The commit always apply to the whole transaction at once
     *
     * @throws Exception
     */
    public function commit()
    {
        if ($this->is_transactional) {
            $this->execTransaction(
                sql: 'COMMIT;',
                func_name: 'commit',
                final_transaction_status: false,
            );
        }
    }

    /**
     * Rollback only the last transaction
     *
     * @throws Exception
     */
    public function rollback()
    {
        if ($this->is_transactional) {
            if (empty($this->save_points)) {
                $this->execTransaction(
                    sql: 'ROLLBACK;',
                    func_name: 'rollback',
                    final_transaction_status: false,
                );
            } else {
                $save_point = array_pop($this->save_points);
                $this->rollbackTo($save_point);
            }
        }
    }

    /**
     * Rollback the whole transaction at once
     *
     * @throws Exception
     */
    public function rollbackAll()
    {
        $this->save_points = [];
        $this->rollback();
    }

    /**
     * Create a save point
     *
     * @param string $point_name
     * @throws Exception
     */
    public function savePoint(string $point_name)
    {
        if ($this->is_transactional) {
            $this->execTransaction(
                sql: "SAVEPOINT {$point_name};",
                func_name: 'savePoint',
                final_transaction_status: null,
            );
            $this->save_points[] = $point_name;
        }
    }

    /**
     * @param string $point_name
     * @throws Exception
     */
    public function rollbackTo(string $point_name)
    {
        if ($this->is_transactional) {
            $this->execTransaction(
                sql: "ROLLBACK TO {$point_name};",
                func_name: 'rollbackTo',
                final_transaction_status: null,
            );
        }
    }

    /**
     * Release a save point
     *
     * @param string $point_name
     * @throws Exception
     */
    public function release(string $point_name)
    {
        if ($this->is_transactional && in_array($point_name, $this->save_points, true)) {
            $this->execTransaction(
                sql: "RELEASE SAVEPOINT {$point_name};",
                func_name: 'release',
                final_transaction_status: null,
            );
            $pos = array_search($point_name, $this->save_points, true);
            unset($this->save_points[$pos]);
        }
    }

    /**
     * @throws Exception
     */
    public function releaseAll()
    {
        foreach ($this->save_points as $point) {
            $this->release($point);
        }
    }

    /**
     * Common code
     *
     * @param string $sql
     * @param string $func_name
     * @param bool|null $final_transaction_status
     * @throws Exception
     */
    private function execTransaction(string $sql, string $func_name, ?bool $final_transaction_status)
    {
        try {
            self::getPdo($this->current_cnx_id)->exec($sql);
            if ($final_transaction_status !== null) {
                $this->is_transactional = $final_transaction_status;
            }
            if ($this->is_transactional === false) {
                $this->save_points = [];
            }
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $sql, $func_name);
            if (isset(static::$exception_wrapper)) {
                return null;
            } else {
                throw $e;
            }
        }
    }
    //endregion

    //region INJECTORS
    /**
     * Injector for values using sql direct escaping
     *
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInSql(?string $data_type = null): object
    {
        return $this->getInjectorInSqlOrByVal(self::VAR_IN_SQL, $data_type);
    }

    /**
     * Injector for values using the $pdo->bindValue() mechanism
     *
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInByVal(?string $data_type = null): object
    {
        return $this->getInjectorInSqlOrByVal(self::VAR_IN_BY_VAL, $data_type);
    }

    /**
     * Injector
     * Mode in_sql => the value is directly escaped in the sql string
     * Mode in_by_val => the value is bound using the PDO mechanism ->bindValue()
     *
     * @param string $mode in_sql|in_by_val
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    protected function getInjectorInSqlOrByVal(string $mode, ?string $data_type = null): object
    {
        return new class($this->data, $mode, $data_type) {
            private array $data;
            private string $mode;
            private ?string $locked_type;

            /**
             * @param string $type among: int str float double num numeric bool
             */
            public function setLockType(string $type)
            {
                $this->locked_type = $type;
            }

            /**
             * @param array $data
             * @param string $mode
             * @param string|null $data_type
             */
            public function __construct(array &$data, string $mode, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->mode = $mode;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $type
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
                $this->data[$this->mode][$tag] = [
                    'value' => $value,
                    'type' => $this->locked_type ?? $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for values using the $pdo->bindParam() mechanism
     *
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInByRef(?string $data_type = null): object
    {
        return new class($this->data, $data_type) {
            private array $data;
            private ?string $locked_type;

            /**
             * @param string $type among: int str float double num numeric bool
             */
            public function setLockType(string $type)
            {
                $this->locked_type = $type;
            }

            /**
             * @param array $data
             * @param string|null $data_type
             */
            public function __construct(array &$data, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(mixed &$value, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data['in_by_ref'][$tag] = [
                    'value' => &$value,
                    'type' => $this->locked_type ?? $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for params having only OUT attribute
     * @return object
     */
    public function getInjectorOut(): object
    {
        return new class($this->data) {
            private array $data;

            /**
             * @param array $data
             */
            public function __construct(array &$data)
            {
                $this->data =& $data;
            }

            /**
             * @param string $out_tag ex:'@id'
             * @return string
             */
            public function __invoke(string $out_tag): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data['out'][$tag] = ['value' => $out_tag];

                return $tag;
            }
        };
    }

    /**
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInOutSql(?string $data_type = null): object
    {
        return $this->getInjectorInOutSqlOrByVal(self::VAR_INOUT_SQL, $data_type);
    }

    /**
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInOutByVal(?string $data_type = null): object
    {
        return $this->getInjectorInOutSqlOrByVal(self::VAR_INOUT_BY_VAL, $data_type);
    }

    /**
     * Injector for by val params having IN OUT attribute
     * Mode inout_sql    => the value is directly escaped in the sql string
     * Mode inout_by_val => the value is bound using the PDO mechanism ->bindValue()
     *
     * @param string $mode inout_sql|inout_by_val
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    protected function getInjectorInOutSqlOrByVal(string $mode, ?string $data_type = null): object
    {
        return new class($this->data, $mode, $data_type) {
            private array $data;
            private string $mode;
            private ?string $locked_type;

            /**
             * @param string $type among: int str float double num numeric bool
             */
            public function setLockType(string $type)
            {
                $this->locked_type = $type;
            }

            /**
             * @param array $data
             * @param string $mode
             * @param string|null $data_type
             */
            public function __construct(array &$data, string $mode, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->mode = $mode;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(mixed $value, string $inout_tag, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data[$this->mode][$tag] = [
                    'value' => $value,
                    'type' => $this->locked_type ?? $type,
                ];

                return $tag;
            }
        };
    }

    /**
     * Injector for by ref params having IN OUT attribute
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function getInjectorInOutByRef(?string $data_type = null): object
    {
        return new class($this->data, $data_type)
        {
            private array $data;
            private ?string $locked_type;

            /**
             * @param string $type  among: int str float double num numeric bool
             */
            public function setLockType(string $type)
            {
                $this->locked_type = $type;
            }

            /**
             * @param array $data
             * @param array $sp_inout_tags
             * @param string|null $data_type
             */
            public function __construct(array &$data, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $inout_tag ex: '@id'
             * @param string $type among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(mixed &$value, string $inout_tag, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::getTag();
                $this->data['inout_by_ref'][$tag] = [
                    'value' => &$value,
                    'type' => $this->locked_type ?? $type,
                ];

                return $tag;
            }
        };
    }
    //endregion

    /**
     * @param string $sql
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return string|null lastInsertId() | null on error
     * @throws Exception
     */
    public function insert(string $sql, bool $auto_reset = true): ?string
    {
        try {
            $result = $this->builtPrepareAndAttachValuesOrParams($sql);
            $pdo = self::getPdo($this->current_cnx_id);
            if ($result === true) {
                $this->stmt->execute();
            } else {
                $pdo->exec($result);
            }

            $this->autoReset($auto_reset);

            return $pdo->lastInsertId();
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $sql, 'insert');
            if (isset(static::$exception_wrapper)) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param mixed $sql
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return array|null
     * @throws Exception
     */
    public function select(string $sql, bool $auto_reset = true): ?array
    {
        $this->createStmt($sql, []);
        if ($this->stmt instanceof PDOStatement) {
            $data = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->autoReset($auto_reset);

            return $data;
        } else {
            return null;
        }
    }

    /**
     * @param string $sql
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmt(string $sql): ?PDOStatement
    {
        return $this->createStmt($sql, []);
    }

    /**
     * @param string $sql
     * @param array $driver_options
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmtAsScrollableCursor(string $sql, array $driver_options = []): ?PDOStatement
    {
        $driver_options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL] + $driver_options;

        return $this->createStmt($sql, $driver_options);
    }

    /**
     * @param string $sql
     * @param array $prepare_options
     * @return false|PDOStatement|null
     * @throws Exception
     */
    private function createStmt(string $sql, array $prepare_options): false|PDOStatement|null
    {
        try {
            $result = $this->builtPrepareAndAttachValuesOrParams($sql, $prepare_options);
            if ($result === true) {
                $this->stmt->execute();
            } else {
                $this->stmt = self::getPdo($this->current_cnx_id)->query($result);
            }

            return $this->stmt;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $sql, 'select');
            if (isset(static::$exception_wrapper)) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param string $sql
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function update(string $sql, bool $auto_reset = true): ?int
    {
        return $this->execute($sql, $auto_reset);
    }

    /**
     * @param string $sql
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function delete(string $sql, bool $auto_reset = true): ?int
    {
        return $this->execute($sql, $auto_reset);
    }

    /**
     * @param string $sql
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return int|null nb of affected rows
     * @throws Exception
     */
    public function execute(string $sql, bool $auto_reset = true): ?int
    {
        try {
            $result = $this->builtPrepareAndAttachValuesOrParams($sql);
            if ($result === true) {
                $this->stmt->execute();
                $nb = $this->stmt->rowCount();
                $this->autoReset($auto_reset);

                return $nb;
            } else {
                $nb = self::getPdo($this->current_cnx_id)->exec($result);
                $this->autoReset($auto_reset);

                return $nb;
            }
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $sql, 'execute');
            if (isset(static::$exception_wrapper)) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param string $sql
     * @param bool $is_query
     * @param bool $auto_reset Reset and prepare the current instance for a new sql statement if there's no BY REF tags
     * @return mixed
     * @throws Exception
     */
    public function call(string $sql, bool $is_query, bool $auto_reset = true): mixed
    {
        try {
            $pdo = self::getPdo($this->current_cnx_id);

            $inject_io_values = function() use ($pdo) {
                if ($this->hasInOutTags()) {
                    $out = array_merge(
                        $this->data[self::VAR_OUT],
                        $this->data[self::VAR_INOUT_BY_VAL],
                        $this->data[self::VAR_INOUT_BY_REF],
                    );
                    $sql = [];
                    foreach ($out as $tag => $io) {
                        // Injecting one by one io_params's value using SQL syntax : "SET @io_param = value"
                        $sql[] = "SET {$io} = ".self::sqlValue(
                            value: $this->data[$tag]['value'],
                            type: $this->data[$tag]['type'],
                            for_pdo: false,
                            cnx_id: $this->current_cnx_id,
                        );
                    }
                    $sql = implode(';', $sql);
                    $pdo->exec($sql);
                }
            };

            $result = $this->builtPrepareAndAttachValuesOrParams($sql);
            $inject_io_values();

            if ($result === true) {
                $this->stmt->execute();
            } elseif ($is_query) {
                // SQL Direct and Query
                $this->stmt = $pdo->query($result);
            } else {
                // SQL Direct
                if ($this->hasOutTags()) {
                    $pdo->exec($result);

                    return ['out' => $this->extractOutParams()];
                } else {
                    return $pdo->exec($result);
                }
            }

            $data    = [];
            $nb_rows = 0;
            if ($is_query) {
                do {
                    $row = $this->stmt->fetchAll();
                    if ($row) {
                        $data[] = $row;
                        ++$nb_rows;
                    } else {
                        break;
                    }
                } while ($this->stmt->nextRowset());
            }

            // adding the OUT params values
            if ($this->hasOutTags()) {
                $data['out'] = $this->extractOutParams();
            }

            if ($auto_reset && empty($this->data[self::VAR_INOUT_BY_REF])) {
                $this->reset();
            }

            return $data;
        } catch (Exception $e) {
            $this->exceptionInterceptor($e, $sql, 'call');
            if (isset(static::$exception_wrapper)) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * @return array|null [out_param => value]
     * @throws Exception
     */
    public function extractOutParams(): ?array
    {
        if ($this->hasOutTags()) {
            try {
                $out_tags = array_merge(
                    array_keys($this->data[self::VAR_OUT]),
                    array_keys($this->data[self::VAR_INOUT_BY_VAL]),
                    array_keys($this->data[self::VAR_INOUT_BY_REF]),
                );
                $sql = 'SELECT '.implode(', ', $out_tags);
                $stmt = self::getPdo($this->current_cnx_id)->query($sql);

                return $stmt->fetchAll()[0];
            } catch (Exception $e) {
                $this->exceptionInterceptor($e, $sql, 'extractOutParams');
                if (isset(static::$exception_wrapper)) {
                    return null;
                } else {
                    throw $e;
                }
            }
        } else {
            return [];
        }
    }

    /**
     * @param string $modes
     * @return array [tag => data]
     */
    private function getTagsAndDataByMode(string ...$modes): array
    {
        $data = [];
        foreach ($this->data as $mode => &$v) {
            if (in_array($mode, $modes, true)) {
                foreach ($v as $tag => &$z) {
                    $data[$tag] =& $z;
                }
            }
        }

        return $data;
    }

    /**
     * @param string $sql
     * @param array $prepare_options
     * @return bool|string  true if the statement has been prepared or string for the plain escaped sql
     * @throws Exception
     */
    private function builtPrepareAndAttachValuesOrParams(string $sql, array $prepare_options = []): string|bool
    {
        // replace tags by the out param value
        foreach ($this->data[self::VAR_OUT] as $tag => $v) {
            $sql = str_replace($tag, (string)$v['value'], $sql);
        }

        // replace tags by plain sql values
        foreach ($this->getTagsAndDataByMode(self::VAR_IN_SQL, self::VAR_INOUT_SQL) as $tag => $v) {
            $sql = str_replace($tag, (string)self::sqlValue($v['value'], $v['type'], false, $this->current_cnx_id), $sql);
        }

        // stop if there's no need to create a statement
        if (empty($this->getTagsAndDataByMode(
            self::VAR_IN_BY_VAL,
            self::VAR_IN_BY_REF,
            self::VAR_INOUT_BY_VAL,
            self::VAR_INOUT_BY_REF,
        ))) {
            return $sql;
        }

        /**
         * @param string $p among: int str float double num numeric bool
         * @return int
         */
        $pdo_type = fn(string $p): int => [
            'null' => PDO::PARAM_NULL,
            'int'  => PDO::PARAM_INT,
            'bool' => PDO::PARAM_BOOL,
        ][$p] ?? PDO::PARAM_STR;

        // initial binding
        if ($this->params_already_bound === false) {
            if ( ! ($this->stmt instanceof PDOStatement)) {
                $this->stmt = self::getPdo($this->current_cnx_id)->prepare($sql, $prepare_options);
            }

            // data using ->bindValue()
            foreach ($this->getTagsAndDataByMode(self::VAR_IN_BY_VAL, self::VAR_INOUT_BY_VAL) as $tag => $v) {
                $pdo_value = self::sqlValue($v['value'], $v['type'], true);
                $this->stmt->bindValue($tag, $pdo_value[0], $pdo_value[1]);
            }

            // data using ->bindParam()
            foreach ($this->getTagsAndDataByMode(self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF) as $tag => &$v) {
                $type = $pdo_type($v['type']);
                $this->stmt->bindParam($tag, $v['value'], $type);
                $this->last_bound_type_tags_by_ref[$tag] = $type;
            }
            $this->params_already_bound = true;
        }

        // explicit cast for values by ref and rebinding for null values
        $data_by_ref = $this->getTagsAndDataByMode(self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF);
        foreach ($data_by_ref as $tag => &$v) {
            $current = self::sqlValue($v['value'], $v['type'], true);
            // if the current value is null and the previous is not then rebind explicitly the param according to null
            // and the same in the opposite way
            $current_type = $current[1];
            $previous_type = $this->last_bound_type_tags_by_ref[$tag];
            if (($current_type === PDO::PARAM_NULL) && ($previous_type !== PDO::PARAM_NULL)) {
                $this->stmt->bindParam($tag, $v['value'], PDO::PARAM_NULL);
                $this->last_bound_type_tags_by_ref[$tag] = PDO::PARAM_NULL;
            } elseif (($current_type !== PDO::PARAM_NULL) && ($previous_type === PDO::PARAM_NULL)) {
                // rebind the parameter to the initial type
                $this->stmt->bindParam($tag, $v['value'], $pdo_type($v['type']));
                $this->last_bound_type_tags_by_ref[$tag] = $pdo_type($v['type']);
            }

            // explicit cast for var by ref
            if ($v['value'] !== null) {
                $v['value'] = $current[0];
            }
        }

        return true;
    }

    /**
     * @param bool $auto_reset
     */
    private function autoReset(bool $auto_reset): void
    {
        if ($auto_reset && empty($this->data[self::VAR_IN_BY_REF])) {
            $this->reset();
        }
    }

    private function resetData(): void
    {
        $this->data = [
            self::VAR_IN_SQL => [],
            self::VAR_IN_BY_VAL => [],
            self::VAR_IN_BY_REF => [],
            self::VAR_INOUT_SQL => [],
            self::VAR_INOUT_BY_VAL => [],
            self::VAR_INOUT_BY_REF => [],
            self::VAR_OUT => [],
        ];
        $this->params_already_bound = false;
        $this->last_bound_type_tags_by_ref = [];
    }
    /**
     * Reset the instance and prepare it for the next statement
     */
    public function reset(): void
    {
        $this->resetData();
        $this->stmt = null;
    }

    /**
     * @param Exception $e
     * @param string $sql
     * @param string $func_name
     */
    private function exceptionInterceptor(Exception $e, string $sql, string $func_name): void
    {
        if ($this->debug) {
            var_dump($sql);
        }
        if (isset(static::$exception_wrapper)) {
            $hlp = static::$exception_wrapper;
            $this->error_from_wrapper = $hlp($e, $this, $sql, $func_name);
        }
    }

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
     * @param string|null $cnx_id if null => default connection
     * @return array|bool|int|string if $for_pdo => [0 => value, 1 => pdo type] | plain escaped value
     * @throws Exception
     */
    public static function sqlValue($value, string $type, bool $for_pdo, ?string $cnx_id = null): array|bool|int|string
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
        } else {
            $v = (string)$value;
            if ($for_pdo) {
                return [$v, PDO::PARAM_STR];
            } else {
                return self::getPdo($cnx_id)->quote($v, PDO::PARAM_STR);
            }
        }
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PPP', false);            // PPP is an official alias for PDOPlusPlus