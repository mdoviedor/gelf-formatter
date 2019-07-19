<?php


namespace Merqueo\GelfFormatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;

/**
 * Class GelfMessageFormatter
 * @package Merqueo\GelfFormatter
 */
class GelfMessageFormatter extends NormalizerFormatter
{
    const DEFAULT_MAX_LENGTH = 32766;

    /**
     * @var string the name of the system for the Gelf log message
     */
    protected $systemName;

    /**
     * @var string a prefix for 'extra' fields from the Monolog record (optional)
     */
    protected $extraPrefix;

    /**
     * @var string a prefix for 'context' fields from the Monolog record (optional)
     */
    protected $contextPrefix;

    /**
     * @var int max length per field
     */
    protected $maxLength;

    /**
     * @var string
     */
    protected $version;


    /**
     * Translates Monolog log levels to Graylog2 log priorities.
     */
    private $logLevels = [
        Logger::DEBUG => 7,
        Logger::INFO => 6,
        Logger::NOTICE => 5,
        Logger::WARNING => 4,
        Logger::ERROR => 3,
        Logger::CRITICAL => 2,
        Logger::ALERT => 1,
        Logger::EMERGENCY => 0,
    ];

    public function __construct($systemName = null, $version, $extraPrefix = null, $contextPrefix = 'ctxt_', $maxLength = null)
    {
        parent::__construct('U.u');

        $this->systemName = $systemName ?: gethostname();
        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
        $this->maxLength = is_null($maxLength) ? self::DEFAULT_MAX_LENGTH : $maxLength;
        $this->version = $version;
    }

    public function format(array $record)
    {
        $record = parent::format($record);

        if (!isset($record['datetime'])) {
            $record['datetime'] = time();
        }

        if (!isset($record['message'], $record['level'])) {
            $record['message'] = 'The record should at least contain datetime, message and level keys';
            $record['level'] = 4;
        }

        $message = new GelfMessage();
        $message->setTimestamp($record['datetime'])
            ->setFullMessage((string)$record['message'])
            ->setHost($this->systemName)
            ->setLevel($this->logLevels[$record['level']])
            ->setVersion($this->version);

        if (isset($record ['context']['short_message'])) {
            $message->setShortMessage($record ['context']['short_message']);
            unset($record ['context']['short_message']);
        } else {
            $message->setShortMessage(substr((string)$record['message'], 0, 20));
        }

        if (isset($record ['context']['line'])) {
            $message->setLine($record ['context']['line']);
            unset($record ['context']['line']);
        }

        // message length + system name length + 200 for padding / metadata
        $len = 200 + strlen((string)$record['message']) + strlen($this->systemName);

        if ($len > $this->maxLength) {
            $message->setFullMessage(substr($record['message'], 0, $this->maxLength));
        }

        if (isset($record['channel'])) {
            $message->setFacility($record['channel']);
        }

        foreach ($record['extra'] as $key => $val) {
            $val = is_scalar($val) || null === $val ? $val : $val;
            $len = strlen($this->extraPrefix . $key . $val);
            if ($len > $this->maxLength) {
                $message->setAdditional($this->extraPrefix . $key, substr($val, 0, $this->maxLength));
                break;
            }
            $message->setAdditional($this->extraPrefix . $key, $val);
        }

        foreach ($record['context'] as $key => $val) {
            $x = is_scalar($val) || null === $val ? $val : $this->toJson($val);
            $len = strlen($this->contextPrefix . $key . $x);
            if ($len > $this->maxLength) {
                $message->setAdditional($this->contextPrefix . $key, substr($x, 0, $this->maxLength));
                break;
            }
            $message->setAdditional($this->contextPrefix . $key, $val);
        }

        return sprintf("%s\n",$this->toJson($message->toArray()));
    }
}
