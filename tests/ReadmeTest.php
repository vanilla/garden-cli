<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */
namespace Garden\Cli\Tests;

use Garden\Cli\Cli;

/**
 * Provides tests for the sample code in README.md.
 */
class ReadmeTest extends AbstractCliTest {

    /**
     * Test the basic example help.
     */
    public function testBasicHelp() {
        $cli = $this->getBasicCli();
        $argv = ['dbdump.php', '--help'];

        // Parse and return cli args.
        $this->expectException(\Exception::class);
        $cli->parse($argv, false);
    }

    /**
     * Test the basic example error output.
     */
    public function testBasicError() {
        $cli = $this->getBasicCli();
        $argv = ['dbdump.php', '-P', 'foo'];

        $this->expectException(\Exception::class);
        $cli->parse($argv, false);
    }

    /**
     * Test the basic example args.
     */
    public function testBasicArgs() {
        $cli = $this->getBasicCli();
        $argv = ['dbdump.php', '-hlocalhost', '-uroot', '--database=testdb'];

        $args = $cli->parse($argv, false);

        $host = $args->getOpt('host', '127.0.0.1'); // get host with default 127.0.0.1
        $user = $args->getOpt('user'); // get user
        $database = $args['database']; // use the args like an array too
        $port = $args->getOpt('port', 123); // get port with default 123

        $this->assertEquals('localhost', $host);
        $this->assertEquals('root', $user);
        $this->assertEquals('testdb', $database);
        $this->assertEquals(123, $port);
    }

    /**
     * Test the command usage output.
     */
    public function testCommandsUsage() {
        $cli = $this->getCommandCli();

        $this->expectException(\Exception::class);
        $cli->parse(['nit.php', '--help'], false);
    }

    /**
     * Test the help output for a multiple command argument.
     */
    public function testCommandsHelp() {
        $cli = $this->getCommandCli();

        $this->expectException(\Exception::class);
        $cli->parse(['nit.php', 'push', '-?'], false);
    }

    /**
     * Get the basic cli example.
     *
     * @return Cli
     */
    public function getBasicCli() {
        // Define the cli options.
        $cli = new Cli();

        $cli->description('Dump some information from your database.')
            ->opt('host:h', 'Connect to host.', false, 'string')
            ->opt('port:P', 'Port number to use.', false, 'integer')
            ->opt('user:u', 'User for login if not current user.', true, 'string')
            ->opt('password:p', 'Password to use when connecting to server.', false, 'string')
            ->opt('database:d', 'The name of the database to dump.', true, 'string');

        return $cli;
    }

    /**
     * Get the multiple command cli example.
     *
     * @return Cli
     */
    public function getCommandCli() {
        // Define a cli with commands.
        $cli = Cli::create()
            // Define the first command: push.
            ->command('push')
            ->description('Push data to a remote server.')
            ->opt('force:f', 'Force an overwrite.', false, 'boolean')
            ->opt('set-upstream:u', 'Add a reference to the upstream repo.', false, 'boolean')
            // Define the second command: pull.
            ->command('pull')
            ->description('Pull data from a remote server.')
            ->opt('commit', 'Perform the merge and commit the result.', false, 'boolean')
            // Set some global options.
            ->command('*')
            ->opt('verbose:v', 'Output verbose information.', false, 'boolean')
            ->arg('repo', 'The repository to sync with.', true);

        return $cli;
    }
}
