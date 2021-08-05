<?php


namespace Exam\Customer\Logger\Handler;

use Monolog\Logger;

class InfoLogger extends BaseLogger
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;
}
