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
    public $format = true;

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
     * Creates a {@see Cli} instance representing a command line parser for a given schema.
     */
    public function __construct() {
        $this->commandSchemas = ['*' => [Cli::META => []]];

        // Select the current schema.
        $this->currentSchema =& $this->commandSchemas['*'];
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
     * @return array Returns an array of lines, broken on word boundries.
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
     * Sets the description for the current schema.
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
     * Determins whether a command has options.
     *
     * @param string $command The name of the command or an empty string for any command.
     * @return Returns true if the command has options. False otherwise.
     */
    public function hasOptions($command = '') {
        if ($command) {
            $def = $this->getSchema($command);
            if (count($def) > 1 || (count($def) > 0 && !isset($def[Cli::META]))) {
                return true;
            } else {
                return false;
            }
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                if (count($def) > 1 || (count($def) > 0 && !isset($def[Cli::META]))) {
                    return true;
                }
            }
        }
        return false;
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
     * @return Args|null Returns an {@see Args} instance when a command should be executed or `null` when one shouldn't.
     */
    public function parse($argv = null, $exit = true) {
        // Only format commands if we are exiting.
        $this->format = $exit;
        if (!$exit) {
            ob_start();
        }

        $args = $this->parseRaw($argv);

        $hasCommand = $this->hasCommand();


        if ($hasCommand && !$args->command()) {
            // If no command is given then write a list of commands.
            $this->writeUsage($args);
            $this->writeCommands();
            $result = null;
        } elseif ($args->getOpt('help')) {
            // Write the help.
            $this->writeUsage($args);
            $this->writeHelp($this->getSchema($args->command()));
            $result = null;
        } else {
            // Validate the arguments against the schema.
            $validArgs = $this->validate($args);
            $result = $validArgs;
        }
        if (!$exit) {
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
     * If the first item in the array is in the form of a command (no preceeding - or --),
     * 'command' is filled with its value.
     *
     * @param array $argv An array of arguments passed in a form compatible with the global `$argv` variable.
     * @return Args Returns the raw parsed arguments.
     * @throws \Exception Throws an exception when {@see $argv} isn't an array.
     */
    public function parseRaw($argv = null) {
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

        if ($argc = count($argv)) {
            // Get possible command.
            if (substr($argv[0], 0, 1) != '-') {
                $arg0 = array_shift($argv);
                if ($hasCommand) {
                    $parsed->command($arg0);
                } else {
                    $parsed->addArg($arg0);
                }
            }
            // Get the data types for all of the commands.
            $schema = $this->getSchema($parsed->command());
            $types = [];
            foreach ($schema as $sname => $srow) {
                if ($sname === Cli::META) {
                    continue;
                }

                $type = Cli::val('type', $srow, 'string');
                $types[$sname] = $type;
                if (isset($srow['short'])) {
                    $types[$srow['short']] = $type;
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

                    // Does not have an =, so choose the next arg as its value
                    if (count($parts) == 1 && isset($argv[$i + 1]) && preg_match('/^--?.+/', $argv[$i + 1]) == 0) {
                        $v = $argv[$i + 1];
                        $i++;
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
                            if (in_array($nextArg, ['0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'])) {
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
                                $optval = $parsed->getOpt($opt, 0);
                                $parsed->setOpt($opt, $optval + 1);
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
                $parsed->addArg($argv[$i]);
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
        $command = $args->command();
        $valid = new Args($command);
        $schema = $this->getSchema($command);
        $meta = $schema[Cli::META];
        unset($schema[Cli::META]);
        $opts = $args->opts();
        $missing = [];

        // Check to see if the command is correct.
        if ($command && !$this->hasCommand($command) && $this->hasCommand()) {
            echo $this->red("Invalid command: $command.\n");
            $isValid = false;
        }

        // Add the args.
        $valid->args($args->args());

        foreach ($schema as $key => $definition) {
            // No Parameter (default)
            $required = Cli::val('required', $definition, false);
            $type = Cli::val('type', $definition, 'string');
            $value = null;

            if (isset($opts[$key])) {
                // Check for --key.
                $value = $opts[$key];
                if ($this->validateType($value, $type)) {
                    $valid->setOpt($key, $value);
                } else {
                    echo $this->red("The value of --$key is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts[$key]);
            } elseif (isset($definition['short']) && isset($opts[$definition['short']])) {
                // Check for -s.
                $value = $opts[$definition['short']];
                if ($this->validateType($value, $type)) {
                    $valid->setOpt($key, $value);
                } else {
                    echo $this->red("The value of --$key (-{$definition['short']}) is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts[$definition['short']]);
            } elseif (isset($opts['no-'.$key])) {
                // Check for --no-key.
                $value = $opts['no-'.$key];

                if ($type !== 'boolean') {
                    echo $this->red("Cannont apply the --no- prefix on the non boolean --$key.\n");
                    $isValid = false;
                } elseif ($this->validateType($value, $type)) {
                    $valid->setOpt($key, !$value);
                } else {
                    echo $this->red("The value of --no-$key is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts['no-'.$key]);
            } elseif ($definition['required']) {
                // The key was not supplied. Is it required?
                $missing[$key] = true;
            } elseif ($type === 'boolean') {
                // The value os not required, but can maybe be coerced into a type.
                $valid->setOpt($key, false);
            }
        }

        if (count($missing)) {
            $isValid = false;
            foreach ($missing as $key => $v) {
                echo $this->red("Missing required option: $key\n");
            }
        }

        if (count($opts)) {
            $isValid = false;
            foreach ($opts as $key => $v) {
                echo $this->red("Invalid option: $key\n");
            }
        }

        if ($isValid) {
            return $valid;
        } else {
            echo "\n";
            return null;
        }
    }

    /**
     * Gets the schema full cli schema.
     *
     * @param string $command The name of the command.
     * @return array Returns the schema that matches the command.
     */
    public function getSchema($command = '') {
        $result = [];
        foreach ($this->commandSchemas as $pattern => $opts) {
            if (fnmatch($pattern, $command)) {
                $result = array_replace($result, $opts);
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
     * @param string $name The long name of the parameter.
     * @param string $description A human-readable description for the column.
     * @param bool $required Whether or not the opt is required.
     * @param string $type The type of parameter.
     * This must be one of string, bool, integer.
     * @param string $short The short name of the opt.
     * @return Cli Returns this object for fluent calls.
     * @throws \Exception Throws an exception when the type is invalid.
     */
    public function opt($name, $description, $required = false, $type = 'string', $short = '') {
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

        $this->currentSchema[$name] = ['description' => $description, 'required' => $required, 'type' => $type, 'short' => $short];
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
            $this->commandSchemas[$pattern] = [Cli::META];
        }
        $this->currentSchema =& $this->commandSchemas[$pattern];

        return $this;
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
     *     type:name[:shortcode][?],
     *     type:name[:shortcode][?],
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
     * Make some text red.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function red($text) {
        return $this->formatString($text, ["\033[1;31m", "\033[0m"]);
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
     * Make some text blue.
     *
     * @param string $text The text to format.
     * @return string Returns  text surrounded by formatting commands.
     */
    public function blue($text) {
        return $this->formatString($text, ["\033[1;34m", "\033[0m"]);
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
     * Format some text for the console.
     *
     * @param string $text The text to format.
     * @param array $wrap The format to wrap in the form ['before', 'after'].
     * @return string Returns the string formatted according to {@link Cli::$format}.
     */
    public function formatString($text, array $wrap) {
        if ($this->format) {
            return "{$wrap[0]}$text{$wrap[1]}";
        } else {
            return $text;
        }
    }

    /**
     * Validate the type of a value an coerce it into the proper type.
     *
     * @param mixed &$value The value to validate.
     * @param string $type One of: bool, int, string.
     * @return bool Returns `true` if the value is the correct type.
     * @throws \Exception Throws an exception when {@see $type} is not a known value.
     */
    protected function validateType(&$value, $type) {
        switch ($type) {
            case 'boolean':
                if (is_bool($value)) {
                    return true;
                } elseif ($value === 0) {
                    // 0 doesn't work well with in_array() so check it seperately.
                    $value = false;
                    return true;
                } elseif (in_array($value, [null, '', '0', 'false', 'no', 'disabled'])) {
                    $value = false;
                    return true;
                } elseif (in_array($value, [1, '1', 'true', 'yes', 'enabled'])) {
                    $value = true;
                    return true;
                } else {
                    return false;
                }
                break;
            case 'integer':
                if (is_numeric($value)) {
                    $value = (int)$value;
                    return true;
                } else {
                    return false;
                }
                break;
            case 'string':
                $value = (string)$value;
                return true;
            default:
                throw new \Exception("Unknown type: $type.", 400);
        }
    }

    /**
     * Writes a lis of all of the commands.
     */
    protected function writeCommands() {
        echo static::bold("COMMANDS\n");

        $table = new Table();
        foreach ($this->commandSchemas as $pattern => $schema) {
            if (static::isCommand($pattern)) {
                $table
                    ->row()
                    ->cell($pattern)
                    ->cell(val('description', Cli::val(Cli::META, $schema), ''));
            }
        }
        $table->write();
    }

    /**
     * Writes the help for a given schema.
     * @param array $schema A command line scheme returned from {@see Cli::getSchema()}.
     */
    protected function writeHelp($schema) {
        // Write the command description.
        $meta = Cli::val(Cli::META, $schema, []);
        $description = Cli::val('description', $meta);

        if ($description) {
            echo implode("\n", Cli::breakLines($description, 80, false))."\n\n";
        }

        unset($schema[Cli::META]);

        if (count($schema)) {
            echo Cli::bold('OPTIONS')."\n";

            ksort($schema);

            $table = new Table();

            foreach ($schema as $key => $definition) {
                $table->row();

                // Write the keys.
                $keys = "--{$key}";
                if ($shortKey = Cli::val('short', $definition, false)) {
                    $keys .= ", -$shortKey";
                }
                if (val('required', $definition)) {
                    $table->bold($keys);
                } else {
                    $table->cell($keys);
                }

                // Write the description.
                $table->cell(val('description', $definition));
            }

            $table->write();
            echo "\n";
        }
    }

    /**
     * Writes the basic usage information of the command.
     *
     * @param Args $args The parsed args returned from {@link Cli::parseRaw()}.
     */
    protected function writeUsage(Args $args) {
        if ($filename = $args->getMeta('filename')) {
            $schema = $this->getSchema($args->command());
            unset($schema[Cli::META]);

            echo static::bold("usage: ").$filename;

            if ($this->hasCommand()) {
                if ($args->command() && isset($this->commandSchemas[$args->command()])) {
                    echo ' '.$args->command();

                } else {
                    echo ' <command>';
                }
            }

            if ($this->hasOptions($args->command())) {
                echo " [<options>]";
            }

            echo "\n\n";
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
