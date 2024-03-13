<?php

namespace Lambo\CDSFile;

class Field
{
    private $name = '';

    private $size = 0;

    /**
     *
     * @var Type
     */
    private $type;

    private $attrs;

    /**
     * 
     * @var Prop[]
     */
    private $props = [];

    /**
     *
     * @param string $name
     * @param int $size
     * @param Type $type
     * @param mixed $attrs
     * @param Prop[] $props
     */
    public function __construct($name, $size, Type $type, $attrs, array $props)
    {
        $this->name = $name;
        $this->size = $size;
        $this->type = $type;
        $this->attrs = $attrs;
        $this->props = $props;
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

    public function getAttrs()
    {
        return $this->attrs;
    }

    /**
     *
     * @return Prop[]
     */
    public function getProps()
    {
        return $this->type;
    }
}
