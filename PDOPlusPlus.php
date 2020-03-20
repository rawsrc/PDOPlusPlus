<?php

declare(strict_types=1);

namespace rawsrc;

/**
 * PDOPlusPlus : A PHP Full Object PDO Wrapper
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
     * 3 modes disponibles :
     * - MODE_SQL_DIRECT     : ignore  le mécanisme de préparation de PDO
     * - MODE_PREPARE_VALUES : utilise le mécanisme de préparation en mode bindValue()
     * - MODE_PREPARE_PARAMS : utilise le mécanisme de préparation en mode bindParams()
     *
     * @param string $mode      Une des constantes de classe MODE_XXX
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
     * @return bool
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
     * 3 paramètres au maximum autorisés :
     *    - le 1er paramètre doit TOUJOURS correspondre à la valeur du champ
     *    - les 2 autres sont libres dans leur présence et/ou placement :
     *          - un paramètre de type 'boolean' strict est réservé à la propriété nullable du champ
     *          - un paramètre de type 'string'  strict est réservé au type du champ
     *
     * Les types de champs possibles sont :
     *     'int', 'str', 'float', 'double', 'num', 'numeric', 'bool'
     *
     * Par défaut, le champ est nullable et de type texte
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

        $value    = $args[0];   // le premier paramètre doit TOUJOURS être la valeur
        $nullable = true;       // par défaut
        $type     = 'str';      // par défaut

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
                // génération du tag, stockage de la valeur, du type et renvoi du tag
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
     * @return string               tag généré
     */
    private function modePrepareValuesEscaping($value, string $type, bool $nullable): string
    {
        // échappement selon le mécanisme de préparation
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
        // génération du tag, stockage de la valeur, du type et renvoi du tag
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
            // ici on demande directement au serveur (via la connexion ouverte) d'échapper la valeur texte selon ses propres règles
            return self::pdo()->quote((string)$value, \PDO::PARAM_STR);
        }
    }


    /**
     * Generateur de tag
     * Chaque tag est unique pour toute la session
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
     * Générateur de tags uniques
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
     * @return array|null       null si erreur
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
            // le format de retour est défini dans le paramétrage de la connexion, voir : PDO::ATTR_DEFAULT_FETCH_MODE
            return $this->stmt->fetchAll();
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
            $pdo = self::pdo();
            if ($this->isModeSQLDirect()) {
                $pdo->exec($sql);
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
                $this->stmt->execute();
            }
            return $pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ($this->debug) {
                var_dump($sql);
            }
            error_log('PDO::select - '.$e->getMessage());
            return null;
        }
    }
}

// mise à disposition de la classe sur l'espace de nom global :
class_alias('rawsrc\PDOPlusPlus', 'PDOPlusPlus', false);
class_alias('rawsrc\PDOPlusPlus', 'PPP', false);