<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

use Garden\Cli\Schema\CommandSchema;
use Garden\Cli\Schema\OptSchema;

/**
 * A general purpose command line parser.
 */
class Cli {
    /// Constants ///

    const META = '__meta';
    const ARGS = '__args';

    const COMMAND_ARGS_NONE = 0;
    const COMMAND_ARGS_OPTIONAL = 1;
    const COMMAND_ARGS_REQUIRED = 2;

    const TYPE_INTEGER = 'integer';
    const TYPE_STRING = 'string';
    const TYPE_BOOLEAN = 'boolean';

    /// Properties ///
    /**
     * @var CommandSchema[] All of the schemas, indexed by command pattern.
     */
    protected $commandSchemas;

    /**
     * @var CommandSchema A pointer to the current schema.
     */
    protected $currentSchema;

    /**
     * @var bool Whether or not to format output with console codes.
     */
    protected $formatOutput;

    protected static $types = [
        'i' => self::TYPE_INTEGER,
        's' => self::TYPE_STRING,
        'b' => self::TYPE_BOOLEAN,
    ];


    /// Methods ///

    /**
     * Creates a {@link Cli} instance representing a command line parser for a given schema.
     */
    public function __construct() {
        $this->commandSchemas = ['*' => new CommandSchema()];

        // Select the current schema.
        $this->currentSchema = $this->commandSchemas['*'];

        $this->formatOutput = static::guessFormatOutput();
    }

    /**
     * Backwards compatibility for the **format** property.
     *
     * @param string $name Must be **format**.
     * @return bool|null Returns {@link getFormatOutput()} or null if {@link $name} isn't **format**.
     */
    public function __get($name) {
        if ($name === 'format') {
            trigger_error("Cli->format is deprecated. Use Cli->getFormatOutput() instead.", E_USER_DEPRECATED);
            return $this->getFormatOutput();
        }
        return null;
    }

    /**
     * Backwards compatibility for the **format** property.
     *
     * @param string $name Must be **format**.
     * @param bool $value One of **true** or **false**.
     */
    public function __set($name, $value) {
        if ($name === 'format') {
            trigger_error("Cli->format is deprecated. Use Cli->setFormatOutput() instead.", E_USER_DEPRECATED);
            $this->setFormatOutput($value);
        }
    }

    /**
     * Get whether or not output should be formatted.
     *
     * @return boolean Returns **true** if output should be formatted or **false** otherwise.
     */
    public function getFormatOutput() {
        return $this->formatOutput;
    }

    /**
     * Set whether or not output should be formatted.
     *
     * @param boolean $formatOutput Whether or not to format output.
     * @return $this
     */
    public function setFormatOutput($formatOutput) {
        $this->formatOutput = $formatOutput;
        return $this;
    }

    /**
     * Add an argument to an {@link Args} object, checking for a correct name.
     *
     * @param CommandSchema $schema The schema for the args.
     * @param Args $args The args object to add the argument to.
     * @param mixed $arg The value of the argument.
     *
     * @return void
     */
    private function addArg(CommandSchema $schema, Args $args, $arg): void {
        $argsCount = count($args->getArgs());
        $schemaArgs = array_keys($schema->getArgs());
        $name = isset($schemaArgs[$argsCount]) ? $schemaArgs[$argsCount] : $argsCount;

        $args->addArg($arg, $name);
    }

    /**
     * Construct and return a new {@link Cli} object.
     *
     * This method is mainly here so that an entire cli schema can be created and defined with fluent method calls.
     *
     * @param array $args The constructor arguments, if any.
     * @return static Returns a new Cli object.
     */
    public static function create(...$args) {
        /** @psalm-suppress TooManyArguments **/
        return new static(...$args);
    }

