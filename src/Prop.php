<?php

namespace Lambo\CDSFile;

class Prop
{
    private $name = '';
    
    private $size = 0;

    /**
     *
     * @var Type
     */
    private $type;

    private $data = null;

    /**
     *
     * @param string $name
     * @param int $size
     * @param Type $type
     * @param mixed $data
     */
    public function __construct($name, $size, Type $type, $data)
    {
        $this->name = $name;
        $this->size = $size;        
        $this->type = $type;
        $this->data = $data;
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function getSize()
    {
        return $this->size;
    }

    /**
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    public function getData()
    {
        return $this->data;
    }
}
