<?php

namespace Lambo\CDSFile;

class Header
{
    const MAGIC_COOKIE_VAL = '9619e0bd';

    private $magicCookie = '';
    
    private $majorVer = 0;

    private $minorVer = 0;

    private $fieldsCount = 0;

    /**
     * @var Field[]
     */
    private $fields = [];

    private $recordsCount = 0;

    private $propsIncl = '';

    private $headerSize = 0;

    private $dataSetPropsCount = [];

    /**
     * @var Prop[]
     */
    private $dataSetProps = [];

    private $changeLog = [];

    public function getMagicCookie()
    {
        return $this->magicCookie;
    }

    public function setMagicCookie($value)
    {
        if ($value !== self::MAGIC_COOKIE_VAL) {
            throw new MagicCookieException();
        }

        $this->magicCookie = $value;

        return $this;
    }

    public function getMajorVer()
    {
        return $this->majorVer;
    }

    public function setMajorVer($value)
    {
        $this->majorVer = $value;

        return $this;
    }

    public function getMinorVer()
    {
        return $this->minorVer;
    }

    public function setMinorVer($value)
    {
        $this->minorVer = $value;

        return $this;
    }

    public function getFieldsCount()
    {
        return $this->fieldsCount;
    }

    public function setFieldsCount($value)
    {
        $this->fieldsCount = $value;

        return $this;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function setFields(array $value)
    {
        $this->fields = $value;

        return $this;
    }

    public function getRecordsCount()
    {
        return $this->recordsCount;
    }

    public function setRecordsCount($value)
    {
        $this->recordsCount = $value;

        return $this;
    }

    public function getPropsIncl()
    {
        return $this->propsIncl;
    }

    public function setPropsIncl($value)
    {
        $this->propsIncl = $value;

        return $this;
    }

    public function getHeaderSize()
    {
        return $this->headerSize;
    }

    public function setHeaderSize($value)
    {
        $this->headerSize = $value;

        return $this;
    }

    public function getDataSetPropsCount()
    {
        return $this->dataSetPropsCount;
    }

    public function setDataSetPropsCount($value)
    {
        $this->dataSetPropsCount = $value;

        return $this;
    }

    public function getDataSetProps()
    {
        return $this->dataSetProps;
    }

    public function setDataSetProps(array $value)
    {
        $this->dataSetProps = $value;

        return $this;
    }

    public function getChangeLog()
    {
        return $this->changeLog;
    }

    public function setChangeLog(array $value)
    {
        $this->changeLog = $value;

        return $this;
    }

    public function toArray()
    {
        $result = [];

        $reflect = new \ReflectionClass($this);
        $props = $reflect->getProperties(\ReflectionProperty::IS_PRIVATE);

        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $result[$prop->getName()] = $prop->getValue($this);
        }

        return $result;
    }
}
