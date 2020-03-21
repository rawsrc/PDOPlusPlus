<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper with a new revolutionary fluid SQL syntax
 *
 * @link        https://www.developpez.net/forums/blogs/32058-rawsrc/b9083/pdoplusplus-nouvelle-facon-dutiliser-pdo/
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
     * @const string
     */
    private const ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
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
     * @var \PDO
     */
    private static $pdo = null;
    /**
     * @var array
     */
    private static $tags = [];
    /**
     * @var array
     */
    private $values = [];
    /**
     * @var array
     */
    private $types = [];
    /**
     * @var string
     */
    private $mode;
    /**
     * @var bool
     */
    private $debug;
    /**
     * @var \PDOStatement
     */
    private $stmt;

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
        $this->mode = in_array($mode, [self::MODE_SQL_DIRECT, self::MODE_PREPARE_VALUES, self::MODE_PREPARE_PARAMS], true)
                          ? $mode
                          : self::MODE_SQL_DIRECT;
        $this->debug = $debug;
    }

    /**
     * @return string
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @return \PDO
     */
    public function pdo(): \PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

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
     */
    private static function connect(string $scheme = DB_SCHEME, string $host = DB_HOST, string $database = DB_NAME,
                                    string $user = DB_USER, string $pwd = DB_PWD, string $port = DB_PORT,
                                    string $timeout = DB_TIMEOUT, array $pdo_params = [], array $dsn_params = [])
    {
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
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            self::$pdo = new \PDO($dsn, $user, $pwd, $params);
        } catch (\PDOException $e) {
            throw $e;
        }
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
     * 3 parameters allowed :
     *    - the 1st is always reserved to the value
     *    - the 2 others are free, even in their placement
     *          - one boolean strict for the nullable attribute
     *          - one string for the type among: 'int', 'str', 'float', 'double', 'num', 'numeric', 'bool'
     *
     * By default all fields are strings and nullable
     *
     * @param  mixed  $value
     * @param  string $type
     * @param  bool   $nullable
     * @return mixed
     */
    public function __invoke(...$args)
    {
        if (empty($args)) {
            throw new \BadFunctionCallException('Missing value');
        }

        $value    = $args[0];
        $nullable = true;
        $type     = 'str';

        if (isset($args[1])) {
            if (is_bool($args[1])) {
                $nullable = $args[1];
                $type     = $args[2] ?? 'str';
            } else {
                $type     = $args[1];
                $nullable = $args[2] ?? true;
            }
        }

        /**
         * @param $p
         * @return bool
         */
        $is_scalar = function($p): bool {
            return ($p === null) || is_scalar($p) || (is_object($p) && method_exists($p, '__toString'));
        };

        if ( ! $is_scalar($value)) {
            throw new \BadFunctionCallException('Null or scalar value expected or class with __toString() implemented');
        }

        if ( ! in_array($type, ['int', 'str', 'float', 'double', 'num', 'numeric', 'bool'], true)) {
            $type = 'str';
        }

        if ($this->isModePrepareValues()) {
            return $this->modePrepareValuesEscaping($value, $type, $nullable);
        } elseif ($this->isModeSQLDirect()) {
            return $this->modeSQLDirectEscaping($value, $type, $nullable);
        } else {
            throw new \BadMethodCallException(
                'For prepared statement using bindParam(), you must use the specific injector returned by the function modePrepareParamsInjector()'
            );
        }
    }

    /**
     * @return object
     */
    public function modePrepareParamsInjector(): object
    {
        return new class(self::$tags, $this->values, $this->types) {
            private static $tags;
            private $values;
            private $types;

            /**
             * @param $tags
             * @param $values
             * @param $types
             */
            public function __construct(&$tags, &$values, &$types)
            {
                self::$tags   =& $tags;
                $this->values =& $values;
                $this->types  =& $types;
            }

            /**
             * @param         $value
             * @param  string $type
             * @return string
             */
            public function __invoke(&$value, string $type = 'str'): string
            {
                if ($type === 'int') {
                    $type = \PDO::PARAM_INT;
                } elseif ($type === 'bool') {
                    $type = \PDO::PARAM_BOOL;
                } else {
                    $type = \PDO::PARAM_STR;
                }
                // generating the tag, save both the value and type and return the tag
                $tag = PDOPlusPlus::tag();
                $this->values[$tag] =& $value;
                $this->types[$tag]  = $type;
                return $tag;
            }
        };
    }

    /**
     * @param         $value
     * @param  string $type
     * @param  bool   $nullable
     * @return string            generated tag
     */
    private function modePrepareValuesEscaping($value, string $type, bool $nullable): string
    {
        if ($value === null) {
            if ($nullable) {
                $type = \PDO::PARAM_NULL;
            }else {
                throw new \TypeError('The value is not nullable');
            }
        } elseif ($type === 'int') {
            $value = (int)$value;
            $type  = \PDO::PARAM_INT;
        } elseif ($type === 'bool') {
            $value = (bool)$value;
            $type  = \PDO::PARAM_BOOL;
        } elseif (in_array($type, ['float', 'double', 'num', 'numeric'], true)) {
            $value = (string)(double)$value;
            $type  = \PDO::PARAM_STR;
        } else {
            $value = (string)$value;
            $type  = \PDO::PARAM_STR;
        }
        // generating the tag, save both the value and type and return the tag
        $tag = $this->tag();
        $this->values[$tag] = $value;
        $this->types[$tag]  = $type;
        return $tag;
    }

    /**
     * @param         $value
     * @param  string $type
     * @param  bool   $nullable
     * @return mixed
     */
    private function modeSQLDirectEscaping($value, string $type, bool $nullable)
    {
        // échappement direct des valeurs : conversion de type FORCÉE et échappement texte
        // escaping the values manually, forced casting and string escaping
        if ($value === null) {
            if ($nullable) {
                return 'NULL';
            } else {
                throw new \TypeError('The value is not nullable');
            }
        } elseif ($type === 'int') {
            return (int)$value;
        } elseif ($type === 'bool') {
            return (int)(bool)$value;
        } elseif (in_array($type, ['float', 'double', 'num', 'numeric'], true)) {
            return (string)(double)$value;
        } else {
            return self::pdo()->quote((string)$value, \PDO::PARAM_STR);
        }
    }


    /**
     * Unique tag generator
     * The tag is always unique for the whole current session
     * @return string
     */
    public static function tag(): string
    {
        do {
            $tag = ':'.substr(str_shuffle(self::ALPHA), 0, 6).mt_rand(1000, 9999);
        } while (isset(self::$tags[$tag]));
        self::$tags[$tag] = true;
        return $tag;
    }

    /**
     * Unique tags generator
     * The tags are always unique for the whole current session
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
     * @param  mixed $sql
     * @return array|null       null on error
     */
    public function select($sql): ?array
    {
        try {
            $pdo = self::pdo();

            if ($this->isModeSQLDirect()) {
                $this->stmt = $pdo->query($sql);
            } elseif ($this->isModePrepareValues()) {
                $this->stmt = $pdo->prepare($sql);
                foreach ($this->values as $token => $v) {
                    $this->stmt->bindValue($token, $v, $this->types[$token]);
                }
            } elseif ($this->isModePrepareParams()) {
                if ( ! isset($this->stmt)) {
                    $this->stmt = $pdo->prepare($sql);
                    foreach ($this->values as $token => &$v) {
                        $this->stmt->bindParam($token, $v, $this->types[$token]);
                    }
                }
            }
            $this->stmt->execute();
            // the return format is defined when opening the connection : see PDO::ATTR_DEFAULT_FETCH_MODE
            return $this->stmt->fetchAll();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::select - '.$e->getMessage());
            return null;
        }
    }

    /**
     * @param  string $sql
     * @return int|null         null on error | int -> nb of affected rows
     */
    public function update(string $sql)
    {
        return $this->deleteOrUpdate($sql);
    }

    /**
     * @param  string $sql
     * @return int|null         null on error | int -> nb of affected rows
     */
    public function delete(string $sql)
    {
        return $this->deleteOrUpdate($sql);
    }

    /**
     * @param  string $sql
     * @return bool
     */
    public function execute(string $sql): bool
    {
        return (bool)$this->deleteOrUpdate($sql);
    }

    /**
     * @param  string $sql
     * @return int|null         null on error | int -> nb of affected rows
     */
    private function deleteOrUpdate(string $sql)
    {
        try {
            $pdo = self::pdo();

            if ($this->isModeSQLDirect()) {
                return $pdo->exec($sql);
            }

            if ($this->isModePrepareValues()) {
                $this->stmt = $pdo->prepare($sql);
                foreach ($this->values as $token => $v) {
                    $this->stmt->bindValue($token, $v, $this->types[$token]);
                }
            } elseif ($this->isModePrepareParams()) {
                if ( ! isset($this->stmt)) {
                    $this->stmt = $pdo->prepare($sql);
                    foreach ($this->values as $token => &$v) {
                        $this->stmt->bindParam($token, $v, $this->types[$token]);
                    }
                }
            }
            $this->stmt->execute();
            return $this->stmt->rowCount();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::deleteOrUpdate - '.$e->getMessage());
            return null;
        }
    }

    /**
     * @param  string $sql
     * @return int|null           lastInsertId() | null on error
     */
    public function insert(string $sql)
    {
        try {
            $pdo = self::pdo();
            if ($this->isModeSQLDirect()) {
                $pdo->exec($sql);
            } elseif ($this->isModePrepareValues()) {
                $this->stmt = $pdo->prepare($sql);
                foreach ($this->values as $token => $v) {
                    $this->stmt->bindValue($token, $v, $this->types[$token]);
                }
                $this->stmt->execute();
            } elseif ($this->isModePrepareParams()) {
                if ( ! isset($this->stmt)) {
                    $this->stmt = $pdo->prepare($sql);
                    foreach ($this->values as $token => &$v) {
                        $this->stmt->bindParam($token, $v, $this->types[$token]);
                    }
                }
                $this->stmt->execute();
            }
            return $pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PPP::insert - '.$e->getMessage());
            return null;
        }
    }
}

// make the class available on the global namespace :
class_alias('rawsrc\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus', 'PPP', false);            // PPP is an official alias of PDOPlusPlus