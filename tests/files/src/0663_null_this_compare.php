<?php

class Foo663
{
    /**
     * An array of static hostnames.
     *
     * @var ?array<int,string>
     */
    protected $_staticHostnames = null;

    public function staticHostname() : string
    {
        if ($this->_staticHostnames !== null) {
            return $this->_staticHostnames[0];
        }
        echo count($this->_staticHostnames);
        return '';
    }
}
