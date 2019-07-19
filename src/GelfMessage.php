<?php


namespace Merqueo\GelfFormatter;


use Psr\Log\LogLevel;
use RuntimeException;

/**
 * Class GelfMessage
 * @package Merqueo\GelfFormatter
 */
class GelfMessage implements GelfMessageInterface
{

    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    protected $host;
    protected $shortMessage;
    protected $fullMessage;
    protected $timestamp;
    protected $level;
    protected $facility;
    protected $file;
    protected $line;
    protected $additionals = [];
    protected $version;

    /**
     * A list of the PSR LogLevel constants which is also a mapping of
     * syslog code to psr-value
     *
     * @var array
     */
    private static $psrLevels = [
        LogLevel::EMERGENCY,    // 0
        LogLevel::ALERT,        // 1
        LogLevel::CRITICAL,     // 2
        LogLevel::ERROR,        // 3
        LogLevel::WARNING,      // 4
        LogLevel::NOTICE,       // 5
        LogLevel::INFO,         // 6
        LogLevel::DEBUG         // 7
    ];

    /**
     * Creates a new message
     *
     * Populates timestamp and host with sane default values
     */
    public function __construct()
    {
        $this->timestamp = microtime(true);
        $this->host = gethostname();
        $this->level = self::ALERT;
        $this->version = "1.0";
    }

    /**
     * Trys to convert a given log-level (psr or syslog) to
     * the psr representation
     *
     * @param mixed $level
     * @return string
     */
    final public static function logLevelToPsr($level)
    {
        $origLevel = $level;

        if (is_numeric($level)) {
            $level = intval($level);
            if (isset(self::$psrLevels[$level])) {
                return self::$psrLevels[$level];
            }
        } elseif (is_string($level)) {
            $level = strtolower($level);
            if (in_array($level, self::$psrLevels)) {
                return $level;
            }
        }

        throw new RuntimeException(
            sprintf("Cannot convert log-level '%s' to psr-style", $origLevel)
        );
    }

    /**
     * Trys to convert a given log-level (psr or syslog) to
     * the syslog representation
     *
     * @param mxied
     * @return integer
     */
    final public static function logLevelToSyslog($level)
    {
        $origLevel = $level;

        if (is_numeric($level)) {
            $level = intval($level);
            if ($level <= self::DEBUG && $level >= self::EMERGENCY) {
                return $level;
            }
        } elseif (is_string($level)) {
            $level = strtolower($level);
            $syslogLevel = array_search($level, self::$psrLevels);
            if (false !== $syslogLevel) {
                return $syslogLevel;
            }
        }

        throw new RuntimeException(
            sprintf("Cannot convert log-level '%s' to syslog-style", $origLevel)
        );
    }

    /**
     * Returns the GELF version of the message
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Returns the host of the message
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Returns the short text of the message
     *
     * @return string
     */
    public function getShortMessage()
    {
        return $this->shortMessage;
    }

    /**
     * Returns the full text of the message
     *
     * @return string
     */
    public function getFullMessage()
    {
        return $this->fullMessage;
    }

    /**
     * Returns the timestamp of the message
     *
     * @return float
     */
    public function getTimestamp()
    {
        return (float)$this->timestamp;
    }

    /**
     * Returns the log level of the message as a Psr\Log\Level-constant
     *
     * @return string
     */
    public function getLevel()
    {
        return self::logLevelToPsr($this->level);
    }

    /**
     * Returns the log level of the message as a numeric syslog level
     *
     * @return int
     */
    public function getSyslogLevel()
    {
        return self::logLevelToSyslog($this->level);
    }

    /**
     * Returns the facility of the message
     *
     * @return string
     */
    public function getFacility()
    {
        return $this->facility;
    }

    /**
     * Returns the file of the message
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Returns the the line of the message
     *
     * @return string
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * Returns the value of the additional field of the message
     *
     * @param string $key
     * @return mixed
     */
    public function getAdditional($key)
    {
        if (!isset($this->additionals[$key])) {
            throw new RuntimeException(
                sprintf("Additional key '%s' is not defined", $key)
            );
        }

        return $this->additionals[$key];
    }

    /**
     * Checks if a additional fields is set
     *
     * @param string $key
     * @return bool
     */
    public function hasAdditional($key)
    {
        return isset($this->additionals[$key]);
    }

    /**
     * Returns all additional fields as an array
     *
     * @return array
     */
    public function getAllAdditionals()
    {
        return $this->additionals;
    }

    /**
     * Converts the message to an array
     *
     * @return array
     */
    public function toArray()
    {
        $message = array(
            'version' => $this->getVersion(),
            'host' => $this->getHost(),
            'host_name' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "cli.merqueo.com",
            'short_message' => $this->getShortMessage(),
            'full_message' => $this->getFullMessage(),
            'level' => $this->getSyslogLevel(),
            'timestamp' => $this->getTimestamp(),
            'time' => date('Y-m-d H:m:i', $this->getTimestamp()),
            'facility' => $this->getFacility(),
            'file' => $this->getFile(),
            'line' => $this->getLine()
        );

        // Transform 1.1 deprecated fields to additionals
        // Will be refactored for 2.0, see #23
        if ($this->getVersion() == "1.1") {
            foreach (['line', 'facility', 'file'] as $idx) {
                $message["_" . $idx] = $message[$idx];
                unset($message[$idx]);
            }
        }

        // add additionals
        foreach ($this->getAllAdditionals() as $key => $value) {
            $message["_" . $key] = $value;
        }

        // return after filtering empty strings and null values
        return array_filter($message, function ($message) {
            return is_bool($message)
                || (is_string($message) && strlen($message))
                || is_int($message)
                || !empty($message);
        });
    }

    /**
     * @param $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @param $host
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param $shortMessage
     * @return $this
     */
    public function setShortMessage($shortMessage)
    {
        $this->shortMessage = $shortMessage;

        return $this;
    }

    /**
     * @param $fullMessage
     * @return $this
     */
    public function setFullMessage($fullMessage)
    {
        $this->fullMessage = $fullMessage;

        return $this;
    }

    public function setTimestamp($timestamp)
    {
        if ($timestamp instanceof \DateTime || $timestamp instanceof \DateTimeInterface) {
            $timestamp = $timestamp->format("U.u");
        }

        $this->timestamp = (float)$timestamp;

        return $this;
    }

    /**
     * @param $level
     * @return $this
     */
    public function setLevel($level)
    {
        $this->level = self::logLevelToSyslog($level);

        return $this;
    }

    /**
     * @param $facility
     * @return $this
     */
    public function setFacility($facility)
    {
        $this->facility = $facility;

        return $this;
    }

    /**
     * @param $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @param $line
     * @return $this
     */
    public function setLine($line)
    {
        $this->line = $line;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setAdditional($key, $value)
    {
        if (!$key) {
            return $this;
        }
        $this->additionals[$key] = $value;
        return $this;
    }
}
