<?php

declare(strict_types=1);

namespace SimpleSAML\Logger;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Utils;

/**
 * A logging handler that dumps logs to files.
 *
 * @package SimpleSAMLphp
 */
class FileLoggingHandler implements LoggingHandlerInterface
{
    /**
     * A string with the path to the file where we should log our messages.
     *
     * @var null|string
     */
    protected ?string $logFile = null;

    /**
     * This array contains the mappings from syslog log levels to names. Copied more or less directly from
     * SimpleSAML\Logger\ErrorLogLoggingHandler.
     *
     * @var array<int, string>
     */
    private static $levelNames = [
        Logger::EMERG   => 'EMERGENCY',
        Logger::ALERT   => 'ALERT',
        Logger::CRIT    => 'CRITICAL',
        Logger::ERR     => 'ERROR',
        Logger::WARNING => 'WARNING',
        Logger::NOTICE  => 'NOTICE',
        Logger::INFO    => 'INFO',
        Logger::DEBUG   => 'DEBUG',
    ];

    /** @var string */
    protected string $processname;

    /** @var string */
    protected string $format = "%b %d %H:%M:%S";


    /**
     * Build a new logging handler based on files.
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        // get the metadata handler option from the configuration
        $this->logFile = $config->getPathValue('loggingdir', 'log/') .
            $config->getString('logging.logfile', 'simplesamlphp.log');

        // Remove any non-printable characters before storing
        $this->processname = preg_replace(
            '/[\x00-\x1F\x7F\xA0]/u',
            '',
            $config->getString('logging.processname', 'SimpleSAMLphp')
        );

        if (@file_exists($this->logFile)) {
            if (!@is_writeable($this->logFile)) {
                throw new \Exception("Could not write to logfile: " . $this->logFile);
            }
        } else {
            if (!@touch($this->logFile)) {
                throw new \Exception(
                    "Could not create logfile: " . $this->logFile .
                    " The logging directory is not writable for the web server user."
                );
            }
        }

        $timeUtils = new Utils\Time();
        $timeUtils->initTimezone();
    }


    /**
     * Set the format desired for the logs.
     *
     * @param string $format The format used for logs.
     */
    public function setLogFormat(string $format): void
    {
        $this->format = $format;
    }


    /**
     * Log a message to the log file.
     *
     * @param int    $level The log level.
     * @param string $string The formatted message to log.
     */
    public function log(int $level, string $string): void
    {
        if (!is_null($this->logFile)) {
            // set human-readable log level. Copied from SimpleSAML\Logger\ErrorLogLoggingHandler.
            $levelName = sprintf('UNKNOWN%d', $level);
            if (array_key_exists($level, self::$levelNames)) {
                $levelName = self::$levelNames[$level];
            }

            $formats = ['%process', '%level'];
            $replacements = [$this->processname, $levelName];

            $matches = [];
            if (preg_match('/%date(?:\{([^\}]+)\})?/', $this->format, $matches)) {
                $format = "M j H:i:s";
                if (isset($matches[1])) {
                    $format = $matches[1];
                }

                array_push($formats, $matches[0]);
                array_push($replacements, date($format));
            }

            $string = str_replace($formats, $replacements, $string);
            file_put_contents($this->logFile, $string . \PHP_EOL, FILE_APPEND);
        }
    }
}
