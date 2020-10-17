<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures;


use Garden\Cli\Application\CliApplication;
use Garden\Container\Container;
use Garden\Container\Reference;

class TestApplication extends CliApplication {
    protected function configureContainer(): void {
        parent::configureContainer();

        $this->getContainer()
            ->rule(TestCommands::class)
            ->setShared(true)
            ->addCall('setDb', [new Reference(Db::class)]);
    }

    protected function configureCli(): void {
        parent::configureCli();

        $this->addMethod(TestCommands::class, 'noParams', [self::OPT_SETTERS => true]);
        $this->addMethod(TestCommands::class, 'DecodeStuff');
        $this->addMethod(TestCommands::class, 'format', [self::OPT_SETTERS => true]);
    }
}
