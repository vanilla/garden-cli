<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures;


use Garden\Cli\App\CliApplication;

class TestApplication extends CliApplication {
    protected function configureCli(): void {
        parent::configureCli();

        $this->addMethod(TestCommands::class, 'noParams');
        $this->addMethod(TestCommands::class, 'DecodeStuff');
        $this->addMethod(TestCommands::class, 'format');
    }
}
