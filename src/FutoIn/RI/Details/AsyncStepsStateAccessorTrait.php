<?php

namespace FutoIn\RI\Details;

/**
 * \brief PHP-specific state() accessor trait
 * \warning: DO NOT use directly
 */
trait AsyncStepsStateAccessorTrait
{
    /**
     * \brief state() access through AsyncSteps interface / set value
     */
    public function __set( $name, $value )
    {
        $this->state()->$name = $value;
    }

    /**
     * \brief state() access through AsyncSteps interface / get value
     */
    public function &__get( $name )
    {
        return $this->state()->$name;
    }

    /**
     * \brief state() access through AsyncSteps interface / check value
     */
    public function __isset( $name )
    {
        return isset( $this->state()->$name );
    }
    
    /**
     * \brief state() access through AsyncSteps interface / delete value
     */
    public function __unset($name)
    {
        unset( $this->state()->$name );
    }
}
