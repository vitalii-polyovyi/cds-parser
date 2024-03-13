<?php

namespace Lambo\CDSFile;

use Lambo\CDSFile\FileExceptions;

class Reader
{
    const DATE_ZERO = 693594;
    const DATE_BCD_AND_UTIME_DIFF = 25569;

    private $handler = null;
    
    private $offset = 0;

    private $withChangeLog = false;

    private $currentRecordIndex = 0;

    private $dateFormat = 'Y-m-d';

    /**
     * File header
     *
     * @var Header
     */
    private $header;

    public function __construct($filePath, $withChangeLog = false)
    {
        if (!file_exists($filePath)) {
            throw new FileExceptions\NotFoundException();
        }

        if (!is_readable($filePath)) {
            throw new FileExceptions\NotReadableException();
        }

        $this->withChangeLog = $withChangeLog;

        $this->handler = fopen($filePath, 'rb');

        $this->readHeader();
    }

    public function setDateFormat($format)
    {
        $this->dateFormat = $format;
        return $this;
    }

    public function isOpen()
    {
        return is_null($this->handler);
    }

    protected function readHeader()
    {
        $this->header = new Header();

        $this->header->setMagicCookie(unpack('H*', $this->readBytes(4))[1]);
        $this->header->setMajorVer($this->readInt(4));
        $this->header->setMinorVer($this->readInt(4));
        $this->header->setFieldsCount($this->readUInt(2));
        $this->header->setRecordsCount($this->readInt(4));
        $this->header->setPropsIncl($this->readInt(4));

        $headerSize = $this->readUInt(2);
        $this->header->setHeaderSize($headerSize);

        $fields = $this->readFields($this->header->getFieldsCount());
        $this->header->setFields($fields);

        $this->header->setDataSetPropsCount($this->readUInt(2));
        $props = $this->readProps($this->header->getDataSetPropsCount());
        $this->header->setDataSetProps($props);

        $this->processLeftHeader($headerSize - $this->offset, $props);

        if (($headerSize - $this->offset) !== 0) {
            throw new \LogicException('Header reading error: ' . ($headerSize - $this->offset) . ' bytes left unread');
        }

        return $this;
    }

    private function processLeftHeader($bytesLeft, array $props)
    {
        if ($bytesLeft > 0) {
            if ($this->withChangeLog &&
                $changeLogProp = $this->findProp($props, 'CHANGE_LOG')) {
                $changeLog = $this->readChangeLog($changeLogProp);
                $this->header->setChangeLog($changeLog);
            } else {
                $this->readBytes($bytesLeft);
            }
        }
    }

    /**
     *
     * @param Prop[] $props
     * @param string $propName
     * @return Prop|null
     */
    private function findProp(array $props, $propName)
    {
        foreach ($props as $prop) {
            if ($prop->getName() === $propName) {
                return $prop;
            }
        }

        return null;
    }

    private function readChangeLog(Prop $changeLogProp)
    {
        $changeLog = [];

        $changeLogLen = floor($changeLogProp->getData() / 3);
        for ($i = 0; $i < $changeLogLen; ++$i) {
            $entry = [];
            $entry[] = $this->readInt(4);
            $entry[] = $this->readInt(4);
            $entry[] = $this->getRecordStatus($this->readInt(4));

            $changeLog[] = $entry;
        }

        return $changeLog;
    }

    public function recordsCount()
    {
        return $this->header->getRecordsCount();
    }

    public function hasNext()
    {
        $recordsCount = $this->header->getRecordsCount();
        return $this->currentRecordIndex < $recordsCount;
    }

