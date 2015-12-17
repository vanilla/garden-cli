<?php

namespace Garden\Cli;

/**
 * A general purpose command line parser.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @license MIT
 * @copyright 2010-2014 Vanilla Forums Inc.
 */
class Cli {
    /// Constants ///

    const META = '__meta';
    const ARGS = '__args';

    /// Properties ///
    /**
     * @var array All of the schemas, indexed by command pattern.
     */
    protected $commandSchemas;

    /**
     * @var array A pointer to the current schema.
     */
    protected $currentSchema;

    /**
     * @var bool Whether or not to format output with console codes.
     */
    protected $formatOutput;

    protected static $types = [
//        '=' => 'base64',
        'i' => 'integer',
        's' => 'string',
//        'f' => 'float',
        'b' => 'boolean',
//        'ts' => 'timestamp',
//        'dt' => 'datetime'
    ];


    /// Methods ///

    /**
     * Creates a {@link Cli} instance representing a command line parser for a given schema.
     */
    public function __construct() {
        $this->commandSchemas = ['*' => [Cli::META => []]];

        // Select the current schema.
        $this->currentSchema =& $this->commandSchemas['*'];

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
     * @return Cli Returns `$this` for fluent calls.
     */
    public function setFormatOutput($formatOutput) {
        $this->formatOutput = $formatOutput;
        return $this;
    }

    /**
     * Add an argument to an {@link Args} object, checking for a correct name.
     *
     * @param array $schema The schema for the args.
     * @param Args $args The args object to add the argument to.
     * @param $arg The value of the argument.
     */
    private function addArg(array $schema, Args $args, $arg) {
        $argsCount = count($args->getArgs());
        $schemaArgs = isset($schema[self::META][self::ARGS]) ? array_keys($schema[self::META][self::ARGS]) : [];
        $name = isset($schemaArgs[$argsCount]) ? $schemaArgs[$argsCount] : $argsCount;

        $args->addArg($arg, $name);
    }

    /**
     * Construct and return a new {@link Cli} object.
     *
     * This method is mainly here so that an entire cli schema can be created and defined with fluent method calls.
     *
     * @return Cli Returns a new Cli object.
     */
    public static function create() {
        return new Cli();
    }

    /**
     * Breaks a cell into several lines according to a given width.
     *
     * @param string $text The text of the cell.
     * @param int $width The width of the cell.
     * @param bool $addSpaces Whether or not to right-pad the cell with spaces.
     * @return array Returns an array of strings representing the lines in the cell.
     */
    public static function breakLines($text, $width, $addSpaces = true) {
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
     * @return array Returns an array of lines, broken on word boundaries.
     */
    protected static function breakString($line, $width, $addSpaces = true) {
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
                        $result[] = substr($candidate, 0, $width);
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
     * @param string $str The description for the current schema or null to get the current description.
     * @return Cli Returns this class for fluent calls.
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
            return $this->hasOptionsDef($def);
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                if ($this->hasOptionsDef($def)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determines whether or not a command definition has options.
     *
     * @param array $commandDef The command definition as returned from {@link Cli::getSchema()}.
     * @return bool Returns true if the command def has options or false otherwise.
     */
    protected function hasOptionsDef($commandDef) {
        return count($commandDef) > 1 || (count($commandDef) > 0 && !isset($commandDef[Cli::META]));
    }

    /**
     * Determines whether or not a command has args.
     *
     * @param string $command The command name to check.
     * @return int Returns one of the following.
     * - 0: The command has no args.
     * - 1: The command has optional args.
     * - 2: The command has required args.
     */
    public function hasArgs($command = '') {
        $args = null;

        if ($command) {
            // Check to see if the specific command has args.
            $def = $this->getSchema($command);
            if (isset($def[Cli::META][Cli::ARGS])) {
                $args = $def[Cli::META][Cli::ARGS];
            }
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                if (isset($def[Cli::META][Cli::ARGS])) {
                    $args = $def[Cli::META][Cli::ARGS];
                }
            }
            if (!empty($args)) {
                return 1;
            }
        }

        if (!$args || empty($args)) {
            return 0;
        }

        foreach ($args as $arg) {
            if (!Cli::val('required', $arg)) {
                return 1;
            }
        }
        return 2;
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
     * @param array $argv The command line arguments a form compatible with the global `$argv` variable.
     *
     * Note that the `$argv` array must have at least one element and it must represent the path to the command that
     * invoked the command. This is used to write usage information.
     * @param bool $exit Whether to exit the application when there is an error or when writing help.
     * @return Args|null Returns an {@see Args} instance when a command should be executed
     * or `null` when one should not be executed.
     * @throws \Exception Throws an exception when {@link $exit} is false and the help or errors need to be displayed.
     */
    public function parse($argv = null, $exit = true) {
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
                throw new \Exception(trim($output));
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
     * @param array $argv An array of arguments passed in a form compatible with the global `$argv` variable.
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
            foreach ($schema as $sName => $sRow) {
                if ($sName === Cli::META) {
                    continue;
                }

                $type = Cli::val('type', $sRow, 'string');
                $types[$sName] = $type;
                if (isset($sRow['short'])) {
                    $types[$sRow['short']] = $type;
                }
            }

            // Parse opts.
            for ($i = 0; $i < count($argv); $i++) {
                $str = $argv[$i];

                if ($str === '--') {
                    // --
                    $i++;
                    break;
                } elseif (strlen($str) > 2 && substr($str, 0, 2) == '--') {
                    // --foo
                    $str = substr($str, 2);
                    $parts = explode('=', $str);
                    $key = $parts[0];

                    // Does not have an =, so choose the next arg as its value,
                    // unless it is defined as 'boolean' in which case there is no
                    // value to seek in next arg
                    if (count($parts) == 1 && isset($argv[$i + 1]) && preg_match('/^--?.+/', $argv[$i + 1]) == 0) {
                        $v = $argv[$i + 1];
                        if (Cli::val($key, $types) == 'boolean') {
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
                    } elseif (count($parts) == 2) {// Has a =, so pick the second piece
                        $v = $parts[1];
                    } else {
                        $v = true;
                    }
                    $parsed->setOpt($key, $v);
                } elseif (strlen($str) == 2 && $str[0] == '-') {
                    // -a

                    $key = $str[1];
                    $type = Cli::val($key, $types, 'boolean');
                    $v = null;

                    if (isset($argv[$i + 1])) {
                        // Try and be smart about the next arg.
                        $nextArg = $argv[$i + 1];

                        if ($type === 'boolean') {
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
                        $v = Cli::val($type, ['boolean' => true, 'integer' => 1, 'string' => '']);
                    }

                    $parsed->setOpt($key, $v);
                } elseif (strlen($str) > 1 && $str[0] == '-') {
                    // -abcdef
                    for ($j = 1; $j < strlen($str); $j++) {
                        $opt = $str[$j];
                        $remaining = substr($str, $j + 1);
                        $type = Cli::val($opt, $types, 'boolean');

                        // Check for an explicit equals sign.
                        if (substr($remaining, 0, 1) === '=') {
                            $remaining = substr($remaining, 1);
                            if ($type === 'boolean') {
                                // Bypass the boolean flag checking below.
                                $parsed->setOpt($opt, $remaining);
                                break;
                            }
                        }

                        if ($type === 'boolean') {
                            if (preg_match('`^([01])`', $remaining, $matches)) {
                                // Treat the 0 or 1 as a true or false.
                                $parsed->setOpt($opt, $matches[1]);
                                $j += strlen($matches[1]);
                            } else {
                                // Treat the option as a flag.
                                $parsed->setOpt($opt, true);
                            }
                        } elseif ($type === 'string') {
                            // Treat the option as a set with no = sign.
                            $parsed->setOpt($opt, $remaining);
                            break;
                        } elseif ($type === 'integer') {
                            if (preg_match('`^(\d+)`', $remaining, $matches)) {
                                // Treat the option as a set with no = sign.
                                $parsed->setOpt($opt, $matches[1]);
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
     */
    public function validate(Args $args) {
        $isValid = true;
        $command = $args->getCommand();
        $valid = new Args($command);
        $schema = $this->getSchema($command);
        ksort($schema);

//        $meta = $schema[Cli::META];
        unset($schema[Cli::META]);
        $opts = $args->getOpts();
        $missing = [];

        // Check to see if the command is correct.
        if ($command && !$this->hasCommand($command) && $this->hasCommand()) {
            echo $this->red("Invalid command: $command.".PHP_EOL);
            $isValid = false;
        }

        // Add the args.
        $valid->setArgs($args->getArgs());

        foreach ($schema as $key => $definition) {
            // No Parameter (default)
            $type = Cli::val('type', $definition, 'string');

            if (isset($opts[$key])) {
                // Check for --key.
                $value = $opts[$key];
                if ($this->validateType($value, $type, $key, $definition)) {
                    $valid->setOpt($key, $value);
                } else {
                    $isValid = false;
                }
                unset($opts[$key]);
            } elseif (isset($definition['short']) && isset($opts[$definition['short']])) {
                // Check for -s.
                $value = $opts[$definition['short']];
                if ($this->validateType($value, $type, $key, $definition)) {
                    $valid->setOpt($key, $value);
                } else {
                    $isValid = false;
                }
                unset($opts[$definition['short']]);
            } elseif (isset($opts['no-'.$key])) {
                // Check for --no-key.
                $value = $opts['no-'.$key];

                if ($type !== 'boolean') {
                    echo $this->red("Cannot apply the --no- prefix on the non boolean --$key.".PHP_EOL);
                    $isValid = false;
                } elseif ($this->validateType($value, $type, $key, $definition)) {
                    $valid->setOpt($key, !$value);
                } else {
                    $isValid = false;
                }
                unset($opts['no-'.$key]);
            } elseif ($definition['required']) {
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
     * @return array Returns the schema that matches the command.
     */
    public function getSchema($command = '') {
        $result = [];
        foreach ($this->commandSchemas as $pattern => $opts) {
            if (fnmatch($pattern, $command)) {
                $result = array_replace_recursive($result, $opts);
            }
        }
        return $result;
    }

    /**
     * Gets/sets the value for a current meta item.
     *
     * @param string $name The name of the meta key.
     * @param mixed $value Set a new value for the meta key.
     * @return Cli|mixed Returns the current value of the meta item or `$this` for fluent setting.
     */
    public function meta($name, $value = null) {
        if ($value !== null) {
            $this->currentSchema[Cli::META][$name] = $value;
            return $this;
        }
        if (!isset($this->currentSchema[Cli::META][$name])) {
            return null;
        }
        return $this->currentSchema[Cli::META][$name];
    }

    /**
     * Adds an option (opt) to the current schema.
     *
     * @param string $name The long name(s) of the parameter.
     * You can use either just one name or a string in the form 'long:short' to specify the long and short name.
     * @param string $description A human-readable description for the column.
     * @param bool $required Whether or not the opt is required.
     * @param string $type The type of parameter.
     * This must be one of string, bool, integer.
     * @return Cli Returns this object for fluent calls.
     * @throws \Exception Throws an exception when the type is invalid.
     */
    public function opt($name, $description, $required = false, $type = 'string') {
        switch ($type) {
            case 'str':
            case 'string':
                $type = 'string';
                break;
            case 'bool':
            case 'boolean':
                $type = 'boolean';
                break;
            case 'int':
            case 'integer':
                $type = 'integer';
                break;
            default:
                throw new \Exception("Invalid type: $type. Must be one of string, boolean, or integer.", 422);
        }

        // Break the name up into its long and short form.
        $parts = explode(':', $name, 2);
        $long = $parts[0];
        $short = static::val(1, $parts, '');

        $this->currentSchema[$long] = ['description' => $description, 'required' => $required, 'type' => $type, 'short' => $short];
        return $this;
    }

    /**
     * Define an arg on the current command.
     *
     * @param string $name The name of the arg.
     * @param string $description The arg description.
     * @param bool $required Whether or not the arg is required.
     * @return Cli Returns $this for fluent calls.
     */
    public function arg($name, $description, $required = false) {
        $this->currentSchema[Cli::META][Cli::ARGS][$name] =
            ['description' => $description, 'required' => $required];
        return $this;
    }

    /**
     * Selects the current command schema name.
     *
     * @param string $pattern The command pattern.
     * @return Cli Returns $this for fluent calls.
     */
    public function command($pattern) {
        if (!isset($this->commandSchemas[$pattern])) {
            $this->commandSchemas[$pattern] = [Cli::META => []];
        }
        $this->currentSchema =& $this->commandSchemas[$pattern];

        return $this;
    }


    /**
     * Determine weather or not a value can be represented as a boolean.
     *
     * This method is sort of like {@link Cli::validateType()} but requires a more strict check of a boolean value.
     *
     * @param mixed $value The value to test.
     * @return bool
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
     * Set the schema for a command.
     *
     * The schema array uses a short syntax so that commands can be specified as quickly as possible.
     * This schema is the exact same as those provided to {@link Schema::create()}.
     * The basic format of the array is the following:
     *
     * ```
     * [
     *     type:name[:shortCode][?],
     *     type:name[:shortCode][?],
     *     ...
     * ]
     * ```
     *
     * @param array $schema The schema array.
     */
    public function schema(array $schema) {
        $parsed = static::parseSchema($schema);

        $this->currentSchema = array_replace($this->currentSchema, $parsed);
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
     * @return bool Returns **true** if the output can be formatter or **false** otherwise.
     */
    public static function guessFormatOutput() {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            return false;
        } elseif (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        } else {
            return true;
        }
    }

    /**
     * Sleep for a number of seconds, echoing out a dot on each second.
     *
     * @param int $seconds The number of seconds to sleep.
     */
    public static function sleep($seconds) {
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            echo '.';
        }
    }

    /**
     * Validate the type of a value and coerce it into the proper type.
     *
     * @param mixed &$value The value to validate.
     * @param string $type One of: bool, int, string.
     * @param string $name The name of the option if you want to print an error message.
     * @param array|null $def The option def if you want to print an error message.
     * @return bool Returns `true` if the value is the correct type.
     * @throws \Exception Throws an exception when {@see $type} is not a known value.
     */
    protected function validateType(&$value, $type, $name = '', $def = null) {
        switch ($type) {
            case 'boolean':
                if (is_bool($value)) {
                    $valid = true;
                } elseif ($value === 0) {
                    // 0 doesn't work well with in_array() so check it separately.
                    $value = false;
                    $valid = true;
                } elseif (in_array($value, [null, '', '0', 'false', 'no', 'disabled'])) {
                    $value = false;
                    $valid = true;
                } elseif (in_array($value, [1, '1', 'true', 'yes', 'enabled'])) {
                    $value = true;
                    $valid = true;
                } else {
                    $valid = false;
                }
                break;
            case 'integer':
                if (is_numeric($value)) {
                    $value = (int)$value;
                    $valid = true;
                } else {
                    $valid = false;
                }
                break;
            case 'string':
                $value = (string)$value;
                $valid = true;
                break;
            default:
                throw new \Exception("Unknown type: $type.", 400);
        }

        if (!$valid && $name) {
            $short = static::val('short', (array)$def);
            $nameStr = "--$name".($short ? " (-$short)" : '');
            echo $this->red("The value of $nameStr is not a valid $type.".PHP_EOL);
        }

        return $valid;
    }

    /**
     * Writes a lis of all of the commands.
     */
    protected function writeCommands() {
        echo static::bold("COMMANDS").PHP_EOL;

        $table = new Table();
        foreach ($this->commandSchemas as $pattern => $schema) {
            if (static::isCommand($pattern)) {
                $table
                    ->row()
                    ->cell($pattern)
                    ->cell(Cli::val('description', Cli::val(Cli::META, $schema), ''));
            }
        }
        $table->write();
    }

    /**
     * Writes the cli help.
     *
     * @param string $command The name of the command or blank if there is no command.
     */
    public function writeHelp($command = '') {
        $schema = $this->getSchema($command);
        $this->writeSchemaHelp($schema);
    }

    /**
     * Writes the help for a given schema.
     *
     * @param array $schema A command line scheme returned from {@see Cli::getSchema()}.
     */
    protected function writeSchemaHelp($schema) {
        // Write the command description.
        $meta = Cli::val(Cli::META, $schema, []);
        $description = Cli::val('description', $meta);

        if ($description) {
            echo implode("\n", Cli::breakLines($description, 80, false)).PHP_EOL.PHP_EOL;
        }

        unset($schema[Cli::META]);

        // Add the help.
        $schema['help'] = [
            'description' => 'Display this help.',
            'type' => 'boolean',
            'short' => '?'
        ];

        echo Cli::bold('OPTIONS').PHP_EOL;

        ksort($schema);

        $table = new Table();
        $table->setFormatOutput($this->formatOutput);

        foreach ($schema as $key => $definition) {
            $table->row();

            // Write the keys.
            $keys = "--{$key}";
            if ($shortKey = Cli::val('short', $definition, false)) {
                $keys .= ", -$shortKey";
            }
            if (Cli::val('required', $definition)) {
                $table->bold($keys);
            } else {
                $table->cell($keys);
            }

            // Write the description.
            $table->cell(Cli::val('description', $definition, ''));
        }

        $table->write();
        echo PHP_EOL;

        $args = Cli::val(Cli::ARGS, $meta, []);
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
     */
    protected function writeUsage(Args $args) {
        if ($filename = $args->getMeta('filename')) {
            $schema = $this->getSchema($args->getCommand());
            unset($schema[Cli::META]);

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
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    public static function parseSchema(array $arr) {
        $result = [];

        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                if (is_string($value)) {
                    // This is a short param value.
                    $param = static::parseShortParam($value);
                    $name = $param['name'];
                    $result[$name] = $param;
                } else {
                    throw new \InvalidArgumentException("Schema at position $key is not a valid param.", 500);
                }
            } else {
                // The parameter is defined in the key.
                $param = static::parseShortParam($key, $value);
                $name = $param['name'];

                if (is_array($value)) {
                    // The value describes a bit more about the schema.
                    switch ($param['type']) {
                        case 'array':
                            if (isset($value['items'])) {
                                // The value includes array schema information.
                                $param = array_replace($param, $value);
                            } else {
                                // The value is a schema of items.
                                $param['items'] = $value;
                            }
                            break;
                        case 'object':
                            // The value is a schema of the object.
                            $param['properties'] = static::parseSchema($value);
                            break;
                        default:
                            $param = array_replace($param, $value);
                            break;
                    }
                } elseif (is_string($value)) {
                    if ($param['type'] === 'array') {
                        // Check to see if the value is the item type in the array.
                        if (isset(self::$types[$value])) {
                            $arrType = self::$types[$value];
                        } elseif (($index = array_search($value, self::$types)) !== false) {
                            $arrType = self::$types[$value];
                        }

                        if (isset($arrType)) {
                            $param['items'] = ['type' => $arrType];
                        } else {
                            $param['description'] = $value;
                        }
                    } else {
                        // The value is the schema description.
                        $param['description'] = $value;
                    }
                }

                $result[$name] = $param;
            }
        }

        return $result;
    }

    /**
     * Parse a short parameter string into a full array parameter.
     *
     * @param string $str The short parameter string to parse.
     * @param array $other An array of other information that might help resolve ambiguity.
     * @return array Returns an array in the form [name, [param]].
     * @throws \InvalidArgumentException Throws an exception if the short param is not in the correct format.
     */
    protected static function parseShortParam($str, $other = []) {
        // Is the parameter optional?
        if (substr($str, -1) === '?') {
            $required = false;
            $str = substr($str, 0, -1);
        } else {
            $required = true;
        }

        // Check for a type.
        $parts = explode(':', $str);

        if (count($parts) === 1) {
            if (isset($other['type'])) {
                $type = $other['type'];
            } else {
                $type = 'string';
            }
            $name = $parts[0];
        } else {
            $name = $parts[1];

            if (isset(self::$types[$parts[0]])) {
                $type = self::$types[$parts[0]];
            } else {
                throw new \InvalidArgumentException("Invalid type {$parts[1]} for field $name.", 500);
            }

            if (isset($parts[2])) {
                $short = $parts[2];
            }
        }

        $result = ['name' => $name, 'type' => $type, 'required' => $required];

        if (isset($short)) {
            $result['short'] = $short;
        }

        return $result;
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
}
