<?php
/**
 * State object details
 * @internal
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Details;

/**
 * Internal class to organize state object.
 *
 * @internal Do not use directly, not standard API
 */
class StateObject
{
    public $error_info = null;
    public $last_exception = null;
    public $_loop_term_label = null;
}
