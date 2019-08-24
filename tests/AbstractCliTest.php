<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;


use PHPUnit\Framework\TestCase;

abstract class AbstractCliTest extends TestCase {
    private $errors;

    private $expectErrors;

    /**
     * Add a custom error handler that tracks PHP errors.
     */
    protected function setUp() {
        $this->errors = [];
        $this->expectErrors = false;
        set_error_handler(function ($errno, $message, $file, $line) {
            $reporting = error_reporting();
            if ($this->expectErrors) {
                $this->errors[] = compact("errno", "message", "file", "line");
            } elseif (error_reporting() !== 0) {
                switch ($errno) {
                    case E_NOTICE:
                    case E_USER_NOTICE:
                    case E_STRICT:
                        throw new \PHPUnit_Framework_Error_Notice($message, $errno, $file, $line);
                        break;
                    case E_WARNING:
                    case E_USER_WARNING:
                        throw new \PHPUnit_Framework_Error_Warning($message, $errno, $file, $line);
                        break;
                    default:
                        throw new \PHPUnit_Framework_Error($message, $errno, $file, $line);
                }
            }
        });
    }

    /**
     * Assert that a given error string was encountered.
     *
     * @param string $errstr The error string to test for.
     */
    public function assertErrorString($errstr) {
        foreach ($this->errors as $error) {
            if ($error["errstr"] === $errstr) {
                return;
            }
        }
        $this->fail("Error with level message '{$errstr}' not found in ",
            var_export($this->errors, true));
    }

    /**
     * Assert that a given error number was encountered.
     *
     * @param int $errno The error number to test for.
     */
    public function assertErrorNumber($errno) {
        foreach ($this->errors as $error) {
            if ($error["errno"] === $errno) {
                $this->assertTrue(true);
                return;
            }
        }
        $nos = [
            E_NOTICE => 'E_NOTICE',
            E_DEPRECATED => 'E_DEPRECATED',
            E_WARNING => 'E_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            E_USER_WARNING => 'E_USER_WARNING'
        ];

        if (array_key_exists($errno, $nos)) {
            $errno = $nos[$errno];
        }

        $this->fail("Error with level number '{$errno}' not found in ".
            var_export($this->errors, true));
    }

    protected function expectErrors(bool $value) {
        $this->expectErrors = $value;
    }

    /**
     * Clear out the current errors.
     */
    public function clearErrors() {
        $this->errors = [];
    }
}
