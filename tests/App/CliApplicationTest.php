<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\App;

use Garden\Cli\App\CliApplication;
use Garden\Cli\Tests\AbstractCliTest;
use Garden\Cli\Tests\Fixtures\Application;
use Garden\Cli\Tests\Fixtures\TestApplication;
use Garden\Cli\Tests\Fixtures\TestCommands;

class CliApplicationTest extends AbstractCliTest {
    /**
     * @var CliApplication
     */
    private $app;

    /**
     * @var TestCommands
     */
    private $commands;

    public function setUp(): void {
        parent::setUp();
        $this->app = new TestApplication();

        $this->commands = new TestCommands();
        TestCommands::$calls = [];
        $this->app->getContainer()->setInstance(TestCommands::class, $this->commands);
    }

    /**
     * Assert that a method was called.
     *
     * @param string $func
     * @param array $args
     * @return array
     */
    public function assertCall(string $func, array $args = []): array {
        $call = $this->commands->findCall($func);
        $this->assertNotNull($call, "Call not found: $func");

        $this->assertArraySubsetRecursive($args, $call);

        return $call;
    }

    /**
     * Reflecting to a method should also route to method args.
     */
    public function testAddObjectSetters(): void {
        $schema = $this->app->getCli()->getSchema('no-params');
        $this->assertSame('This method has no parameters.', $schema->getDescription());
        $this->assertSame(TestCommands::class.'::noParams', $schema->getMeta(CliApplication::META_ACTION));

        $opt = $schema->getOpt('an-orange');
        $this->assertArraySubsetRecursive([
            'description' => 'Set an orange.',
            'required' => false,
            'type' => 'integer',
            'meta' => [
                CliApplication::META_DISPATCH_TYPE => CliApplication::TYPE_CALL,
                CliApplication::META_DISPATCH_VALUE => 'setAnOrange',
            ]
        ], $opt->jsonSerialize());

        $opt = $schema->getOpt('bar');
        $this->assertArraySubsetRecursive([
            'description' => '',
            'required' => false,
            'type' => 'string',
            'meta' => [
                CliApplication::META_DISPATCH_TYPE => CliApplication::TYPE_CALL,
                CliApplication::META_DISPATCH_VALUE => 'setBar',
            ]
        ], $opt->jsonSerialize());

        $this->assertFalse($schema->hasOpt('db'), 'Setters with types that cannot be set via CLI should not be reflected.');
    }

    /**
     * Static methods should only reflect static setters.
     */
    public function testAddStaticMethodSetters(): void {
        $schema = $this->app->getCli()->getSchema('format');
        $this->assertTrue($schema->hasOpt('bar'));
        $this->assertFalse($schema->hasOpt('an-orange'), 'Static methods should not reflect non-static setters.');
    }

    /**
     * Test basic method arg reflection.
     */
    public function testAddMethodParams(): void {
        $schema = $this->app->getCli()->getSchema('decode-stuff');
        $this->assertSame('Decode some stuff.', $schema->getDescription());
        $this->assertSame(TestCommands::class.'::decodeStuff', $schema->getMeta(CliApplication::META_ACTION));

        $arg = $schema->getOpt('count');
        $this->assertArraySubsetRecursive([
            'description' => 'The number of things.',
            'required' => true,
            'type' => 'integer',
            'meta' => [
                CliApplication::META_DISPATCH_TYPE => CliApplication::TYPE_PARAMETER,
                CliApplication::META_DISPATCH_VALUE => 'count',
            ]
        ], $arg->jsonSerialize());

        $arg = $schema->getOpt('foo');
        $this->assertArraySubsetRecursive([
            'description' => 'Hello world.',
            'required' => false,
            'type' => 'string',
            'meta' => [
                CliApplication::META_DISPATCH_TYPE => CliApplication::TYPE_PARAMETER,
                CliApplication::META_DISPATCH_VALUE => 'foo',
            ]
        ], $arg->jsonSerialize());
    }

    /**
     * Test a basic dispatch.
     */
    public function testDispatch(): void {
        $r = $this->app->main([__FUNCTION__, 'decode-stuff', '--count=123']);
        $this->assertCall('decodeStuff', ['count' => 123, 'foo' => 'bar']);
    }

    /**
     * You should be able to call setters on a call.
     */
    public function testDispatchWithSetters(): void {
        $r = $this->app->main([__FUNCTION__, 'no-params', '--an-orange=4']);
        $this->assertCall('setAnOrange', ['o' => 4]);
        $this->assertCall('noParams');
        $this->assertCount(2, TestCommands::$calls);
    }

    /**
     * The dispatcher should work on static methods without getting an instance from the container.
     */
    public function testDispatchStatic(): void {
        $this->app->getContainer()->setInstance(TestCommands::class, 'error');

        $r = $this->app->main([__FUNCTION__, 'format', '--body=foo']);
        $this->assertCall('format', ['body' => 'foo']);
    }
}
