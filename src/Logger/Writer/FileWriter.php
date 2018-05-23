<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Writer;

use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Log writer to append output to a file.
 */
class FileWriter implements WriterInterface {

    /**
     * @var resource The file handle resource.
     */
    protected $handle;

    /**
     * @var FormatterInterface[] An array of formatters.
     */
    protected $formatters = [];

    /**
     * @var bool Whether or not to show durations for tasks.
     */
    protected $showDurations = true;

    /**
     * FileWriter constructor.
     *
     * @param string $filePath The path of the file to append to.
     * @param string $mode The type of access to the file.
     * @param bool $useIncludePath Whether to search the include path for the file.
     *
     * @throws \Exception
     */
    public function __construct(string $filePath, string $mode = 'a', bool $useIncludePath = false) {
        $this->handle = fopen($filePath, $mode, $useIncludePath);

        // ensure the file opened
        if ($this->handle === false) {
            throw new \Exception('The "'.$filePath.'" could not be opened/created');
        }
    }

    /**
     * FileWriter destructor.
     */
    public function __destruct() {
        // ensure the file we have opened gets closed
        fclose($this->handle);
    }

    /**
     * Set the showDurations.
     *
     * @param boolean $showDurations Flag to indicate if durations should be displayed.
     *
     * @return $this
     */
    public function setShowDurations(bool $showDurations) {
        $this->showDurations = $showDurations;

        return $this;
    }

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function getShowDurations() {
        return $this->showDurations;
    }

    /**
     * Add a formatter to be used by the writer.
     *
     * @param FormatterInterface $formatter A formatter to use for the writer.
     *
     * @return $this
     */
    public function addFormatter(FormatterInterface $formatter) {
        array_push($this->formatters, $formatter);

        return $this;
    }

    /**
     * Write the stream.
     *
     * @param int $timestamp The unix timestamp of the log.
     * @param string $logLevel The level of the message (e.g. INFO, SUCCESS, WARNING, ERROR).
     * @param string $message The message.
     * @param int $indentLevel The nesting level of the message.
     * @param float|null $duration The duration to add to the message.
     */
    public function write(int $timestamp, string $logLevel, string $message, int $indentLevel = 0, $duration = null) {
        // ensure our stream hasn't died
        if (feof($this->handle)) {
            // @codeCoverageIgnoreStart
            trigger_error('Called '.__CLASS__.'::write() but file handle was closed.', E_USER_WARNING);
            return;
            // @codeCoverageIgnoreEnd
        }

        // apply each of the formatters we've attached to this writer
        foreach($this->formatters as $formatter) {
            list($timestamp, $logLevel, $message, $indentLevel, $duration) = $formatter->format($timestamp, $logLevel, $indentLevel, $message, $duration);
        }

        // convert the indent levels to a visual representation
        $indentation = $indentLevel ? str_repeat('  ', $indentLevel).'- ' : '';

        // build the log entry we'll write to the stream
        $logEntry = $timestamp . ' ' . $indentation . ' ' .$message;
        if ($this->showDurations) {
            $logEntry .= ' '.$duration;
        }

        // write the message
        fwrite($this->handle, trim($logEntry).PHP_EOL);
    }
}
