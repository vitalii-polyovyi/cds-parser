<?php

namespace Lambo\CDSFile;

class Record
{
    /**
     *
     * @var RecordStatus
     */
    private $status = null;

    /**
     * Record visibility
     *
     * @var boolean
     */
    private $isVisible = true;

    private $data = [];

    /**
     *
     * @param RecordStatus $status
     * @param array $data
     */
    public function __construct(RecordStatus $status, array $data)
    {
        $this->status = $status;
        $this->data = $data;
        $this->setRecordVisibility();
    }

    private function setRecordVisibility()
    {
        $code = $this->status->getCode();

        $this->isVisible = !($code === 2 || $code === 1 || $code === 32);

        return $this;
    }

    public function isVisible()
    {
        return $this->isVisible;
    }

    public function getStatus()
    {
        return $this->status;
    }
    
    public function getData()
    {
        return $this->data;
    }

    private function setVisibility($visibility)
    {
        $this->isVisible = $visibility;
        return $this;
    }

    public function makeVisible()
    {
        return $this->setVisibility(true);
    }

    public function makeInvisible()
    {
        return $this->setVisibility(false);
    }

    public function hasStatus($statusName)
    {
        return ($this->getStatus()->getName() === $statusName);
    }
}
