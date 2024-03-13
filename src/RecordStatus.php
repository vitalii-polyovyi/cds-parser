<?php

namespace Lambo\CDSFile;

class RecordStatus
{
    private $name = '';

    private $code = '';

    private $subCode = -1;
    
    public function __construct($name, $code, $subCode = -1)
    {
        $this->name = $name;
        $this->code = $code;
        $this->subCode = $subCode;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getSubCode()
    {
        return $this->subCode;
    }
}