    public function nextRecord()
    {
        if (!$this->hasNext()) {
            return null;
        }

        $this->currentRecordIndex++;

        $status = $this->getRecordStatus($this->readUInt(1));
        $rowData = [];

        $fieldsCount = $this->header->getFieldsCount();
        $s = floor((2 * $fieldsCount + 7) / 8);

        $statusBytes = $this->readBytes($s);
        $fields = $this->header->getFields();

        $bitPos = 0;
        for ($i = 0; $i < $fieldsCount; ++$i) {
            $x = unpack('C', $statusBytes)[1];
            $statusBit1 = ($x >> $bitPos) & 0b1;
            $statusBit2 = ($x >> $bitPos + 1) & 0b1;

            $field = $fields[$i];
            $fieldName = $field->getName();

            if ($statusBit1 || $statusBit2) {
                $rowData[$fieldName] = null;
            }
            else if (!$statusBit1 && !$statusBit2) {
                $rowData[$fieldName] = $this->readValue($field);
            }
            else {
                $rowData[$fieldName] = null;
            }

            $bitPos += 2;
        }

        return new Record($status, $rowData);
    }

    /**
     * @param boolean $visibleOnly
     * @return array
     */
    public function allRecords($visibleOnly = false, $dataOnly = false)
    {
        $records = [];
        while ($this->hasNext()) {
            $record = $this->nextRecord();

            if ($visibleOnly && !$record->isVisible()) {
                continue;
            }

            $records[] = $dataOnly ? $record->getData() : $record;
        }

        return $records;
    }

    /**
     *
     * @return Header
     */
    public function getHeader()
    {
        return $this->header;
    }
    
    private function getTypeName($typeCode)
    {
        switch ($typeCode) {
            case 0: return 'unknown';
            case 1: return 'int';
            case 2: return 'uint';
            case 3: return 'bool';
            case 4: return 'float';
            case 5: return 'bcd';
            case 6: return 'date';
            case 7: return 'time';
            case 8: return 'timestamp';
            case 9: return 'zstring';
            case 10: return 'unicode';
            case 11: return 'bytes';
            case 12: return 'adt';
            case 13: return 'array';
            case 14: return 'nestedtable';
            case 15: return 'reference';
        }
    }
    
    private function getAttr($attrCode)
    {
        switch ($attrCode) {
            case 0: return 'unknown';
            case 2: return 'int';
            case 4: return 'uint';
            case 5: return 'bool';
        }
    }

    /**
     *
     * @param string $unpackedData
     * @return array
     */
    private function parseFieldType($unpackedData)
    {
        return [
            ($unpackedData)      & 0b111111,
            ($unpackedData >> 6) & 0b1,
            ($unpackedData >> 7) & 0b1
        ];
    }

    /**
     *
     * @param array $parsedFieldType
     * @return Type
     */
    private function explainFieldType(array $parsedFieldType)
    {    
        return new Type(
            $this->getTypeName($parsedFieldType[0]),
            $parsedFieldType[1] === 0 ? 'fixed' : 'varying',
            $parsedFieldType[2] === 1
        );
    }

    private function getRecordStatus($statusCodeOrig)
    {
        $code = $statusCodeOrig;
        $subCode = -1;
        if ($statusCodeOrig === 5) {
            $code = 1;
            $subCode = 4;
        }
        else if ($statusCodeOrig === 6) {
            $code = 4;
            $subCode = 2;
        }
        else if ($statusCodeOrig === 9) {
            $code = 1;
            $subCode = 8;
        }
        else if ($statusCodeOrig === 12) {
            $code = 4;
            $subCode = 8;
        }
        else if ($statusCodeOrig === 13) {
            $code = 1;
            $subCode = 4;
        }
        else if ($statusCodeOrig === 14) {
            $code = 2;
            $subCode = 8;
        }

        $detectStatus = function ($code, $subCode) {
            switch ($code) {
                case 0 : return new RecordStatus('unmodified', $code, $subCode);
                case 1 : return new RecordStatus('original', $code, $subCode);
                case 2 : return new RecordStatus('deleted', $code, $subCode);
                case 4 : return new RecordStatus('inserted', $code, $subCode);
                case 8 : return new RecordStatus('modified', $code, $subCode);
                case 32: return new RecordStatus('unused', $code, $subCode);
                case 64: return new RecordStatus('detmodification', $code, $subCode);
                default: {
                    throw new \LogicException('Unknown CDS record status ' . $code);
                }
            }
        };

        return $detectStatus($code, $subCode);
    }

