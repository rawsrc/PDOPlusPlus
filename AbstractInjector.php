<?php

declare(strict_types=1);

namespace rawsrc\PDOPlusPlus;

abstract class AbstractInjector
{
    /**
     * @param array $data
     * @param string|null $final_injector_type
     */
    public function __construct(
        protected array &$data,
        protected string|null $final_injector_type = null,
    ) { }

    /**
     * @param string $type among: int str float double num numeric bool binary
     */
    public function setFinalInjectorType(string $type)
    {
        if ($this->final_injector_type === null) {
            $this->final_injector_type = $type;
        } else {
            throw new Exception('Cannot redefine the type of an injector');
        }
    }
}