<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\App;

use Garden\Cli\App\CliApplication;
use Garden\Cli\Cli;
use Garden\Cli\Tests\AbstractCliTest;
use Garden\Cli\Tests\Fixtures\Application;
use Garden\Cli\Tests\Fixtures\TestCommands;

class ApplicationTest extends AbstractCliTest {
    /**
     * @var CliApplication
     */
    private $app;

    public function setUp(): void {
        parent::setUp();
        $this->app = new CliApplication();
    }

    public function testAddMethodSetters(): void {
        $this->app->addMethod(TestCommands::class, 'noParams');

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
                CliApplication::META_DISPATCH_NAME => 'setAnOrange',
            ]
        ], $opt->jsonSerialize());
    }

    public function testAddMethodArgs(): void {
        $this->app->addMethod(TestCommands::class, 'decodeStuff');

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
                CliApplication::META_DISPATCH_NAME => 'count',
            ]
        ], $arg->jsonSerialize());

        $arg = $schema->getOpt('foo');
        $this->assertArraySubsetRecursive([
            'description' => 'Hello world.',
            'required' => false,
            'type' => 'string',
            'meta' => [
                CliApplication::META_DISPATCH_TYPE => CliApplication::TYPE_PARAMETER,
                CliApplication::META_DISPATCH_NAME => 'foo',
            ]
        ], $arg->jsonSerialize());
    }
}