    private function readBytes($length)
    {
        $this->offset += $length;
        return fread($this->handler, $length);
    }

    private function readVarying($bytes)
    {
        $sizeOffsetPacked = $this->readBytes($bytes);
        $sizeOffsetUnpacked = unpack('C', $sizeOffsetPacked);
    
        return $this->readBytes($sizeOffsetUnpacked[1]);
    }
    
    private function readZString($bytes)
    {
        return $this->readVarying($bytes);
    }
    
    private function readUnicodeString($bytes)
    {
        return $this->readVarying($bytes);
    }
    
    private function readVariyngBytes($bytes)
    {
        return unpack('C*', $this->readVarying($bytes));
    } 
    
    private function readFixed($bytes)
    {
        return $this->readBytes($bytes);
    }

    private function readUnknown($bytes)
    {
        return $this->readFixed($bytes);
    }
    
    private function readInt($bytes)
    {
        $packed = $this->readFixed($bytes);
        $unpacked = null;
    
        switch ($bytes) {
            case 1:
                $unpacked = unpack('c', $packed);
                break;
            case 2:
                $unpacked = unpack('s', $packed);
                break;
            case 4:
                $unpacked = unpack('l', $packed);
                break;
            case 8:
                $unpacked = unpack('q', $packed);
                break;
        }
    
        if ($unpacked) {
            return $unpacked[1];
        }
    
        return 0;    
    }
    
    private function readUInt($bytes)
    {
        $packed = $this->readFixed($bytes);
        $unpacked = null;
    
        switch ($bytes) {
            case 1:
                $unpacked = unpack('C', $packed);
                break;
            case 2:
                $unpacked = unpack('S', $packed);
                break;
            case 4:
                $unpacked = unpack('L', $packed);
                break;
            case 8:
                $unpacked = unpack('Q', $packed);
                break;
        }
    
        if ($unpacked) {
            return $unpacked[1];
        }
    
        return 0;    
    }
    
    private function readBool($bytes)
    {
        $packed = $this->readFixed($bytes);
        $unpacked = null;
    
        switch ($bytes) {
            case 1:
                $unpacked = unpack('C', $packed);
                break;
            case 2:
                $unpacked = unpack('S', $packed);
                break;
            case 4:
                $unpacked = unpack('L', $packed);
                break;
        }
    
        if ($unpacked) {
            return $unpacked[1] === 1;
        }
    
        return false;    
    }
    
    private function readFloat($bytes)
    {
        $packed = $this->readFixed($bytes);
        $unpacked = unpack('d', $packed);
        if ($unpacked) {
            return $unpacked[1];
        }
    
        return 0;    
    }

    /**
     * Convert 2/4/8 byte into a signed integer. This is needed to make code 32bit php and 64bit compatible as Pack function
     * does not have options to convert big endian signed integers
     * taken from http://stackoverflow.com/q/13322327/2514290
     * @param int $uint
     * @param int $bitSize
     * @return int
     */
    // private static function uintToSignedInt($uint, $bitSize = 16)
    // {
    //     if ($bitSize === 16 && ($uint & 0x8000) > 0) {
    //         // This is a negative number.  Invert the bits and add 1 and add negative sign
    //         $uint = -((~$uint & 0xFFFF) + 1);
    //     } elseif ($bitSize === 32 && ($uint & 0x80000000) > 0) {
    //         // This is a negative number.  Invert the bits and add 1 and add negative sign
    //         $uint = -((~$uint & 0xFFFFFFFF) + 1);
    //     } elseif ($bitSize === 64 && ($uint & 0x8000000000000000) > 0) {
    //         // This is a negative number.  Invert the bits and add 1 and add negative sign
    //         $uint = -((~$uint & 0xFFFFFFFFFFFFFFFF) + 1);
    //     }
    //     return $uint;
    // }    
    
