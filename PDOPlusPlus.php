<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

use BadMethodCallException;
use Exception;
use PDO;
use PDOStatement;
use TypeError;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper with a new revolutionary fluid SQL syntax
 *
 * @link        https://www.developpez.net/forums/blogs/32058-rawsrc/b9083/pdoplusplus-nouvelle-facon-dutiliser-pdo/
 * @link        https://github.com/rawsrc/PDOPlusPlus
 * @author      rawsrc - https://www.developpez.net/forums/u32058/rawsrc/
 * @copyright   MIT License
 *
 *              Copyright (c) 2020 rawsrc
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
     * @const string    Used by tag generator
     */
    protected const ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    /**
     * @const string
     */
    public const MODE_PREPARE_VALUES = 'prepare_values';
    /**
     * @const string
     */
    public const MODE_PREPARE_PARAMS = 'prepare_params';
    /**
     * @const string
     */
    public const MODE_SQL_DIRECT = 'sql_direct';
    /**
     * @var PDO
     */
    protected static $pdo = null;
    /**
     * @var array
     */
    protected static $tags = [];
    /**
     * @var array   [tag => value]
     */
    protected $values = [];
    /**
     * @var array   [tag => PDO::PARAM_xxx]
     */
    protected $types = [];
    /**
     * @var array
     */
    protected $in_params = [];
    /**
     * Used only for OUT Params in stored procedure
     * @var array  [param]
     */
    protected $out_params = [];
    /**
     * Used only for OUT and IN_OUT Params in stored procedure
     * @var array
     */
    protected $inout_params = [];
    /**
     * @var string
     */
    protected $mode;
    /**
     * @var bool
     */
    protected $debug;
    /**
     * @var PDOStatement
     */
    protected $stmt;
    /**
     * @var bool
     */
    protected $params_already_bound = false;
    /**
     * @var bool
     */
    protected $is_transactional = false;
    /**
     * @var array used by nested transactions
     */
    protected $save_points = [];

    /**
     * @return bool
     */
    protected function isModePrepareValues(): bool
    {
        return $this->mode === self::MODE_PREPARE_VALUES;
    }

    /**
     * @return bool
     */
    protected function isModePrepareParams(): bool
    {
        return $this->mode === self::MODE_PREPARE_PARAMS;
    }

    /**
     * @return bool
     */
    protected function isModeSQLDirect(): bool
    {
        return $this->mode === self::MODE_SQL_DIRECT;
    }

    /**
     * @return bool
     */
    protected function hasInParams(): bool
    {
        return ! (empty($this->in_params) && empty($this->inout_params));
    }

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
     * @return string
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return PDO
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    /**
     * 3 modes available :
     * - MODE_SQL_DIRECT     : omits the PDO prepare()
     * - MODE_PREPARE_VALUES : use PDO::prepare() with bindValue()
     * - MODE_PREPARE_PARAMS : use PDO::prepare() with bindParams()
     *
     * @param string $mode
     * @param bool   $debug
     */
    public function __construct(string $mode = self::MODE_SQL_DIRECT, bool $debug = false)
    {
        $modes       = [self::MODE_SQL_DIRECT, self::MODE_PREPARE_VALUES, self::MODE_PREPARE_PARAMS];
        $this->mode  = in_array($mode, $modes, true) ? $mode : self::MODE_SQL_DIRECT;
        $this->debug = $debug;
    }

    //region DATABASE CONNECTION
    /**
     * Default parameters for PDO are :
     *      \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
     *      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
     *      \PDO::ATTR_EMULATE_PREPARES   => false
     *
     * @param string $scheme     Ex: mysql pgsql...
     * @param string $host       server host
     * @param string $database   database name
     * @param string $user       user name
     * @param string $pwd        password
     * @param string $port       port number
     * @param string $timeout
     * @param array  $pdo_params others parameters for PDO          [key => value]
     * @param array  $dsn_params other parameter for the dsn string [string]
     * @throws Exception
     */
    protected static function connect(
        string $scheme = DB_SCHEME, string $host = DB_HOST, string $database = DB_NAME, string $user = DB_USER,
        string $pwd = DB_PWD, string $port = DB_PORT, string $timeout = DB_TIMEOUT, array $pdo_params = DB_PDO_PARAMS,
        array $dsn_params = DB_DSN_PARAMS
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pwd, $params);
        } catch (Exception $e) {
            throw $e;
        }
    }
    //endregion

    /**
     * 3 parameters allowed :
     *    - the 1st is always reserved to the value
     *    - the 2 others are free, even in their placement
     *          - one boolean strict for the nullable attribute
     *          - one string for the type among: 'int', 'str', 'float', 'double', 'num', 'numeric', 'bool'
     *
     * By default all fields are strings and nullable
     *
     * @param array $args
     * @return mixed
     */
    public function __invoke(...$args)
    {
        return $this->injectorInByVal()(...$args);
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
            $this->execTransaction('START TRANSACTION;', 'startTransaction', true);
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
            $this->execTransaction('COMMIT;', 'commit', false);
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
                $this->execTransaction('ROLLBACK;', 'rollback', false);
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
            $this->execTransaction("SAVEPOINT {$point_name};", 'savePoint', null);
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
            $this->execTransaction("ROLLBACK TO {$point_name};", 'rollbackTo', null);
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
            $this->execTransaction("RELEASE SAVEPOINT {$point_name};", 'release', null);
            $pos = array_search($point_name, $this->save_points, true);
            $this->save_points = array_slice($this->save_points, 0, $pos);
        }
    }

    /**
     * @throws Exception
     */
    public function releaseAll()
    {
        // releasing the first save point will release the others
        if ( ! empty($this->save_points)) {
            $this->release($this->save_points[0]);
        }
    }

    /**
     * Common code
     *
     * @param string    $sql
     * @param string    $func_name
     * @param bool|null $final_transaction_status
     * @throws Exception
     */
    private function execTransaction(string $sql, string $func_name, ?bool $final_transaction_status)
    {
        try {
            self::pdo()->exec($sql);
            if (is_bool($final_transaction_status)) {
                $this->is_transactional = $final_transaction_status;
            }
            if ($this->is_transactional === false) {
                $this->save_points = [];
            }
        } catch (Exception $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log("PPP::execTransaction::{$func_name} - ".$e->getMessage());
            throw $e;
        }
    }
    //endregion

    //region INJECTORS
    /**
     * Return the corresponding params/value injector to the current mode
     *
     * @param string $type // among: in out inout
     * @return object
     */
    public function injector(string $type = 'in'): object
    {
        if ($type === 'out') {
            return $this->injectorOut();
        }

        if ($type === 'inout') {
            if ($this->isModePrepareParams()) {
                return $this->injectorInOutByRef();
            } else {
                return $this->injectorInOutByVal();
            }
        }

        if ($type === 'in') {
            if ($this->isModePrepareParams()) {
                return $this->injectorInByRef();
            }
        }

        // by default
        return $this->injectorInByVal();
    }

    /**
     * Default injector
     * @return object
     */
    protected function injectorInByVal(): object
    {
        return new class($this) extends PDOPlusPlus {
            /**
             * @var PDOPlusPlus
             */
            private $ppp;

            /**
             * @param PDOPlusPlus $p
             */
            public function __construct(PDOPlusPlus $p)
            {
                $this->ppp = $p;
            }

            public function __invoke(...$args)
            {
                if (empty($args)) {
                    throw new BadMethodCallException('Missing value');
                }

                $value = $args[0];
                $type  = $args[1] ?? 'str';

                /**
                 * @param $p
                 * @return bool
                 */
                $is_scalar = function($p): bool {
                    return ($p === null) || is_scalar($p) || (is_object($p) && method_exists($p, '__toString'));
                };

                if ( ! $is_scalar($value)) {
                    throw new TypeError('Null or scalar value expected or class with __toString() implemented');
                }

                if ($this->ppp->isModePrepareValues()) {
                    $tag = PDOPlusPlus::tag();
                    $this->ppp->values[$tag] = $value;
                    $this->ppp->types[$tag]  = $type;
                    $this->ppp->in_params[]  = $tag;
                    return $tag;
                } elseif ($this->ppp->isModeSQLDirect()) {
                    return PDOPlusPlus::sqlValue($value, $type, false);
                } else {
                    throw new BadMethodCallException(
                        'For prepared statement using reference, you must use a specific injector "by reference"'
                    );
                }
            }
        };
    }

    /**
     * Default injector for referenced params
     * @return object
     */
    protected function injectorInByRef(): object
    {
        return new class($this->values, $this->types, $this->in_params) {
            private $values;
            private $types;
            private $in_params;

            /**
             * @param $values
             * @param $types
             * @param $in_params
             */
            public function __construct(&$values, &$types, &$in_params)
            {
                $this->values    =& $values;
                $this->types     =& $types;
                $this->in_params =& $in_params;
            }

            /**
             * @param         $value
             * @param  string $type     among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(&$value, string $type = 'str'): string
            {
                $tag = PDOPlusPlus::tag();
                $this->values[$tag] =& $value;
                $this->types[$tag]  =  $type;
                $this->in_params[]  =  $tag;
                return $tag;
            }
        };
    }

    /**
     * Injector for params having only OUT attribute
     * @return object
     */
    protected function injectorOut(): object
    {
        return new class($this->out_params) {
            private $out_params;

            /**
             * @param $out_params
             */
            public function __construct(&$out_params)
            {
                $this->out_params =& $out_params;
            }

            /**
             * @param  string $out_param // ex:'@id'
             * @return string
             */
            public function __invoke(string $out_param): string
            {
                $this->out_params[] = $out_param;
                return $out_param;
            }
        };
    }

    /**
     * Injector for by val params having IN OUT attribute
     * @return object
     */
    protected function injectorInOutByVal(): object
    {
        return new class($this->values, $this->types, $this->inout_params) {
            private $values;
            private $types;
            private $inout_params;

            /**
             * @param $values
             * @param $types
             * @param $inout_params
             */
            public function __construct(&$values, &$types, &$inout_params)
            {
                $this->values       =& $values;
                $this->types        =& $types;
                $this->inout_params =& $inout_params;
            }

            /**
             * @param         $value
             * @param string  $inout_param // ex: '@id'
             * @param string  $type        among: int str float double num numeric bool
             * @return string
             */
            public function __invoke($value, string $inout_param, string $type = 'str'): string
            {
                $this->values[$inout_param] = $value;
                $this->types[$inout_param]  = $type;
                $this->inout_params[]       = $inout_param;
                return $inout_param;
            }
        };
    }

    /**
     * Injector for by ref params having IN OUT attribute
     * @return object
     */
    protected function injectorInOutByRef(): object
    {
        return new class($this->values, $this->types, $this->inout_params) {
            private $values;
            private $types;
            private $inout_params;

            /**
             * @param $values
             * @param $types
             * @param $inout_params
             */
            public function __construct(&$values, &$types, &$inout_params)
            {
                $this->values       =& $values;
                $this->types        =& $types;
                $this->inout_params =& $inout_params;
            }

            /**
             * @param        $value
             * @param string $inout_param   // ex: '@id'
             * @param string $type          among: int str float double num numeric bool
             * @return string
             */
            public function __invoke(&$value, string $inout_param, string $type = 'str'): string
            {
                $this->values[$inout_param] =& $value;            // by ref
                $this->types[$inout_param]  =  $type;
                $this->inout_params[]       =  $inout_param;
                return $inout_param;
            }
        };
    }
    //endregion

    /**
     * @param string $sql
     * @return int|null           lastInsertId() | null on error
     * @throws Exception
     */
    public function insert(string $sql)
    {
        try {
            $pdo = self::pdo();
            if ($this->isModeSQLDirect()) {
                $pdo->exec($sql);
            } else {
                $this->prepareAndAttachValuesOrParams($sql);
                $this->stmt->execute();
            }
            return $pdo->lastInsertId();
        } catch (Exception $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::insert - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  mixed $sql
     * @return array
     * @throws Exception
     */
    public function select($sql): array
    {
        try {
            if ($this->isModeSQLDirect()) {
                $this->stmt = self::pdo()->query($sql);
            } else {
                $this->prepareAndAttachValuesOrParams($sql);
                $this->stmt->execute();
            }
            return $this->stmt->fetchAll();
        } catch (Exception $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::select - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  string $sql
     * @return int           nb of affected rows
     * @throws Exception
     */
    public function update(string $sql): int
    {
        return $this->execute($sql);
    }

    /**
     * @param  string $sql
     * @return int           nb of affected rows
     * @throws Exception
     */
    public function delete(string $sql): int
    {
        return $this->execute($sql);
    }

    /**
     * @param string $sql
     * @return int             nb of affected rows
     * @throws Exception
     */
    public function execute(string $sql): int
    {
        try {
            if ($this->isModeSQLDirect()) {
                return self::pdo()->exec($sql);
            } else {
                $this->prepareAndAttachValuesOrParams($sql);
                $this->stmt->execute();
                return $this->stmt->rowCount();
            }
        } catch (Exception $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::execute - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $sql
     * @param bool   $is_query
     * @return mixed
     * @throws Exception
     */
    public function call(string $sql, bool $is_query)
    {
        try {
            $pdo = self::pdo();

            $inject_io_values = function() use ($pdo) {
                if ($this->hasInOutParams()) {
                    $sql = [];
                    foreach ($this->inout_params as $io) {
                        // Injecting one by one io_params's value using SQL syntax : "SET @io_param = value"
                        $sql[] = "SET {$io} = ".self::sqlValue($this->values[$io], $this->types[$io], false);
                    }
                    $sql = implode(';', $sql);
                    $pdo->exec($sql);
                }
            };

            if ( ! $this->isModeSQLDirect()) {
                $this->prepareAndAttachValuesOrParams($sql);
            }

            $inject_io_values();

            if ($this->isModePrepareValues() || $this->isModePrepareParams()) {
                $this->stmt->execute();
            } elseif ($is_query) {
                // SQL Direct and Query
                $this->stmt = $pdo->query($sql);
            } else {
                // SQL Direct
                if ($this->hasOutParams()) {
                    $pdo->exec($sql);
                    return ['out' => $this->extractOutParams()];
                } else {
                    return $pdo->exec($sql);
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
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::call - '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array|null       [out_param => value]
     * @throws Exception
     */
    public function extractOutParams(): array
    {
        if ($this->hasOutParams()) {
            try {
                $sql  = 'SELECT '.implode(', ', $this->outParams());
                $stmt = self::pdo()->query($sql);
                return $stmt->fetchAll()[0];
            } catch (Exception $e) {
                if ($this->debug) {
                    var_dump($sql);
                }
                error_log('PPP::extractOutParams - '.$e->getMessage());
                throw $e;
            }
        } else {
            return [];
        }
    }

    /**
     * @param string $sql
     */
    private function prepareAndAttachValuesOrParams(string $sql)
    {
        /**
         * @param  string $p    among: int str float double num numeric bool
         * @return int
         */
        $pdo_type = function(string $p): int {
            return [
                'null' => PDO::PARAM_NULL,
                'int'  => PDO::PARAM_INT,
                'bool' => PDO::PARAM_BOOL,
            ][$p] ?? PDO::PARAM_STR;
        };

        if ( ! ($this->stmt instanceof PDOStatement)) {
            $this->stmt = self::pdo()->prepare($sql);
        }

        if ($this->isModePrepareValues()) {
            if ($this->hasInParams()) {
                foreach ($this->values as $token => $v) {
                    $this->stmt->bindValue($token, $v, $pdo_type($this->types[$token]));
                }
            }
        } elseif ($this->isModePrepareParams() && $this->hasInParams() && ( ! $this->params_already_bound)) {
            foreach ($this->values as $token => &$v) {
                $this->stmt->bindParam($token, $v, $pdo_type($this->types[$token]));
            }
            $this->params_already_bound = true;
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
            $tag = $prepend.substr(str_shuffle(self::ALPHA), 0, 6).mt_rand(1000, 9999);
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
     * @param        $value
     * @param string $type      among: int str float double num numeric bool
     * @param bool   $for_pdo
     * @return mixed|array      if $for_pdo => [0 => value, 1 => pdo type] | plain escaped value
     */
    public static function sqlValue($value, string $type, bool $for_pdo)
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
                return self::pdo()->quote($v, PDO::PARAM_STR);
            }
        }
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus\PDOPlusPlus', 'PPP', false);            // PPP is an official alias for PDOPlusPlus