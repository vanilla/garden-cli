<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */
namespace Garden\Cli\Tests;

use Garden\Cli\Args;

/**
 * Tests for the {@link Args} class.
 */
class ArgsTest extends \PHPUnit_Framework_TestCase {

    /**
     * Test basic get/set functionality.
     */
    public function testGetSet() {
        $args = new Args();

        $args->setArgs(['foo']);
        $this->assertSame(['foo'], $args->getArgs());

        $args->setCommand('cmd');
        $this->assertSame('cmd', $args->getCommand());

        $args->setMeta('meta', 123);
        $this->assertSame(123, $args->getMeta('meta'));

        $this->assertSame('default', $args->getMeta('nex', 'default'));

        $args->setOpt('grim', 567);
        $this->assertSame(567, $args->getOpt('grim'));

        $args->setOpts(['opt' => 'value']);
        $this->assertSame(['opt' => 'value'], $args->getOpts());

        $args->setOpt('opt', null);
        $this->assertSame([], $args->getOpts());

        $args['foo'] = 'bar';
        $this->assertTrue(isset($args['foo']));
        $this->assertSame('bar', $args['foo']);
        $this->assertSame('bar', $args->getOpt('foo'));

        unset($args['foo']);
        $this->assertNull($args['foo']);
    }

    /**
     * Test args json serialization.
     */
    public function testJsonSerialize() {
        $arg = new Args();
        $arg->setCommand('cmd')
            ->setOpt('opt1', 123)
            ->setOpt('opt2', 456)
            ->setMeta('met', 'a')
            ->setArgs([1, 2, 3]);

        $json = json_encode($arg);
        $this->assertEquals([
            'command' => 'cmd',
            'opts' => ['opt1' => 123, 'opt2' => 456],
            'args' => [1, 2, 3],
            'meta' => ['met' => 'a']
        ], json_decode($json, true));
    }
}