    private function readDate($bytes)
    {
        // $packed = $this->readFixed($bytes);
        // $unpacked = unpack('vlow/vhigh', $packed);
        // var_dump($unpacked);
        // $unpacked['high'] = self::uintToSignedInt($unpacked['high']);
        // var_dump($unpacked);
        // $probably = ($unpacked['high'] << 16) + $unpacked['low'];
        // var_dump($probably);
        // var_dump(($probably - 693594));
        // var_dump((($probably - 693594) - 25569) * 86400);
        // if ($unpacked) {
        //     return date($this->dateFormat, (($probably - 693594) - 25569) * 86400);
        // }
        $packed = $this->readFixed($bytes);
        $unpacked = unpack('l', $packed);
        if ($unpacked) {
            return date($this->dateFormat, (($unpacked[1] - self::DATE_ZERO) - self::DATE_BCD_AND_UTIME_DIFF) * 86400);
        }

        return 0;
    }

    private function readTime($bytes) // fix, but cannot get any data for this
    {
        $packed = $this->readFixed($bytes);
        $unpacked = unpack('l', $packed);
        if ($unpacked) {
            return date('H:i:s', $unpacked[1]);
        }
    
        return 0;
    }

    private function readTimestamp($bytes)
    {
        $packed = $this->readFixed($bytes);    
        $unpacked = unpack('q', $packed);
        if ($unpacked) {
            return $unpacked[1];
        }
    
        return 0;
    }
    
    private function readValue(Field $field)
    {
        $type = $field->getType();
        $attr = $type->getAttr();
        $name = $type->getName();
        $bytes = $field->getSize();
        
        if ($attr === 'fixed') {
            switch ($name) {
                case 'unknown':
                    return $this->readUnknown($bytes);
                case 'int':
                    return $this->readInt($bytes);
                case 'uint':
                    return $this->readUInt($bytes);
                case 'bool':
                    return $this->readBool($bytes);
                case 'float':
                    return $this->readFloat($bytes);
                case 'date':
                    return $this->readDate($bytes);
                case 'time':
                    return $this->readTime($bytes);
                case 'timestamp':
                    return $this->readTimestamp($bytes);
                default:
                    return $this->readUnknown($bytes);
            }
        }
        else {
            switch ($name) {
                case 'zstring':
                    return $this->readZString($bytes);
                case 'unicode':
                    return $this->readUnicodeString($bytes);
                case 'bytes':
                    return $this->readVariyngBytes($bytes);
                default:
                    return $this->readVarying($bytes);
            }
        }
    }

    /**
     *
     * @return Type
     */
    private function readType()
    {
        $parsedFiledType = $this->parseFieldType($this->readInt(2));
        return $this->explainFieldType($parsedFiledType);
    }

    /**
     *
     * @param int $fieldsCount
     * @return Field[]
     */
    private function readFields($fieldsCount)
    {
        $fields = [];
        for ($i = 0; $i < $fieldsCount; ++$i) {
            $nameLen = $this->readUInt(1);

            $fields[] = new Field(
                $this->readBytes($nameLen),
                $this->readInt(2),
                $this->readType(),
                $this->readInt(2),
                $this->readProps($this->readInt(2))
            );
        }

        return $fields;
    }
    
    private function readProps($propsCount)
    {
        $props = [];

        for ($i = 0; $i < $propsCount; ++$i) {
            $nameLen = $this->readUInt(1);
            $name = $this->readBytes($nameLen);
            $size = $this->readInt(2);
            $type = $this->readType();

            $fakeField = new Field($name, $size, $type, '', []);
            $props[] = new Prop($name, $size, $type, $this->readValue($fakeField));
        }

        return $props;
    }

    public function __destruct()
    {
        if ($this->isOpen()) {
            fclose($this->handler);
        }
    }
}
