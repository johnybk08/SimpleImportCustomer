<?php


namespace Exam\Customer\Logger\Handler;

use Monolog\Logger;

class ErrorLogger extends BaseLogger
{
    /**
     * @var int
     */
    protected $loggerType = Logger::ERROR;
}
