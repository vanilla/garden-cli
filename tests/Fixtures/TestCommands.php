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
     * @var calls
     */
    public $calls;

    protected function call(string $func, array $args  = []) {
        $this->calls[] = ['func' => $func] + $args;
    }

    public function findCall(string $func): ?array {
        foreach ($this->calls as $call) {
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
    }

    /**
     * This method has no parameters.
     */
    public function noParams(): void {
        $this->call(__FUNCTION__);
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
    }

    public function setDb(Db $db) {
        $this->call(__FUNCTION__, compact('db'));
    }
}
