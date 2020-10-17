<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures;


class Db {
    /**
     * @var mixed|string
     */
    public $name;
    /**
     * @var mixed|string
     */
    public $user;

    public function __construct($name = '', $user = '') {
        $this->name = $name;
        $this->user = $user;
    }

    public function setDbname(string $name) {
        $this->name = $name;
    }

    public function setUser(string $user) {
        $this->user = $user;
    }

    public static function create($name, $user) {
        return new Db($name, $user);
    }
}
