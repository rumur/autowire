<?php

namespace Rumur\Autowiring\Exceptions;

class NotInstantiable extends \InvalidArgumentException
{
    public static function primitive(?string $name): NotInstantiable
    {
        return new static(sprintf("Unresolvable primitive parameter [%s]", $name));
    }

    public static function class(?string $name): NotInstantiable
    {
        return new static(sprintf("Unresolvable class [%s]", $name));
    }

    public static function default(?string $name): NotInstantiable
    {
        return new static(sprintf("[%s] could not be instantiated.", $name));
    }
}
