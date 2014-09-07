<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
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
     */
    public function __set( $name, $value )
    {
        $this->state()->$name = $value;
    }

    /**
     * state() access through AsyncSteps interface / get value
     */
    public function &__get( $name )
    {
        return $this->state()->$name;
    }

    /**
     * state() access through AsyncSteps interface / check value
     */
    public function __isset( $name )
    {
        return isset( $this->state()->$name );
    }
    
    /**
     * state() access through AsyncSteps interface / delete value
     */
    public function __unset($name)
    {
        unset( $this->state()->$name );
    }
}
