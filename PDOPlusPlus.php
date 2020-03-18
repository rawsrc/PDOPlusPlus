<?php

declare(strict_types=1);

namespace rawsrc;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper
 *
 * @link        https://www.developpez.net/forums/blogs/32058-rawsrc/b8215/phpecho-moteur-rendu-php-classe-gouverner/
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
     * @var bool
     */
    private $prepare;
    /**
     * @var bool
     */
    private $debug;

    /**
     * PDOPlus constructor.
     * @param bool $prepare
     * @param bool $debug
     */
    public function __construct(bool $prepare = false, bool $debug = false)
    {
        $this->prepare = $prepare;
        $this->debug   = $debug;
    }

    /**
     * @return bool
     */
    public function prepare(): bool
    {
        return $this->prepare;
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
     * Les paramètres par défaut de PDO sont :
     *      \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
     *      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
     *      \PDO::ATTR_EMULATE_PREPARES   => false
     *
     * @param string $scheme     Ex: mysql pgsql...
     * @param string $host       Adresse IP du serveur
     * @param string $database   Nom de la base de données
     * @param string $user       Nom de l'utilisateur
     * @param string $pwd        Mot de passe de la connexion
     * @param string $port       Numéro du port pour la connexion
     * @param string $timeout    Délai d'attente réponse serveur
     * @param array  $pdo_params Paramètres additionnels à passer à la création de PDO     [key => value]
     * @param array  $dsn_params Paramètres additionnels à passer à la chaîne de connexion [string]
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
     * @param  mixed  $value
     * @param  string $type
     * @param  bool   $nullable
     * @return mixed
     */
    public function __invoke($value, string $type = 'str', bool $nullable = false)
    {
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string)$value;
            }
        }

        if ( ! is_scalar($value)) {
            throw new \BadFunctionCallException('Scalar value expected or class with __toString() implemented');
        }

        if ( ! in_array($type, ['int', 'str', 'float', 'double', 'num', 'numeric', 'bool', 'null'], true)) {
            $type = 'str';
        }

        if ($type === 'int') {
            $value = (int)$value;
            $type  = \PDO::PARAM_INT;
        } elseif (in_array($type, ['float', 'double', 'num', 'numeric'], true)) {
            $value = (string)(double)$value;
            $type  = \PDO::PARAM_STR;
        } elseif ($type === 'bool') {
            $value = (bool)$value;
            $type  = \PDO::PARAM_BOOL;
        } else {
            $value = (string)$value;
            $type  = \PDO::PARAM_STR;
        }

        if ($value === null) {
            if ($nullable) {
                if ($this->prepare) {
                    $type = \PDO::PARAM_NULL;
                } else {
                    $value = 'NULL';
                }
            } else {
                throw new \TypeError('The value is not nullable');
            }
        }

        if ($this->prepare) {
            $tag = $this->tag();
            $this->values[$tag] = $value;
            $this->types[$tag]  = $type;
            return $tag;
        } else {
            if ($type === 'str') {
                return self::pdo()->quote($value, \PDO::PARAM_STR);
            } else {
                return $value;
            }
        }
    }

    /**
     * Generator de tags
     * Chaque tag est unique pour toute la session
     * @return string
     */
    public function tag(): string
    {
        do {
            $tag = ':'.substr(str_shuffle(self::ALPHA), 6).mt_rand(1000, 9999);
        } while (isset(self::$tags[$tag]));
        self::$tags[$tag] = true;
        return $tag;
    }

    /**
     * @param  mixed $sql
     * @return array|null       null si erreur
     */
    public function select($sql): ?array
    {
        try {
            if ($this->prepare) {
                $stmt = self::pdo()->prepare($sql);
                foreach ($this->values as $tag => $v) {
                    $stmt->bindValue($tag, $v, $this->types[$tag]);
                }
                $stmt->execute();
            } else {
                $stmt = self::pdo()->query($sql);
            }
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PDO::select - '.$e->getMessage());
            return null;
        }
    }

    /**
     * @param  string $sql
     * @return int|null         si int => nombre de lignes affectées
     */
    public function update(string $sql)
    {
        return $this->deleteOrUpdate($sql);
    }

    /**
     * @param  string $sql
     * @return int|null         si int => nombre de lignes affectées
     */
    public function delete(string $sql)
    {
        return $this->deleteOrUpdate($sql);
    }

    /**
     * @param  string $sql
     * @return int|null         si int => nombre de lignes affectées
     */
    private function deleteOrUpdate(string $sql)
    {
        try {
            if ($this->prepare) {
                $stmt = self::pdo()->prepare($sql);
                foreach ($this->values as $tag => $v) {
                    $stmt->bindValue($tag, $v, $this->types[$tag]);
                }
                $stmt->execute();
                return $stmt->rowCount();
            } else {
                return self::pdo()->exec($sql);
            }
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PDO::deleteOrUpdate - '.$e->getMessage());
            return null;
        }
    }

    /**
     * @param  string $sql
     * @return int|null           lastInsertId() | null si erreur
     */
    public function insert(string $sql)
    {
        try {
            if ($this->prepare) {
                $stmt = self::pdo()->prepare($sql);
                foreach ($this->values as $token => $v) {
                    $stmt->bindValue($token, $v, $this->types[$token]);
                }
                $stmt->execute();
            } else {
                self::pdo()->exec($sql);
            }
            return self::pdo()->lastInsertId();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PDO::select - '.$e->getMessage());
            return null;
        }
    }

    /**
     * @param  string $sql
     * @param  bool   $is_query
     * @return mixed              si $is_query => [] | true
     */
    public function storedProc(string $sql, bool $is_query = true)
    {
        try {
            if ($this->prepare) {
                $stmt = self::pdo()->prepare($sql);
                foreach ($this->values as $token => $v) {
                    $stmt->bindValue($token, $v, $this->types[$token]);
                }
                $stmt->execute();
            } else {
                $stmt = self::pdo()->query($sql);
            }
            return $is_query ? $stmt->fetchAll() : true;
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PDO::select - '.$e->getMessage());
            return null;
        }
    }
}
