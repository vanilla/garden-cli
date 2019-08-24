# Garden CLI

[![Build Status](https://img.shields.io/travis/vanilla/garden-cli.svg?style=flat)](https://travis-ci.org/vanilla/garden-cli)
[![Packagist Version](https://img.shields.io/packagist/v/vanilla/garden-cli.svg?style=flat)](https://packagist.org/packages/vanilla/garden-cli)
![MIT License](https://img.shields.io/packagist/l/vanilla/garden-cli.svg?style=flat)
[![CLA](https://cla-assistant.io/readme/badge/vanilla/garden-cli)](https://cla-assistant.io/vanilla/garden-cli)

Garden CLI is a PHP command line interface library meant to provide a full set of functionality with a clean and simple api.

## Why use Garden CLI?

PHP's `getopt()` provides little functionality and is prone to failure where one typo in your command line options can wreck and entire command call. Garden CLI solves this problem and provides additional functionality.

 * Your commands get automatic support for `--help` to print out help for your commands.
 * Support a single command or multiple commands. (ex. git pull, git push, etc.)
 * Have command options parsed and validated with error information automatically printed out.
 * A simple, elegant syntax so that even your most basic command line scripts will take little effort to implement robust parsing.

## Installation

*Garden CLI requires PHP 7.0 or higher*

Garden CLI is [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md) compliant and can be installed using [composer](//getcomposer.org). Just add `vanilla/garden-cli` to your composer.json.

```json
"require": {
    "vanilla/garden-cli": "~2.0"
}
```

## Basic Example

Here is a basic example of a command line script that uses Garden CLI to parse its options. Let's say you are writing a script called `dbdump.php` to dump some data from your database.

```php
<?php
// All of the command line classes are in the Garden\Cli namespace.
use Garden\Cli\Cli;

// Require composer's autoloader.
require_once 'vendor/autoload.php';

// Define the cli options.
$cli = new Cli();

$cli->description('Dump some information from your database.')
    ->opt('host:h', 'Connect to host.', true)
    ->opt('port:P', 'Port number to use.', false, 'integer')
    ->opt('user:u', 'User for login if not current user.', true)
    ->opt('password:p', 'Password to use when connecting to server.')
    ->opt('database:d', 'The name of the database to dump.', true);

// Parse and return cli args.
$args = $cli->parse($argv, true);
```

This example returns an `Args` object or exits to show help or an error message. Here are some things to note about the example.

* You can throw an exception instead of exiting by passing `false` as the second argument to `parse()`.
* The `opt()` method has the following parameters: `name`, `description`, `required`, and `type`. Most parameters have sensible defaults.
* If you want your option to have a short code then specify in with `name` argument separated by a colon.
* If you specify a short code for an option this will act like an alias for the parameter name in `$argv` only. You always access an option by its full name after parsing.

## Option Types

The `opt` method has a `$type` parameter that you can use to specify a type for the option. The valid types are `integer`, `string`, and `boolean` with string as the default.

You can also add `[]` after the type name to specify an array. To supply an array on the command line you specify the option multiple times like so:

```
command --header="line1" --header="line2"

```

## Displaying Help

If you were to call the basic example with a `--help` option then you'd see the following help printed:

<pre>
<b>usage: </b>dbdump.php [&lt;options&gt;]

Dump some information from your database.

<b>OPTIONS</b>
  <b>--database, -d</b>   The name of the database to dump.
  --help, -?       Display this help.
  <b>--host, -h</b>       Connect to host.
  --password, -p   Password to use when connecting to server.
  --port, -P       Port number to use.
  <b>--user, -u</b>       User for login if not current user.
</pre>

All of the options are printed in a compact table and required options are printed in bold. The table will automatically expand to accommodate longer option names and wrap if you provide extra long descriptions.

## Displaying Errors

Let's say you call the basic example with just `-P foo`. What you'd see is the following error message:

<pre>
The value of --port (-P) is not a valid integer.
Missing required option: database
Missing required option: user
</pre>

## Using the Parsed Options

Once you've successfully parsed the `$argv` using `Cli->parse($argv)` you can use the various methods on the returned `Args` object.

```php
$args = $cli->parse($argv);

$host = $args->getOpt('host', '127.0.0.1'); // get host with default 127.0.0.1
$user = $args->getOpt('user'); // get user
$database = $args['database']; // use the args like an array too
$port = $args->getOpt('port', 123); // get port with default 123
```

## Multiple Commands Example

Let's say you are writing a git-like command line utility called `nit.php` that pushes and pulls information from a remote repository.

```php
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
    ->opt('verbose:v', 'Output verbose information.', false, 'integer')
    ->arg('repo', 'The repository to sync with.', true);

$args = $cli->parse($argv);
```

Like the basic example, `parse()` will return a `Args` object on a successful parse. Here are some things to note about this example.

* The `Cli::create()` method is provided if you want to have a 100% fluent interface when defining your command schema.
* Call the `command()` method to define a new command.
* If you call `command('*')` then you can define options that are global to all commands.
* If the type of `opt()` is `integer` then you can count the number of times an option is supplied. In this example, this allowes you to specify multiple levels of verbosity by adding multiple `-v`s.
* The `arg()` method lets you define arguments that go after the options on the command line. More on this below.

## Listing Commands

Calling a script that has commands with no options or just the `--help` option will display a list of commands. Here is the output from the multiple commands example above.

<pre>
<b>usage: </b>nit.php <command> [&lt;options&gt;] [&lt;args&gt;]

<b>COMMANDS</b>
  push   Push data to a remote server.
  pull   Pull data from a remote server.
</pre>

## Args and Opts

The `Args` class differentiates between args and opts. There are methods to access both opts and args on that class.

* Opts are passed by `--name` full name or `-s` short code. They are named and can have types.
* Args are passed after the options as just strings separated by spaces.
* When calling a script from the command line you can use `--` to separate opts from args if there is ambiguity.

## Formatting Output with the TaskLogger

The `TaskLogger` is a [PSR-3](https://www.php-fig.org/psr/psr-3/) log decorator helps you output task-based information to the console in a nice, compact style. It's good for
things like install scripts, scripts that take a long time, or scripts you put into a cron job.

### Logging Tasks

When using the `TaskLogger` you want to think in terms of messages and tasks. A message is a single log item to output
to the user. A task has a begin and an end and can be nested as much as you want. Messages are output using the various PSR-3 methods while tasks are output with `begin()` and `end()`. Here are all of the methods you can use to log tasks.

| Method        | Notes |
| ------        | ----- |
| `begin`       | Log the beginning of a task. |
| `beginDebug`, `beginInfo`, `beginNotice`, `beginWarning`, `beginError`, `beginCritical`, `beginAlert`, `beginEmergency` | Log the beginning of a task with the given log level. |
| `end`         | Log the end of a task with the same level as it began. |
| `endError`    | Log the end of a task that resulted in an error. |
| `endHttpStatus`   | Log the end of a task with an HTTP status. The log level is calculated from the number of the status. |

### Task Nesting and Durations

You can nest tasks as much as you wish by calling a `begin*` method before calling an `end*` method. Each time you nest a task it will output its messages indented another level. Tasks also calculate their duration and output it at after the call to `end`.

### Suppressing Messages

By default, the `TaskLogger` will only output messages that are at a level of `LogLevel::INFO` or higher. You can change this with the `setMinLevel` method. If you begin a task at a level that us suppressed, but a child message is at or above the min level then the begin task message will be output retroactively. This allows you to see what task kicked off the logged message.

### Example

```php
$log = new TaskLogger();

$log->info('This is a message.')
    ->error('This is an error.') // outputs in red

    ->beginInfo('Begin a task')
    // code task code goes here...
    ->end('done.')

    ->beginDebug('Make an API call')
    ->endHttpStatus(200) // treated as error or success depending on code

    ->begin(LogLevel::NOTICE, 'Multi-step task')
    ->info('Step 1')
    ->info('Step 2')
    ->beginDebug('Step 3')
    ->debug('Step 3.1') // steps will be hidden because they are level 3
    ->debug('Step 3.2')
    ->end('done.')
    ->end('done.');
```

## The StreamLogger

If you create and use a `TaskLogger` object it will output nicely to the console out of the box. Under the hood it is using a `StreamLogger` object to handle the formatting of the tasks to an output stream, in this case stdout. You can replace or modify the `StreamLogger` if you want to control logging in a more granular level. Here are some options.

| Method                | Default   | Notes |
| ------                | -------   | ----- |
| `setLineFormat`       | `'[{time}] {message}'`    | Set the format of lines. Use the `{level}`, `{time}`, `{message}` strings to move the components around. |
| `setColorizeOutput`   | automatic | Whether or not to use console colors. |
| `setBufferBegins`     | `true`    | Attempt to put task begin/end messages on the same line. Turn this off if you plan on writing to the log concurrently. |
| `setTimeFormat`       | `'%F %T'` | Set the time format. This can be a `strftime` string or a callback. |
| `setLevelFormat`      | nothing   | Set a callback to format a `LogLevel` constant. |

### Example

The following example creates a `StreamLogger` object and tweaks some of its settings before passing it into the `TaskLogger` constructor.

```php
$fmt = new StreamLogger(STDOUT);

$fmt->setLineFormat('{level}: {time} {message}');

$fmt->setLevelFormat('strtoupper');

$fmt->setTimeFormat(function ($ts) {
    return number_format(time() - $ts).' seconds ago';
});

$log = new TaskLogger($fmt);
```

## Implementing Your Own Logger

You can give the `TaskLogger` any PSR-3 compliant logger and it will send its output to it. In order to use some of the special task functionality, you'll have to inspect the `$contenxt` argument of your `log` method. Here the fields that you may receive.

| Field                         | Type  | Notes |
| -----                         | ----  | ----- |
| `TaskLogger::FIELD_TIME`      | `int` | The timestamp of the message. |
| `TaskLogger::FIELD_INDENT`    | `int` | The indent level of the message. |
| `TaskLogger::FIELD_BEGIN`     | `bool` | True if the message denotes the beginning of a task. |
| `TaskLogger::FIELD_END`       | `bool` | True if the message denotes the end of a task. |
| `TaskLogger::FIELD_DURATION`  | `float` | The duration of a task in seconds and milliseconds. |
