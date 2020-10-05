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
     * Set an orange.
     *
     * @param int $o
     */
    public function setAnOrange(int $o) {

    }

    /**
     * This method has no parameters.
     */
    public function noParams(): void {

    }

    /**
     * Decode some stuff.
     *
     * @param int $count The number of things.
     * @param string $foo Hello world.
     * @param Db|null $db Don't reflect me.
     */
    public function decodeStuff(int $count, string $foo = '', Db $db = null) {

    }

    public function setDb(Db $db) {

    }
}
