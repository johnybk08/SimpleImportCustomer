<?php

namespace Exam\Customer\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Stdlib\DateTime\DateTime;

class BaseLogger extends Base
{
    const FILE_NAME = 'customer-import-log-';

    const FILE_NAME_EXTENSION = '.log';
    /**
     * @var string
     */
    protected $forderName = '/var/log/Customer/';

    /**
     * @var string
     */
    protected $fileName = '';

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * OracleLoggerBase constructor.
     * @param DriverInterface $filesystem
     * @param DateTime $date
     */
    public function __construct(
        DriverInterface $filesystem,
        DateTime $date
    ) {
        $this->date = $date;
        //set filePath
        $this->setFilePath();
        parent::__construct($filesystem, $this->getFilePath());
    }

    /**
     * Get file path for log
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set full path for log file
     *
     * @param string $filePath
     */
    public function setFilePath($filePath = '')
    {
        $fileName = self::FILE_NAME . $this->date->gmtDate('Ymd') . self::FILE_NAME_EXTENSION;
        $this->filePath = $filePath ? $filePath : BP . $this->forderName . $fileName;
    }
}
