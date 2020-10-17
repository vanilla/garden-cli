<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */
namespace Garden\Cli\Tests;

use Garden\Cli\Cli;

/**
 * Unit tests for the various command line interface classes.
 */
class CliTest extends AbstractCliTest {
    /**
     * Test a cli run with named arguments.
     */
    public function testArgNames() {
        $cli = new Cli();
        $cli->description('A cli with named args.')
            ->arg('from', 'The path from.')
            ->arg('to', 'The path to.');

        $args = $cli->parse(['script', '/var/foo.txt', '/var/bar.txt'], false);

        $this->assertSame('/var/foo.txt', $args->getArg('from'));
        $this->assertSame('/var/bar.txt', $args->getArg('to'));

        $this->assertSame('/var/foo.txt', $args->getArg(0));
        $this->assertSame('/var/bar.txt', $args->getArg(1));
    }


    /**
     * Test a cli run with no commands.
     *
     * @param array $argv The args to test.
     * @dataProvider provideBasicArgvs
     */
    public function testNoCommandParse(array $argv) {
        $cli = $this->getBasicCli();

        // Test some basic parsing scenarios.
        $parsed = $cli->parse($argv, false);

        $this->assertEquals([
            'hello' => 'world',
            'enabled' => true,
            'disabled' => false,
            'count' => 3,
        ], $parsed->getOpts());
    }

    /**
     * Test a cli with different arg forms.
     *
     * @param array $argv The args to test.
     * @dataProvider provideBasicArgForms
     */
    public function testArgForms(array $argv) {
        $cli = $this->getBasicCli();

        $parsed = $cli->parse($argv, false);

        $this->assertSame('world', $parsed->getOpt('hello'));
    }

    /**
     * Test a cli against various ways of providing boolean arguments.
     *
     * @param array $argv The args to test.
     * @param array $expectedOpts The expected opt output.
     * @dataProvider provideBoolArgForms
     */
    public function testBoolArgForms(array $argv, array $expectedOpts) {
        $cli = new Cli();
        $cli->opt('boola:a', 'A bool.', false, 'boolean')
            ->opt('boolb:b', 'Another bool.', false, 'boolean')
            ->opt('str:s', 'A string', false);

        $parsed = $cli->parse($argv, false);

        $this->assertSame($expectedOpts, $parsed->getOpts());
    }

    /**
     * Test a cli against various ways of providing boolean arguments.
     *
     * @param array $argv The args to test.
     * @param array $expectedOpts The expected opt output.
     * @dataProvider provideOptionalArgValueForm
     */
    public function testArgOptionalValue(array $argv, array $expectedOpts) {
        $cli = new Cli();
        $cli->opt('optionalFlag:o', 'An optional flag that may take a value.', false, 'string');

        // Test some basic parsing scenarios.
        $parsed = $cli->parse($argv, false);

        $this->assertSame($expectedOpts, $parsed->getOpts());
    }

    /**
     * Test a missing option.
     */
    public function testMissingOpt() {
        $cli = $this->getBasicCli();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required option: hello');
        $cli->parse(['script', '-e'], false);
    }

    /**
     * Test invalid opt types.
     *
     * @param array $argv The arguments to parse.
     * @param string $message The expected exception message.
     * @dataProvider provideInvalidTypes
     */
    public function testInvalidTypes(array $argv, $message) {
        $this->expectException('\Exception');

        $cli = $this->getBasicCli();
        $cli->parse($argv, false);
    }

    /**
     * Test the help that gets printed with the --help opt.
     */
    public function testHelp() {
        $cli = $this->getBasicCli();

        $expectedHelp = <<<EOT
usage: script [<options>]

OPTIONS
  --count, -c      The count of things.
  --disabled, -d   Disabled or not
  --enabled, -e    Enabled or not.
  --hello, -h      Hello world.
  --help, -?       Display this help.
EOT;

        $this->expectException('\Exception');

        $cli->parse(['script', '--help'], false);
    }