    /**
     * Breaks a cell into several lines according to a given width.
     *
     * @param string $text The text of the cell.
     * @param int $width The width of the cell.
     * @param bool $addSpaces Whether or not to right-pad the cell with spaces.
     * @return string[] Returns an array of strings representing the lines in the cell.
     *
     * @psalm-return array<int, string>
     */
    public static function breakLines($text, $width, $addSpaces = true): array {
        $rawLines = explode("\n", $text);
        $lines = [];

        foreach ($rawLines as $line) {
            // Check to see if the line needs to be broken.
            $sublines = static::breakString($line, $width, $addSpaces);
            $lines = array_merge($lines, $sublines);
        }

        return $lines;
    }

    /**
     * Breaks a line of text according to a given width.
     *
     * @param string $line The text of the line.
     * @param int $width The width of the cell.
     * @param bool $addSpaces Whether or not to right pad the lines with spaces.
     * @return string[] Returns an array of lines broken on word boundaries.
     *
     * @psalm-return array<int, string>
     */
    protected static function breakString(string $line, int $width, bool $addSpaces = true): array {
        $words = explode(' ', $line);
        $result = [];

        $line = '';
        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);

            // Check for a new line.
            if (strlen($candidate) > $width) {
                if ($line === '') {
                    // The word is longer than a line.
                    if ($addSpaces) {
                        $result[] = (string)substr($candidate, 0, $width);
                    } else {
                        $result[] = $candidate;
                    }
                } else {
                    if ($addSpaces) {
                        $line .= str_repeat(' ', $width - strlen($line));
                    }

                    // Start a new line.
                    $result[] = $line;
                    $line = $word;
                }
            } else {
                $line = $candidate;
            }
        }

        // Add the remaining line.
        if ($line) {
            if ($addSpaces) {
                $line .= str_repeat(' ', $width - strlen($line));
            }

            // Start a new line.
            $result[] = $line;
        }

        return $result;
    }

    /**
     * Sets the description for the current command.
     *
     * @param string|null $str The description for the current schema or null to get the current description.
     * @return $this
     */
    public function description($str = null) {
        return $this->meta('description', $str);
    }

    /**
     * Determines whether or not the schema has a command.
     *
     * @param string $name Check for the specific command name.
     * @return bool Returns true if the schema has a command.
     */
    public function hasCommand($name = '') {
        if ($name) {
            return array_key_exists($name, $this->commandSchemas);
        } else {
            foreach ($this->commandSchemas as $pattern => $opts) {
                if (strpos($pattern, '*') === false) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Determines whether a command has options.
     *
     * @param string $command The name of the command or an empty string for any command.
     * @return bool Returns true if the command has options. False otherwise.
     */
    public function hasOptions($command = '') {
        if ($command) {
            $def = $this->getSchema($command);
            return $def->hasOpts();
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                if ($def->hasOpts()) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Determines whether or not a command has args.
     *
     * @param string $command The command name to check.
     * @return int Returns one of the following `COMMAND_ARGS_*` constants.
     */
    public function hasArgs($command = ''): int {
        $args = null;

        if ($command) {
            // Check to see if the specific command has args.
            $def = $this->getSchema($command);
            $args = $def->getArgs();
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                $args = $def->getArgs();
            }
            if (!empty($args)) {
                return self::COMMAND_ARGS_OPTIONAL;
            }
        }

        if (empty($args)) {
            return self::COMMAND_ARGS_NONE;
        }

        foreach ($args as $arg) {
            if (!Cli::val('required', $arg)) {
                return self::COMMAND_ARGS_OPTIONAL;
            }
        }
        return self::COMMAND_ARGS_REQUIRED;
    }

    /**
     * Finds our whether a pattern is a command.
     *
     * @param string $pattern The pattern being evaluated.
     * @return bool Returns `true` if `$pattern` is a command, `false` otherwise.
     */
    public static function isCommand($pattern) {
        return strpos($pattern, '*') === false;
    }

    /**
     * Parses and validates a set of command line arguments the schema.
     *
     *
     *
     * Note that the `$argv` array must have at least one element and it must represent the path to the command that
     * invoked the command. This is used to write usage information.
     *
     * @param array|null $argv Command line arguments compatible with the global `$argv` variable.
     * @param bool $exit Whether to exit the application when there is an error or when writing help.
     *
     * @return Args Returns an {@see Args} instance when a command should be executed or `null` when one should not be executed.
     *
     * @throws \Exception Throws an exception when {@link $exit} is false and the help or errors need to be displayed.
     */
    public function parse($argv = null, $exit = true): Args {
        $formatOutputBak = $this->formatOutput;
        // Only format commands if we are exiting.
        if (!$exit) {
            $this->formatOutput = false;
        }
        if (!$exit) {
            ob_start();
        }

        $args = $this->parseRaw($argv);

        $hasCommand = $this->hasCommand();

        if ($hasCommand && !$args->getCommand()) {
            // If no command is given then write a list of commands.
            $this->writeUsage($args);
            $this->writeCommands();
            $result = null;
        } elseif ($args->getOpt('help') || $args->getOpt('?')) {
            // Write the help.
            $this->writeUsage($args);
            $this->writeHelp($args->getCommand());
            $result = null;
        } else {
            // Validate the arguments against the schema.
            $validArgs = $this->validate($args);
            $result = $validArgs;
        }
        if (!$exit) {
            $this->formatOutput = $formatOutputBak;
            $output = ob_get_clean();
            if ($result === null) {
                throw new \InvalidArgumentException(trim($output));
            }
        } elseif ($result === null) {
            exit();
        }
        return $result;
    }

    /**
     * Parse an array of arguments.
     *
     * If the first item in the array is in the form of a command (no preceding - or --),
     * 'command' is filled with its value.
     *
     * @param array|null $argv An array of arguments passed in a form compatible with the global `$argv` variable.
     * @return Args Returns the raw parsed arguments.
     * @throws \Exception Throws an exception when {@see $argv} isn't an array.
     */
    protected function parseRaw($argv = null) {
        if ($argv === null) {
            $argv = $GLOBALS['argv'];
        }

        if (!is_array($argv)) {
            throw new \Exception(__METHOD__ . " expects an array", 400);
        }

        $path = array_shift($argv);
        $hasCommand = $this->hasCommand();

        $parsed = new Args();
        $parsed->setMeta('path', $path);
        $parsed->setMeta('filename', basename($path));

        if (count($argv)) {
            // Get possible command.
            if (substr($argv[0], 0, 1) != '-') {
                $arg0 = array_shift($argv);
                if ($hasCommand) {
                    $parsed->setCommand($arg0);
                } else {
                    $schema = $this->getSchema($parsed->getCommand());
                    $this->addArg($schema, $parsed, $arg0);
                }
            }
            // Get the data types for all of the commands.
            if (!isset($schema)) {
                $schema = $this->getSchema($parsed->getCommand());
            }

            $types = [];
            foreach ($schema->getOpts() as $sName => $sRow) {
                $type = $sRow->getType();
                $types[$sName] = $type;
                if ($sRow->getShortName()) {
                    $types[$sRow->getShortName()] = $type;
                }
            }

            // Parse opts.
            for ($i = 0; $i < count($argv); $i++) {
                $str = $argv[$i];

                // Parse the help command as a boolean since it is not part of the schema!
                if (in_array($str, ['-?', '--help'])) {
                    $parsed->setOpt('help', true);
                    continue;
                }

                if ($str === '--') {
                    // --
                    $i++;
                    break;
                } elseif (strlen($str) > 2 && substr($str, 0, 2) == '--') {
                    // --foo
                    $str = substr($str, 2);
                    $parts = explode('=', $str);
                    $key = $parts[0];
                    $v = '';

                    // Has a =, so pick the second piece
                    if (count($parts) == 2) {
                        $v = $parts[1];
                    // Does not have an =
                    } else {
                        // If there is a value (even if there are no equals)
                        if (isset($argv[$i + 1]) && preg_match('/^--?.+/', $argv[$i + 1]) == 0) {
                            // so choose the next arg as its value if any,
                            $v = $argv[$i + 1];
                            // If this is a boolean we need to coerce the value
                            if (Cli::val($key, $types) === self::TYPE_BOOLEAN) {
                                if (in_array($v, ['0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'])) {
                                    // The next arg looks like a boolean to me.
                                    $i++;
                                } else {
                                    // Next arg is not a boolean: set the flag on, and use next arg in its own iteration
                                    $v = true;
                                }
                            } else {
                                $i++;
                            }
                        // If there is no value but we have a no- before the command
                        } elseif (strpos($key, 'no-') === 0) {
                            $tmpKey = str_replace('no-', '', $key);
                            if (Cli::val($tmpKey, $types) === self::TYPE_BOOLEAN) {
                                $key = $tmpKey;
                                $v = false;
                            }
                        } elseif (Cli::val($key, $types) === self::TYPE_BOOLEAN) {
                            $v = true;
                        }
                    }
                    $this->pushOpt($parsed, $key, $v);
                } elseif (strlen($str) == 2 && $str[0] == '-') {
                    // -a

                    $key = $str[1];
                    $type = Cli::val($key, $types, self::TYPE_BOOLEAN);
                    $v = null;

                    if (isset($argv[$i + 1])) {
                        // Try and be smart about the next arg.
                        $nextArg = $argv[$i + 1];

                        if ($type === self::TYPE_BOOLEAN) {
                            if ($this->isStrictBoolean($nextArg)) {
                                // The next arg looks like a boolean to me.
                                $v = $nextArg;
                                $i++;
                            } else {
                                $v = true;
                            }
                        } elseif (!preg_match('/^--?.+/', $argv[$i + 1])) {
                            // The next arg is not an opt.
                            $v = $nextArg;
                            $i++;
                        } else {
                            // The next arg is another opt.
                            $v = null;
                        }
                    }

                    if ($v === null) {
                        $v = Cli::val($type, [self::TYPE_BOOLEAN => true, self::TYPE_INTEGER => 1, self::TYPE_STRING => '']);
                    }

                    $this->pushOpt($parsed, $key, $v);
                } elseif (strlen($str) > 1 && $str[0] == '-') {
                    // -abcdef
                    for ($j = 1; $j < strlen($str); $j++) {
                        $opt = $str[$j];
                        $remaining = substr($str, $j + 1);
                        $type = Cli::val($opt, $types, self::TYPE_BOOLEAN);

                        // Check for an explicit equals sign.
                        if (substr($remaining, 0, 1) === '=') {
                            $remaining = substr($remaining, 1);
                            if ($type === self::TYPE_BOOLEAN) {
                                // Bypass the boolean flag checking below.
                                $this->pushOpt($parsed, $opt, $remaining);
                                break;
                            }
                        }

                        if ($type === self::TYPE_BOOLEAN) {
                            if (preg_match('`^([01])`', $remaining, $matches)) {
                                // Treat the 0 or 1 as a true or false.
                                $this->pushOpt($parsed, $opt, $matches[1]);
                                $j += strlen($matches[1]);
                            } else {
                                // Treat the option as a flag.
                                $this->pushOpt($parsed, $opt, true);
                            }
                        } elseif ($type === self::TYPE_STRING) {
                            // Treat the option as a set with no = sign.
                            $this->pushOpt($parsed, $opt, $remaining);
                            break;
                        } elseif ($type === self::TYPE_INTEGER) {
                            if (preg_match('`^(\d+)`', $remaining, $matches)) {
                                // Treat the option as a set with no = sign.
                                $this->pushOpt($parsed, $opt, $matches[1]);
                                $j += strlen($matches[1]);
                            } else {
                                // Treat the option as either multiple flags.
                                $optVal = $parsed->getOpt($opt, 0);
                                $parsed->setOpt($opt, $optVal + 1);
                            }
                        } else {
                            // This should not happen unless we've put a bug in our code.
                            throw new \Exception("Invalid type $type for $opt.", 500);
                        }
                    }
                } else {
                    // End of opts
                    break;
                }
            }

            // Grab the remaining args.
            for (; $i < count($argv); $i++) {
                $this->addArg($schema, $parsed, $argv[$i]);
            }
        }

        return $parsed;
    }

    /**
     * Validates arguments against the schema.
     *
     * @param Args $args The arguments that were returned from {@link Cli::parseRaw()}.
     * @return Args|null
     * @throws \Exception Throws an exception when validation fails and exceptions are configured.
     */
    public function validate(Args $args) {
        $isValid = true;
        $command = $args->getCommand();
        $valid = new Args($command);
        $schema = $this->getSchema($command);
        $opts = $args->getOpts();
        $missing = [];

        // Check to see if the command is correct.
        if ($command && !$this->hasCommand($command) && $this->hasCommand()) {
            echo $this->red("Invalid command: $command.".PHP_EOL);
            $isValid = false;
        }

        // Add the args.
        $valid->setArgs($args->getArgs());

        foreach ($schema->getOpts() as $key => $definition) {
            // No Parameter (default)
            $type = $definition->getType();

            $value = $this->extractOpt($opts, $key, $definition);

            if ($value !== null) {
                if ($this->validateType($value, $type, $key, $definition)) {
                    $this->mergeOpt($valid, $key, $value);
                } else {
                    $isValid = false;
                }
            } elseif ($definition->isRequired()) {
                // The key was not supplied. Is it required?
                $missing[$key] = true;
                $valid->setOpt($key, false);
            }
        }

        if (count($missing)) {
            $isValid = false;
            foreach ($missing as $key => $v) {
                echo $this->red("Missing required option: $key".PHP_EOL);
            }
        }

        foreach ($schema->getArgs() as $name => $arg) {
            if (!empty($arg['required']) && !$valid->hasArg($name)) {
                $isValid = false;
                echo $this->red("Missing required arg: $name".PHP_EOL);
            }
        }

        if (count($opts)) {
            $isValid = false;
            foreach ($opts as $key => $v) {
                echo $this->red("Invalid option: $key".PHP_EOL);
            }
        }

        if ($isValid) {
            return $valid;
        } else {
            echo PHP_EOL;
            return null;
        }
    }

    /**
     * Gets the full cli schema.
     *
     * @param string $command The name of the command. This can be left blank if there is no command.
     * @return CommandSchema Returns the schema that matches the command.
     */
    public function getSchema($command = ''): CommandSchema {
        $matches = [];
        foreach ($this->commandSchemas as $pattern => $schema) {
            if (fnmatch($pattern, $command)) {
                $matches[] = $schema;
            }
        }
        if (empty($matches)) {
            return new CommandSchema();
        } elseif (count($matches) === 1) {
            return $matches[0];
        } else {
            $r = new CommandSchema();
            foreach ($matches as $schema) {
                $r->mergeSchema($schema);
            }
            return $r;
        }
    }

    /**
     * Gets/sets the value for a current meta item.
     *
     * @param string $name The name of the meta key.
     * @param mixed $value Set a new value for the meta key.
     * @return $this|mixed Returns the current value of the meta item or `$this` for fluent setting.
     */
    public function meta($name, $value = null) {
        if ($value !== null) {
            $this->currentSchema->setMeta($name, $value);
            return $this;
        }
        return $this->currentSchema->getMeta($name, null);
    }

    /**
     * Adds an option (opt) to the current schema.
     *
     * @param string $name The long name(s) of the parameter.
     * You can use either just one name or a string in the form 'long:short' to specify the long and short name.
     * @param string $description A human-readable description for the column.
     * @param bool $required Whether or not the opt is required.
     * @param string $type The type of parameter. This must be one of string, bool, integer.
     * @param array $meta Additional information to attach to the opt.
     * @return $this
     */
    public function opt($name, $description, $required = false, $type = 'string', array $meta = []) {
        $opt = new OptSchema($name, $description, $required, $type, $meta);
        $this->currentSchema->addOpt($opt);
        return $this;
    }

    /**
     * Add an opt to the current schema.
     *
     * @param OptSchema $opt
     * @return $this
     */
    public function addOpt(OptSchema $opt): self {
        $this->currentSchema->addOpt($opt);
        return $this;
    }

    /**
     * Define an arg on the current command.
     *
     * @param string $name The name of the arg.
     * @param string $description The arg description.
     * @param bool $required Whether or not the arg is required.
     * @return $this
     */
    public function arg($name, $description, $required = false) {
        $this->currentSchema->addMeta(
            Cli::ARGS,
            $name,
            ['description' => $description, 'required' => $required]
        );
        return $this;
    }

    /**
     * Selects the current command schema name.
     *
     * @param string $pattern The command pattern.
     * @return $this
     */
    public function command($pattern) {
        if (!isset($this->commandSchemas[$pattern])) {
            $this->commandSchemas[$pattern] = new CommandSchema();
        }
        $this->currentSchema = $this->commandSchemas[$pattern];

        return $this;
    }

    /**
     * Determine weather or not a value can be represented as a boolean.
     *
     * This method is sort of like {@link Cli::validateType()} but requires a more strict check of a boolean value.
     *
     * @param mixed $value The value to test.
     * @param bool|null $boolValue Set the boolean value of the value being checked.
     * @return bool Returns **true** if the value is boolean or **false** otherwise.
     */
    protected function isStrictBoolean($value, &$boolValue = null) {
        if ($value === true || $value === false) {
            $boolValue = $value;
            return true;
        } elseif (in_array($value, ['0', 'false', 'off', 'no'])) {
            $boolValue = false;
            return true;
        } elseif (in_array($value, ['1', 'true', 'on', 'yes'])) {
            $boolValue = true;
            return true;
        } else {
            $boolValue = null;
            return false;
        }
    }

    /**
     * Bold some text.
     *
     * @param string $text The text to format.
     * @return string Returns the text surrounded by formatting commands.
     */
    public function bold($text) {
        return $this->formatString($text, ["\033[1m", "\033[0m"]);
    }

    /**
     * Bold some text.
     *
     * @param string $text The text to format.
     * @return string Returns the text surrounded by formatting commands.
     */
    public static function boldText($text) {
        return "\033[1m{$text}\033[0m";
    }

    /**
     * Make some text red.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function red($text) {
        return $this->formatString($text, ["\033[1;31m", "\033[0m"]);
    }

    /**
     * Make some text red.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public static function redText($text) {
        return "\033[1;31m{$text}\033[0m";
    }

    /**
     * Make some text green.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function green($text) {
        return $this->formatString($text, ["\033[1;32m", "\033[0m"]);
    }

    /**
     * Make some text green.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public static function greenText($text) {
        return "\033[1;32m{$text}\033[0m";
    }

    /**
     * Make some text blue.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function blue($text) {
        return $this->formatString($text, ["\033[1;34m", "\033[0m"]);
    }

    /**
     * Make some text blue.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public static function blueText($text) {
        return "\033[1;34m{$text}\033[0m";
    }

    /**
     * Make some text purple.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function purple($text) {
        return $this->formatString($text, ["\033[0;35m", "\033[0m"]);
    }

    /**
     * Make some text purple.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public static function purpleText($text) {
        return "\033[0;35m{$text}\033[0m";
    }

    /**
     * Format some text for the console.
     *
     * @param string $text The text to format.
     * @param string[] $wrap The format to wrap in the form ['before', 'after'].
     * @return string Returns the string formatted according to {@link Cli::$format}.
     */
    protected function formatString($text, array $wrap) {
        if ($this->formatOutput) {
            return "{$wrap[0]}$text{$wrap[1]}";
        } else {
            return $text;
        }
    }

    /**
     * Guess whether or not to format the output with colors.
     *
     * If the current environment is being redirected to a file then output should not be formatted. Also, Windows
     * machines do not support terminal colors so formatting should be suppressed on them too.
     *
     * @param mixed $stream The stream to interrogate for output format support.
     * @return bool Returns **true** if the output can be formatter or **false** otherwise.
     */
    public static function guessFormatOutput($stream = STDOUT) {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            return false;
        } elseif (function_exists('posix_isatty')) {
            try {
                return @posix_isatty($stream);
            } catch (\Throwable $ex) {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Sleep for a number of seconds, echoing out a dot on each second.
     *
     * @param int $seconds The number of seconds to sleep.
     *
     * @return void
     */
    public static function sleep($seconds): void {
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            echo '.';
        }
    }

    /**
     * Validate an opt to make sure it matches the type defined in its schema.
     *
     * @param mixed $value The value to validate. Will also convert the value to the proper type.
     * @param string $type The type to validate against.
     * @param string $name The name of the opt for an error message.
     * @param OptSchema|null $def The schema for the opt to aid error messages.
     * @return bool
     */
    protected function validateType(&$value, $type, $name = '', OptSchema $def = null): bool {
        if ($def !== null && $def->isArray()) {
            $value = (array)$value;
            $r = true;
            foreach ($value as $i => &$item) {
                $r = $r && $this->validateScalarType($item, $type, "{$name}[$i]", $def);
            }
        } else {
            if (is_array($value)) {
                $value = array_pop($value);
            }
            $r = $this->validateScalarType($value, $type, $name, $def);
        }

        return $r;
    }

    /**
     * Validate the type of a value and coerce it into the proper type.
     *
     * @param mixed $value The value to validate.
     * @param string $type One of: bool, int, string.
     * @param string $name The name of the option if you want to print an error message.
     * @param OptSchema|null $def The option def if you want to print an error message.
     * @return bool Returns `true` if the value is the correct type.
     * @throws \Exception Throws an exception when {@see $type} is not a known value.
     */
    private function validateScalarType(&$value, $type, $name = '', ?OptSchema $def = null): bool {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                if (is_bool($value)) {
                    $valid = true;
                } elseif (in_array($value, [null, '', 0, '0', 'false', 'no', 'disabled'], true)) {
                    $value = false;
                    $valid = true;
                } elseif (in_array($value, [1, '1', 'true', 'yes', 'enabled'], true)) {
                    $value = true;
                    $valid = true;
                } else {
                    $valid = false;
                }
                break;
            case self::TYPE_INTEGER:
                if (is_numeric($value)) {
                    $value = (int)$value;
                    $valid = true;
                } else {
                    $valid = false;
                }
                break;
            case self::TYPE_STRING:
                $value = (string)$value;
                $valid = true;
                break;
            default:
                throw new \Exception("Unknown type: $type.", 400);
        }

        if (!$valid && $name) {
            $short = $def !== null ? $def->getShortName() : '';
            $nameStr = "--$name".($short ? " (-$short)" : '');
            echo $this->red("The value of $nameStr is not a valid $type.".PHP_EOL);
        }

        return $valid;
    }

    /**
     * Writes a lis of all of the commands.
     *
     * @return void
     */
    protected function writeCommands(): void {
        echo static::bold("COMMANDS").PHP_EOL;

        $table = new Table();
        foreach ($this->commandSchemas as $pattern => $schema) {
            if (static::isCommand($pattern)) {
                $table
                    ->row()
                    ->cell($pattern)
                    ->cell($schema->getDescription());
            }
        }
        $table->write();
    }

    /**
     * Writes the cli help.
     *
     * @param string $command The name of the command or blank if there is no command.
     *
     * @return void
     */
    public function writeHelp($command = ''): void {
        $schema = $this->getSchema($command);
        $this->writeSchemaHelp($schema);
    }

    /**
     * Writes the help for a given schema.
     *
     * @param CommandSchema $schema A command line scheme returned from {@see Cli::getSchema()}.
     *
     * @return void
     */
    protected function writeSchemaHelp(CommandSchema $schema): void {
        // Write the command description.
        $description = $schema->getDescription();

        if ($description) {
            echo implode("\n", Cli::breakLines($description, 80, false)).PHP_EOL.PHP_EOL;
        }

        // Add the help.
        $schema->addOpt(new OptSchema('help:?', 'Display this help.', false, self::TYPE_BOOLEAN));

        echo Cli::bold('OPTIONS').PHP_EOL;

        $table = new Table();
        $table->setFormatOutput($this->formatOutput);

        $opts = $schema->getOpts();
        ksort($opts);
        foreach ($opts as $key => $definition) {
            $table->row();

            // Write the keys.
            $keys = "--{$key}";
            if ($shortKey = $definition->getShortName()) {
                $keys .= ", -$shortKey";
            }
            if ($definition->isRequired()) {
                $table->bold($keys);
            } else {
                $table->cell($keys);
            }

            // Write the description.
            $table->cell($definition->getDescription());
        }

        $table->write();
        echo PHP_EOL;

        $args = $schema->getArgs();
        if (!empty($args)) {
            echo Cli::bold('ARGUMENTS').PHP_EOL;

            $table = new Table();
            $table->setFormatOutput($this->formatOutput);

            foreach ($args as $argName => $arg) {
                $table->row();

                if (Cli::val('required', $arg)) {
                    $table->bold($argName);
                } else {
                    $table->cell($argName);
                }

                $table->cell(Cli::val('description', $arg, ''));
            }
            $table->write();
            echo PHP_EOL;
        }
    }

    /**
     * Writes the basic usage information of the command.
     *
     * @param Args $args The parsed args returned from {@link Cli::parseRaw()}.
     *
     * @return void
     */
    protected function writeUsage(Args $args): void {
        if ($filename = $args->getMeta('filename')) {
            echo static::bold("usage: ").$filename;

            if ($this->hasCommand()) {
                if ($args->getCommand() && isset($this->commandSchemas[$args->getCommand()])) {
                    echo ' '.$args->getCommand();
                } else {
                    echo ' <command>';
                }
            }

            if ($this->hasOptions($args->getCommand())) {
                echo " [<options>]";
            }

            if ($hasArgs = $this->hasArgs($args->getCommand())) {
                echo $hasArgs === 2 ? " <args>" : " [<args>]";
            }

            echo PHP_EOL.PHP_EOL;
        }
    }

    /**
     * Safely get a value out of an array.
     *
     * This function uses optimizations found in the [facebook libphputil library](https://github.com/facebook/libphutil).
     *
     * @param string|int $key The array key.
     * @param array $array The array to get the value from.
     * @param mixed $default The default value to return if the key doesn't exist.
     * @return mixed The item from the array or `$default` if the array key doesn't exist.
     */
    public static function val($key, array $array, $default = null) {
        // isset() is a micro-optimization - it is fast but fails for null values.
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Comparing $default is also a micro-optimization.
        if ($default === null || array_key_exists($key, $array)) {
            return null;
        }

        return $default;
    }

    /**
     * Push an option value to an option.
     *
     * If the option already exists then an array will be set. Otherwise, the value is just set.
     *
     * @param Args $args The args to modify.
     * @param string $key The option key.
     * @param mixed $value The value of the option.
     */
    private function pushOpt(Args $args, string $key, $value): void {
        if ($args->hasOpt($key)) {
            $args->setOpt($key, array_merge((array)$args->getOpt($key, []), [$value]));
        } else {
            $args->setOpt($key, $value);
        }
    }

    /**
     * Merge a validated opt to an args array.
     *
     * @param Args $args The args to merge to.
     * @param string $key The key of the opt.
     * @param mixed $value The value to merge.
     */
    private function mergeOpt(Args $args, string $key, $value): void {
        if (is_array($value)) {
            $args->setOpt($key, array_merge((array)$args->getOpt($key, []), $value));
        } else {
            $args->setOpt($key, $value);
        }
    }

    /**
     * Extract validated option values from a raw parse opts array.
     *
     * @param array $opts The raw opts array.
     * @param string $key The real options key.
     * @param OptSchema $def The option definition.
     * @return array|mixed Returns the extracted opts.
     */
    private function extractOpt(array &$opts, string $key, OptSchema $def) {
        $r = [];
        $unset = [];

        foreach ($opts as $k => $v) {
            if ($k === $def->getName() ||
                $k === $def->getShortName()
            ) {
                $r = array_merge($r, (array)$v);
                $unset[] = $k;
            }
        }

        foreach ($unset as $k) {
            unset($opts[$k]);
        }

        if (!$def->isArray()) {
            $r = array_pop($r);
        } elseif (empty($r)) {
            $r = null;
        }

        return $r;
    }
}
