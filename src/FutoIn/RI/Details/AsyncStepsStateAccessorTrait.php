<?php
/**
 * Re-usable state accessing code
 * @internal
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Details;

/**
 * PHP-specific state() accessor trait
 * \warning: DO NOT use directly
 */
trait AsyncStepsStateAccessorTrait
{
    /**
     * state() access through AsyncSteps interface / set value
     * @param string $name state variable name
     * @param mixed $value state variable value
     */
    public function __set( $name, $value )
    {
        $this->state()->$name = $value;
    }

    /**
     * state() access through AsyncSteps interface / get value
     * @param string $name state variable name
     * @return mixed $value state variable value
     */
    public function &__get( $name )
    {
        return $this->state()->$name;
    }

    /**
     * state() access through AsyncSteps interface / check value exists
     * @param string $name state variable name
     * @return bool
     */
    public function __isset( $name )
    {
        return isset( $this->state()->$name );
    }
    
    /**
     * state() access through AsyncSteps interface / delete value
     * @param string $name state variable name
     */
    public function __unset($name)
    {
        unset( $this->state()->$name );
    }
}
