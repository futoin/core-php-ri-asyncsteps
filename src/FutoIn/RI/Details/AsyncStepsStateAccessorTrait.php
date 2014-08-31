<?php

namespace FutoIn\RI\Details;

/// TODO: Too dirty... To be redesigned.

trait AsyncStepsStateAccessorTrait
{
    public function __set( $name, $value )
    {
        $this->state()->$name = $value;
    }

    public function &__get( $name )
    {
        return $this->state()->$name;
    }

    public function __isset( $name )
    {
        return isset( $this->state()->$name );
    }

    public function __unset($name)
    {
        unset( $this->state()->$name );
    }
}