    /**
     * Test the help that gets printed with long command description
     */
    public function testLongHelp() {
        $cli = $this->getLongDescCli();

        $expectedHelp = <<<EOT
usage: script <command> [<options>] [<args>]

COMMANDS
  command-long   Very long long long long long long long long long long long
                 long long long long long long long long long long long long
                 long long long long long long long description
EOT;

        $this->expectException('\Exception');

        $cli->parse(['script', '--help'], false);
    }

    /**
     * Test the help that gets printed with long command, option and argument descriptions
     */
    public function testLongCommandHelp() {
        $cli = $this->getLongDescCli();

        $expectedHelp = <<<EOT
usage: script command-long [<options>] [<args>]

Very long long long long long long long long long long long long long long long
long long long long long long long long long long long long long long long
description

OPTIONS
  --help, -?   Display this help.
  --opt-long   Very long long long long long long long long long long long long
               long long long long long long long long long long long long long
               long long long long long description

ARGUMENTS
  arg-long   Very long long long long long long long long long long long long
             long long long long long long long long long long long long long
             long long long long long description
EOT;

        $this->expectException('\Exception');

        $cli->parse(['script', 'command-long', '--help'], false);
    }

    /**
     * Test required option wrapping
     */
    public function testOptionWrapping() {
        $expectedHelp = <<<EOT
\033[1mOPTIONS\033[0m
  --count, -c      The count of things.
  --disabled, -d   Disabled or not
  --enabled, -e    Enabled or not.
  \033[1m--hello, -h   \033[0m   Hello world.
  --help, -?       Display this help.


EOT;

        $this->expectOutputString($expectedHelp);
        $this->getBasicCli()->setFormatOutput(true)->writeHelp();
    }

    /**
     * Get a sample {@link Cli} object with no commands for testing.
     *
     * @return Cli Returns the sample {@link Cli} instance.
     */
    protected function getBasicCli() {
        $cli = new Cli();

        $cli->opt('hello:h', 'Hello world.', true, 'string')
            ->opt('enabled:e', 'Enabled or not.', false, 'boolean')
            ->opt('disabled:d', 'Disabled or not', false, 'bool')
            ->opt('count:c', 'The count of things.', false, 'integer')
            ;

        return $cli;
    }

    /**
     * Get a sample {@link Cli} object with long option, arg and command descriptions
     *
     * @return Cli Returns the sample {@link Cli} instance.
     */
    protected function getLongDescCli() {
        $cli = new Cli();

        $description = 'Very'.str_repeat(' long ', 30).' description';

        $cli->command('command-long')
            ->description($description)
            ->opt('opt-long', $description, false, 'string')
            ->arg('arg-long', $description, false, 'string');

        return $cli;
    }

    /**
     * Provide some args for {@link testNoCommandParse()}.
     *
     * @return array Returns args for {@link testNoCommandParse()}.
     */
    public function provideBasicArgvs() {
        $result = [
            [['script', '--hello=world', '--enabled', '--disabled', 'false', '--count=3']],
            [['script', '-h', 'world', '-e', '-d', '0', '-c', '3']],
            [['script', '-hworld', '-e1', '-d0', '-ccc']],
            [['script', '--hello', 'world', '-ed0c2c']],
            [['script', 'filename', '--hello', 'world', '-c3', '--no-disabled', '-e']],
        ];
        return $result;
    }

    /**
     * Provide some args in different forms to make sure they all provide the same opt value.
     *
     * @return array Returns args for {@link CliTest::testArgForms()}.
     */
    public function provideBasicArgForms() {
        $result = [
            'long =' => [['script', '--hello=world']],
            'long space' => [['script', '--hello', 'world']],
            'short' => [['script', '-hworld']],
            'short = ' => [['script', '-h=world']],
            'short space' => [['script', '-h', 'world']],
        ];
        return $result;
    }

    /**
     * Provide data for {@link CliTest::testBoolArgForms()}.
     *
     * @return array Returns an array in the form `[$argv, $expectedOpts]`.
     */
    public function provideBoolArgForms() {
        $result = [
            'plain flags' => [['script', '-ab'], ['boola' => true, 'boolb' => true]],
            'flags and string' => [['script', '-abswut'], ['boola' => true, 'boolb' => true, 'str' => 'wut']],
            'flag value and string' => [['script', '-a1b0s=wut'], ['boola' => true, 'boolb' => false, 'str' => 'wut']],
            'flag followed by opt' => [['script', '-a', '-swut'], ['boola' => true, 'str' => 'wut']],
            '--no prefix' => [['script', '--no-boola', '--no-boolb'], ['boola' => false, 'boolb' => false]]
        ];
        return $result;
    }

    /**
     * Provide data for {@link CliTest::testArgOptionalValue()}.
     *
     * @return array Returns an array in the form `[$argv, $expectedOpts]`.
     */
    public function provideOptionalArgValueForm() {
        $result = [
            'nothing' => [['script'], []],
            'long form - no value' => [['script', '--optionalFlag'], ['optionalFlag' => '']],
            'short form - no value' => [['script', '-o'], ['optionalFlag' => '']],
            'long form - w value' => [['script', '--optionalFlag=used'], ['optionalFlag' => 'used']],
            'short form - w value' => [['script', '-o=used'], ['optionalFlag' => 'used']],
            'long form - w value - no equal sign' => [['script', '--optionalFlag', 'used'], ['optionalFlag' => 'used']],
            'short form - w value - no equal sign' => [['script', '-o', 'used'], ['optionalFlag' => 'used']],
        ];
        return $result;
    }

    /**
     * Provide some args that should throw invalid type errors.
     *
     * @return array Returns an array suitable to be used as a data provider.
     */
    public function provideInvalidTypes() {
        $result = [
            [['script', '-hw', '--enabled=13'], 'The value of --enabled (-e) is not a valid boolean.'],
            [['script', '-hw', '--enabled=foo'], 'The value of --enabled (-e) is not a valid boolean.'],
            [['script', '-hw', '--no-enabled', 'foo'], 'The value of --enabled (-e) is not a valid boolean.'],
            [['script', '-hw', '--count=foo'], 'The value of --count (-c) is not a valid integer.'],
            [['script', '-hw', '--no-count=22'], 'Cannot apply the --no- prefix on the non boolean --count.'],
            [['script', '-hw', '-c', 'foo'], 'The value of --count (-c) is not a valid integer.'],
        ];

        return $result;
    }

    /**
     * Test that the backwards compatibility of the format property works.
     */
    public function testFormatCompat() {
        $cli = new Cli();

        $format = @$cli->format;
        $format2 = !$format;
        @$cli->format = $format2;
        $this->assertSame($format2, $cli->getFormatOutput());

        $this->expectDeprecation();
        $format = $cli->format;
    }

    /**
     * Test array opts.
     *
     * @param array $argv The input command.
     * @param array $expectedOpts The expected opts after the command is parsed.
     * @dataProvider provideArrayOptTests
     */
    public function testArrayOpt(array $argv, array $expectedOpts): void {
        $cli = new Cli();

        $cli->opt('int:i', '', false, 'integer[]')
            ->opt('str:s', '', false, 'string[]')
            ->opt('bool:b', '', false, 'boolean[]');

        array_unshift($argv, 'script');

        $args = $cli->parse($argv);

        $this->assertSame($expectedOpts, $args->getOpts());
    }

    /**
     * Provide test data for array opt tests.
     *
     * @return array Returns a data provider array.
     */
    public function provideArrayOptTests(): array {
        $r = [
            [['-i123'], ['int' => [123]]],
            [['-shello'], ['str' => ['hello']]],
            [['-b'], ['bool' => [true]]],
            [['-i1', '-i2'], ['int' => [1, 2]]],
            [['-sa', '-sb'], ['str' => ['a', 'b']]],
            [['-b', '--no-bool'], ['bool' => [true, false]]],
            [['--no-bool', '--no-bool'], ['bool' => [false, false]]],
        ];

        $result = [];
        foreach ($r as $item) {
            $result[$item[0][0]] = $item;
        }


        return $result;
    }

    /**
     * Make sure that required args are checked.
     */
    public function testRequiredArgs(): void {
        $cli = new Cli();
        $cli->description('A cli with named args.')
            ->arg('from', 'The path from.')
            ->arg('to', 'The path to.', true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required arg: to');
        $args = $cli->parse(['script', '/var/foo.txt'], false);
    }
}
