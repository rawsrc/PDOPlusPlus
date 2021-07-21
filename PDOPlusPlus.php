<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

use BadMethodCallException;
use Closure;
use Exception;
use PDO;
use PDOStatement;
use TypeError;

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
     * User variables: 7 different injectors
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
     * @var string
     */
    protected static string $default_cnx_id;
    /**
     * @var string|null
     */
    protected string $current_cnx_id;
    /**
     * List all generated tags during the current session
     * @var array [tag]
     */
    protected static array $tags = [];
    /**
     * User data injected in the sql string
     * @var array [tag => [value => user value, type => user type, mode => const VAR_xxx]
     */
    protected array $data = [];
    /**
     * Used only for IN Params
     * @var array [tag]
     */
    protected array $in_params = [];
    /**
     * Used only for OUT Params in stored procedure
     */
    protected array $out_params = []; // array of tags: out params
    /**
     * Used only for OUT and IN_OUT Params in stored procedure
     * @var array [tag]
     */
    protected array $inout_params = [];

    protected array $last_bound_type_tags_by_ref = [];
    protected bool $debug;
    protected bool $params_already_bound = false;
    protected bool $is_transactional = false;
    protected PDOStatement $stmt;
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
    protected function hasInOutParams(): bool
    {
        return ! empty($this->inout_params);
    }

    /**
     * @return bool
     */
    protected function hasOutParams(): bool
    {
        return ! (empty($this->out_params) && empty($this->inout_params));
    }

    /**
     * @return array
     */
    protected function outParams(): array
    {
        return array_merge($this->out_params, $this->inout_params);
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
     * Closure prototype : function(Exception $e, PDOPlusPlus $ppp, string $sql, string $func_name, ...$args) {
     *                         // ...
     *                     }
     * The result of the closure will be available through the method ->error()
     * @param Closure $p
     * @see $this->exceptionInterceptor()
     */
    public static function setExceptionWrapper(Closure $p)
    {
        static::$exception_wrapper = $p;
    }

    /**
     * Return the result of the exception wrapper
     * @return mixed
     */
    public function error(): mixed
    {
        return $this->error_from_wrapper;
    }

    /**
     * @param string|null $cnx_id null => default connection
     * @return PDO
     * @throws BadMethodCallException
     */
    public static function pdo(?string $cnx_id = null): PDO
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
     *     'user'       => string (user name)
     *     'pwd'        => string (password)
     *     'port'       => string (port number)
     *     'timeout'    => string (seconds)
     *     'pdo_params' => others parameters for PDO: array [key => value]
     *     'dsn_params' => other parameter for the dsn string: array [string]
     * ]
     *
     * Careful all keys except 'pdo_params' and 'dsn_params' are required
     * If one is missing then an Exception will be thrown
     *
     * @param string $cnx_id
     * @param array $params
     * @param bool $is_default
     * @throws BadMethodCallException
     */
    public static function addCnxParams(string $cnx_id, array $params, bool $is_default = true)
    {
        if (isset(
            $params['scheme'],
            $params['host'],
            $params['database'],
            $params['user'],
            $params['pwd'],
            $params['port'],
            $params['timeout'])
        ) {
            static::$cnx_params[$cnx_id] = $params;
            if ($is_default) {
                static::$default_cnx_id = $cnx_id;
            }
        } else {
            throw new BadMethodCallException('Invalid connection parameters');
        }
    }

    public static function setDefaultConnection(string $cnx_id)
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
     * @param string $scheme     Ex: mysql pgsql...
     * @param string $host       server host
     * @param string $database   database name
     * @param string $user       user name
     * @param string $pwd        password
     * @param string $port       port number
     * @param string $timeout
     * @param array $pdo_params others parameters for PDO [key => value]
     * @param array $dsn_params other parameter for the dsn string [string]
     * @throws Exception
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
    ) {
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

        try {
            return new PDO($dsn, $user, $pwd, $params);
        } catch (Exception $e) {
            throw $e;
        }
    }
    //endregion

    /**
     * 2 parameters allowed :
     *    - the first : the user value
     *    - the second : a string for the type among: 'int', 'str', 'float', 'double', 'num', 'numeric', 'bool'
     *
     * By default the value is directly escaped in the SQL string and all fields are strings
     *
     * @param array $args
     * @return mixed
     */
    public function __invoke(...$args)
    {
        return $this->injectorInSqlOrByVal(self::VAR_IN_SQL)(...$args);
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
            // as PDO only rollback all the transactions
            $save_point = self::tag('');
            $this->savePoint($save_point);
        } else {
            $this->execTransaction(
                sql: 'START TRANSACTION;',
                func_name: 'startTransaction',
                final_transaction_status:  true,
            );
        }
    }

    /**
     * The commit apply always to the whole transaction at once
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
            self::pdo($this->current_cnx_id)->exec($sql);
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
    public function injectorInSql(?string $data_type = null): object
    {
        return $this->injectorInSqlOrByVal(self::VAR_IN_SQL, $data_type);
    }

    /**
     * Injector for values using the $pdo->bindValue() mechanism
     *
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function injectorInByVal(?string $data_type = null): object
    {
        return $this->injectorInSqlOrByVal(self::VAR_IN_BY_VAL, $data_type);
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
    protected function injectorInSqlOrByVal(string $mode, ?string $data_type = null): object
    {
        return new class($this->data, $this->in_params, $mode, $data_type) {
            private array $data;
            private array $in_params;
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
             * @param array $in_params
             * @param string $mode
             * @param string|null $data_type
             */
            public function __construct(array &$data, array &$in_params, string $mode, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->in_params =& $in_params;
                $this->mode = $mode;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $type
             * @return string
             * @throws TypeError
             */
            public function __invoke($value, string $type = 'str'): string
            {
                $is_scalar = function(mixed $p): bool {
                    return ($p === null) || is_scalar($p) || (is_object($p) && method_exists($p, '__toString'));
                };

                if ( ! $is_scalar($value)) {
                    throw new TypeError('Null or scalar value expected or class with __toString() implemented');
                }

                $tag = PDOPlusPlus::tag();
                $this->data[$tag] = [
                    'mode' => $this->mode,
                    'value' => $value,
                    'type' => $this->locked_type ?? $type,
                ];
                $this->in_params[$tag] = $tag;

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
    public function injectorInByRef(?string $data_type = null): object
    {
        return new class($this->data, $this->in_params, $data_type) {
            private array $data;
            private array $in_params;
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
             * @param array $in_params
             * @param string|null $data_type
             */
            public function __construct(array &$data, array &$in_params, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->in_params =& $in_params;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $type among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(&$value, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::tag();
                $this->data[$tag] = [
                    'mode' => 'in_by_ref',
                    'value' => &$value,
                    'type' => $this->locked_type ?? $type,
                ];
                $this->in_params[$tag] = $tag;

                return $tag;
            }
        };
    }

    /**
     * Injector for params having only OUT attribute
     * @return object
     */
    public function injectorOut(): object
    {
        return new class($this->data, $this->out_params) {
            private array $data;
            private array $out_params;

            /**
             * @param array $data
             * @param array $out_params
             */
            public function __construct(array &$data, array &$out_params)
            {
                $this->data =& $data;
                $this->out_params =& $out_params;
            }

            /**
             * @param string $out_param // ex:'@id'
             * @return string
             */
            public function __invoke(string $out_param): string
            {
                $tag = PDOPlusPlus::tag();
                $this->data[$tag] = ['mode' => 'out', 'value' => $out_param];
                $this->out_params[$tag] = $out_param;

                return $tag;
            }
        };
    }

    /**
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function injectorInOutSql(?string $data_type = null): object
    {
        return $this->injectorInOutSqlOrByVal(self::VAR_INOUT_SQL, $data_type);
    }

    /**
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function injectorInOutByVal(?string $data_type = null): object
    {
        return $this->injectorInOutSqlOrByVal(self::VAR_INOUT_BY_VAL, $data_type);
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
    protected function injectorInOutSqlOrByVal(string $mode, ?string $data_type = null): object
    {
        return new class($this->data, $this->inout_params, $mode, $data_type) {
            private array $data;
            private array $inout_params;
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
             * @param array $inout_params
             * @param string $mode
             * @param string|null $data_type
             */
            public function __construct(array &$data, array &$inout_params, string $mode, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->inout_params =& $inout_params;
                $this->mode = $mode;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $inout_param // ex: '@id'
             * @param string $type        among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(mixed $value, string $inout_param, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::tag();
                $this->data[$tag] = [
                    'mode' => $this->mode,
                    'value' => $value,
                    'type' => $this->locked_type ?? $type,
                ];
                $this->inout_params[$tag] = $inout_param;

                return $tag;
            }
        };
    }

    /**
     * Injector for by ref params having IN OUT attribute
     * @param string|null $data_type Define and lock the type of the value among: int str float double num numeric bool
     * @return object
     */
    public function injectorInOutByRef(?string $data_type = null): object
    {
        return new class($this->data, $this->inout_params, $data_type) {
            private array $data;
            private array $inout_params;
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
             * @param array $inout_params
             * @param string|null $data_type
             */
            public function __construct(array &$data, array &$inout_params, ?string $data_type = null)
            {
                $this->data =& $data;
                $this->inout_params =& $inout_params;
                $this->locked_type = $data_type;
            }

            /**
             * @param mixed $value
             * @param string $inout_param ex: '@id'
             * @param string $type among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(&$value, string $inout_param, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::tag();
                $this->data[$tag] = [
                    'mode' => 'inout_by_ref',
                    'value' => &$value,
                    'type' => $this->locked_type ?? $type,
                ];
                $this->inout_params[$tag] = $inout_param;

                return $tag;
            }
        };
    }
    //endregion

    /**
     * @param string $sql
     * @return string|null lastInsertId() | null on error
     * @throws Exception
     */
    public function insert(string $sql): ?string
    {
        try {
            $result = $this->builtPrepareAndAttachValuesOrParams($sql);
            $pdo = self::pdo($this->current_cnx_id);
            if ($result === true) {
                $this->stmt->execute();
            } else {
                $pdo->exec($result);
            }

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
     * @return array|null
     * @throws Exception
     */
    public function select($sql): ?array
    {
        $this->createStmt($sql, []);
        if ($this->stmt instanceof PDOStatement) {
            return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return null;
        }
    }

    /**
     * @param mixed $sql
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmt($sql): ?PDOStatement
    {
        return $this->createStmt($sql, []);
    }

    /**
     * @param mixed $sql
     * @param array $driver_options
     * @return PDOStatement|null
     * @throws Exception
     */
    public function selectStmtAsScrollableCursor($sql, array $driver_options = []): ?PDOStatement
    {
        $driver_options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL] + $driver_options;

        return $this->createStmt($sql, $driver_options);
    }

    /**
     * @param $sql
     * @param array $prepare_options
     * @return false|PDOStatement|null
     * @throws Exception
     */
    private function createStmt($sql, array $prepare_options): false|PDOStatement|null
    {
        try {
            $result = $this->builtPrepareAndAttachValuesOrParams($sql, $prepare_options);
            if ($result === true) {
                $this->stmt->execute();
            } else {
                $this->stmt = self::pdo($this->current_cnx_id)->query($result);
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
            $result = $this->builtPrepareAndAttachValuesOrParams($sql);
            if ($result === true) {
                $this->stmt->execute();

                return $this->stmt->rowCount();
            } else {
                return self::pdo($this->current_cnx_id)->exec($result);
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
     * @return mixed
     * @throws Exception
     */
    public function call(string $sql, bool $is_query)
    {
        try {
            $pdo = self::pdo($this->current_cnx_id);

            $inject_io_values = function() use ($pdo) {
                if ($this->hasInOutParams()) {
                    $sql = [];
                    foreach ($this->inout_params as $tag => $io) {
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
                if ($this->hasOutParams()) {
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
            if ($this->hasOutParams()) {
                $data['out'] = $this->extractOutParams();
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
        if ($this->hasOutParams()) {
            try {
                $sql = 'SELECT '.implode(', ', $this->outParams());
                $stmt = self::pdo($this->current_cnx_id)->query($sql);

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
     * @param array $modes
     * @return array [tag => data]
     */
    private function tagsByMode(array $modes): array
    {
        $data = [];
        foreach ($this->data as $tag => &$v) {
            if (in_array($v['mode'], $modes, true)) {
                $data[$tag] =& $v;
            }
        }

        return $data;
    }

    /**
     * @param string $sql
     * @param array $prepare_optionss
     * @return true|string  true if the statement has been prepared or string for the plain escaped sql
     * @throws Exception
     */
    private function builtPrepareAndAttachValuesOrParams(string $sql, array $prepare_options = [])
    {
        // replace tags by the out param value
        foreach ($this->tagsByMode([self::VAR_OUT]) as $tag => $v) {
            $sql = str_replace($tag, (string)$v['value'], $sql);
        }

        // replace tags by plain sql values
        foreach ($this->tagsByMode([self::VAR_IN_SQL, self::VAR_INOUT_SQL]) as $tag => $v) {
            $sql = str_replace($tag, (string)self::sqlValue($v['value'], $v['type'], false, $this->current_cnx_id), $sql);
        }

        // stop if there's no need to create a statement
        if (empty($this->tagsByMode([self::VAR_IN_BY_VAL, self::VAR_IN_BY_REF, self::VAR_INOUT_BY_VAL, self::VAR_INOUT_BY_REF]))) {
            return $sql;
        }

        /**
         * @param string $p among: int str float double num numeric bool
         * @return int
         */
        $pdo_type = function(string $p): int {
            return [
                'null' => PDO::PARAM_NULL,
                'int'  => PDO::PARAM_INT,
                'bool' => PDO::PARAM_BOOL,
            ][$p] ?? PDO::PARAM_STR;
        };

        // initial binding
        if ($this->params_already_bound === false) {
            if ( ! ($this->stmt instanceof PDOStatement)) {
                $this->stmt = self::pdo($this->current_cnx_id)->prepare($sql, $prepare_options);
            }

            // params using ->bindValue()
            foreach ($this->tagsByMode([self::VAR_IN_BY_VAL, self::VAR_INOUT_BY_VAL]) as $tag => $v) {
                $pdo_value = self::sqlValue($v['value'], $v['type'], true);
                $this->stmt->bindValue($tag, $pdo_value[0], $pdo_value[1]);
            }
            // params using ->bindParam()
            foreach ($this->tagsByMode([self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF]) as $tag => &$v) {
                $type = $pdo_type($v['type']);
                $this->stmt->bindParam($tag, $v['value'], $type);
                $this->last_bound_type_tags_by_ref[$tag] = $type;
            }
            $this->params_already_bound = true;
        }

        // explicit cast for values by ref and rebinding for null values
        $data_by_ref = $this->tagsByMode([self::VAR_IN_BY_REF, self::VAR_INOUT_BY_REF]);
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
     * Reset the instance and prepare it for the next statement
     */
    public function reset(): void
    {
        $this->data = [];
        $this->params_already_bound = false;
        $this->last_bound_type_tags_by_ref = [];
        $this->stmt = null;
    }

    /**
     * @param Exception $e
     * @param string $sql
     * @param string $func_name
     */
    private function exceptionInterceptor(Exception $e, string $sql, string $func_name)
    {
        if ($this->debug) {
            var_dump($sql);
        }
        if (isset(static::$exception_wrapper)) {
            $hlp = static::$exception_wrapper;
            $this->error_from_wrapper = $hlp($e, $this, $sql, $func_name);
        }
    }

    public function closeCursor()
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
    public static function tag(string $prepend = ':'): string
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
     * @param  array $keys
     * @return array        [key => tag]
     */
    public static function tags(array $keys): array
    {
        $tags = [];
        for ($i = 0, $nb = count($keys) ; $i < $nb ; ++$i) {
            $tags[] = self::tag();
        }

        return array_combine($keys, $tags);
    }

    /**
     * @param $value
     * @param string $type among: int str float double num numeric bool
     * @param bool $for_pdo
     * @param string|null $cnx_id if null => default connection
     * @return mixed|array if $for_pdo => [0 => value, 1 => pdo type] | plain escaped value
     * @throws Exception
     */
    public static function sqlValue($value, string $type, bool $for_pdo, ?string $cnx_id = null)
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
                return self::pdo($cnx_id)->quote($v, PDO::PARAM_STR);
            }
        }
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PPP', false);            // PPP is an official alias for PDOPlusPlus