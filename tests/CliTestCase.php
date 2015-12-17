<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Cli\Tests;


class CliTestCase extends \PHPUnit_Framework_TestCase {
    private $errors;

    /**
     * Add a custom error handler that tracks PHP errors.
     */
    protected function setUp() {
        $this->errors = [];
        set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
            $this->errors[] = compact("errno", "errstr", "errfile", "errline", "errcontext");
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

        $this->fail("Error with level number '{$errno}' not found in ",
            var_export($this->errors, true));
    }

    /**
     * Clear out the current errors.
     */
    public function clearErrors() {
        $this->errors = [];
    }
}
