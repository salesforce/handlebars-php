<?php

use Handlebars\Loader;

/**
 * Creates a loader backed by an associative array that maps the key of the map to the name used during the load. If
 * the name does not exist in the map, an IllegalArgumentException is thrown.
 */
class MapLoader implements Loader
{
    private $values;

    public function __construct($values)
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('Unexpected value for values argument. Expected an array.');
        }
        $this->values = $values;
    }

    public function load($name)
    {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        } else {
            throw new InvalidArgumentException('Template ' . $name . ' not found.');
        }
    }
}