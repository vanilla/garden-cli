Garden CLI
==========

Garden CLI is a PHP command line interface library meant to provide a full set of functionality with a clean and simple api.

Why use Garden CLI?
-------------------

Php's `getopt()` provides little functionality and is prone to failure where one typo in your command line options can wreck and entire command call. Garden CLI solves this problem and provides additional functionality.

 * You commands get automatic support for `--help` to print out help for your commands.
 * Support a single command or multiple commands. (ex. git pull, git push, etc.)
 * Have command options parsed and validated with error information automatically printed out.
 * A simple, elegant syntax so that even your most basic command line scripts will take little effort to implement robust parsing.

Installation
------------

*Garden CLI requres PHP 5.4 or higher*

Garden CLI is [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md) compliant and can be installed using [composer](//getcomposer.org). Just add `vanilla/garden-cli` to your composer.json.

```json
"require": {
    "vanilla/garden-cli": "*"
}
```

Basic Example
-------------

Here is a basic example of a command line script the uses Garden CLI to parse its options. Let's say you are writing a script called `dbdump.php` to dump some data from your database.

```php
<?php
// Require composer's autoloader.
require_once 'vendor/autoload.php';

// Define the cli options.
$cli = new Cli();

$cli->description('Dump some information from your database.')
    ->opt('host', 'Connect to host.', true, 'string', 'h')
    ->opt('port', 'Port number to use.', false, 'integer', 'P')
    ->opt('user', 'User for login if not current user.', true, 'string', 'u')
    ->opt('password', 'Password to use when connecting to server.', false, 'string', 'p')
    ->opt('database', 'The name of the database to dump.', true, 'string', 'd');

// Parse and return cli args.
$args = $cli->parse($argv, true);
```

This example returns a `Garden\Cli\Args` object or exits to show help or an error message. Here are some things to note about the exmple.

* You can throw an exception instead of exiting by passing `false` as the second argument to `parse()`.
* The `opt()` method has the following parameters: `name`, `description`, `required`, `type`, and `shortcode`. Most parameters have sensible defaults.
* If you specify a short code for an option this will act like an alias for the parameter name in `$argv` only. You always access an option by its full name after parsing.

Displaying Help
---------------

If you were to call the basic example with a `--help` option then you'd see the following help printed:

<pre>
usage: dbdump.php [&lt;options&gt;]

Dump some information from your database.

<b>OPTIONS</b>
  <b>--database, -d</b>   The name of the database to dump.
  --host, -h       Connect to host.
  --password, -p   Password to use when connecting to server.
  --port, -P       Port number to use.
  <b>--user, -u</b>       User for login if not current user.
</pre>

All of the options are printed in a compact table and required options are printed in bold. The table will automatically expand to accommodate longer option names and wrap if you provide extra long descriptions.

Displaying Errors
-----------------

Let's say you call the basic example with just `-P foo` which is an error and some required options are missing. What you'd see is the following error:

<pre>
The value of --port (-P) is not a valid integer.
Missing required option: database
Missing required option: user
</pre>

Using the Parsed Options
------------------------

Once you've successfully parsed the `$argv` using `Cli->parse($argv)` you can use the various methods on the returned `Args` object.

```php
$args = $cli->parse($argv);

$host = $args->getOpt('host', '127.0.0.1'); // get host with default 127.0.0.1
$user = $args->getOpt('user'); // get user
$database = $args['database']; // use the args like an array too
$port = $args->getOpt('port', 123); // get port with default 123
```
