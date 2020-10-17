<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures;

/**
 * Class TestCommands.
 */
class TestCommands {
    /**
     * @var array
     */
    public static $calls;

    /**
     * @var Db
     */
    public $db;

    public static function call(string $func, array $args  = []) {
        self::$calls[] = ['func' => $func] + $args;
    }

    public static function findCall(string $func): ?array {
        foreach (self::$calls as $call) {
            if ($call['func'] === $func) {
                return $call;
            }
        }
        return null;
    }

    /**
     * Set an orange.
     *
     * @param int $o
     */
    public function setAnOrange(int $o) {
        $this->call(__FUNCTION__, compact('o'));
        return $this;
    }

    public static function setBar(string $bar) {
        self::call(__FUNCTION__, compact('bar'));
    }

    /**
     * This method has no parameters.
     */
    public function noParams() {
        $this->call(__FUNCTION__);
        return $this;
    }

    /**
     * Decode some stuff.
     *
     * @param int $count The number of things.
     * @param string $foo Hello world.
     * @param Db|null $db Don't reflect me.
     */
    public function decodeStuff(int $count, string $foo = 'bar', Db $db = null) {
        $this->call(__FUNCTION__, compact('count', 'foo', 'db'));
        return $this;
    }

    public function setDb(Db $db) {
        $this->db = $db;
        $this->call(__FUNCTION__, compact('db'));
        return $this;
    }

    public static function format(string $body) {
        self::call(__FUNCTION__, compact('body'));
    }
}
