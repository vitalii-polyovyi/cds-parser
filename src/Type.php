<?php

namespace Lambo\CDSFile;

class Type
{
    private $name = '';

    private $attr = '';
    
    private $isArray = false;

    public function __construct($name, $attr, $isArray = false)
    {
        $this->name = $name;
        $this->attr = $attr;
        $this->isArray = $isArray;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAttr()
    {
        return $this->attr;
    }

    public function isArray()
    {
        return $this->isArray;
    }
}
