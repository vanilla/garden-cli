<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

use ArrayAccess;
use InvalidArgumentException;
use JsonSerializable;

/**
 * This class represents the parsed and validated argument list.
 */
class Args implements JsonSerializable, ArrayAccess {
    protected $command;
    protected $opts;
    protected $args;
    protected $meta;

    /**
     * Initialize the {@link Args} instance.
     *
     * @param string $command The name of the command.
     * @param array $opts An array of command line options.
     * @param array $args A numeric array of command line args.
     */
    public function __construct($command = '', $opts = [], $args = []) {
        $this->command = $command;
        $this->opts = $opts;
        $this->args = $args;
        $this->meta = [];
    }

    /**
     * Add an argument to the args array.
     *
     * @param string $value The argument to add.
     * @param string|null $index The index to add the arg at.
     * @return $this
     */
    public function addArg($value, $index = null) {
        if ($index !== null) {
            $this->args[$index] = $value;
        } else {
            $this->args[] = $value;
        }
        return $this;
    }

    /**
     * Get an argument at a given index.
     *
     * Arguments can be accessed by name or index.
     *
     * @param string|int $index
     * @param mixed $default The default value to return if the argument is not found.
     * @return mixed Returns the argument at {@link $index} or {@link $default}.
     */
    public function getArg($index, $default = null) {
        if (array_key_exists($index, $this->args)) {
            return $this->args[$index];
        } elseif (is_int($index)) {
            $values = array_values($this->args);
            if (array_key_exists($index, $values)) {
                return $values[$index];
            }
        }
        return $default;
    }

    /**
     * Set an argument in the args array.
     *
     * @param string|int $index The index to set at.
     * @param mixed $value The value of the arg.
     * @return $this
     */
    public function setArg($index, $value) {
        $this->args[$index] = $value;
        return $this;
    }

    /**
     * Get the args array.
     *
     * @return array Returns the args array.
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * Set the args array.
     *
     * @param array $args The new args array.
     * @return $this
     */
    public function setArgs(array $args) {
        $this->args = $args;
        return $this;
    }

    /**
     * Get the name of the command associated with the args.
     *
     * @return string Returns the name of the command.
     */
    public function getCommand() {
        return $this->command;
    }

    /**
     * Set the name of the command associated with the args.
     *
     * @param string $command The new command.
     * @return $this
     */
    public function setCommand($command) {
        $this->command = $command;
        return $this;
    }

    /**
     * Get a meta value.
     *
     * @param string $name The name of the meta value.
     * @param mixed $default The default value to return if {@link $name} is not found.
     * @return mixed Returns the meta value or {@link $default} if it doesn't exist.
     */
    public function getMeta($name, $default = null) {
        return Cli::val($name, $this->meta, $default);
    }

    /**
     * Set a meta value.
     *
     * @param string $name The name of the meta value.
     * @param mixed $value The new meta value.
     * @return $this
     */
    public function setMeta($name, $value) {
        $this->meta[$name] = $value;
        return $this;
    }

    /**
     * Gets the entire options array.
     *
     * @return array Returns the current options array.
     */
    public function getOpts() {
        return $this->opts;
    }

    /**
     * Sets the entire options array.
     *
     * @param array $value Pass an array to set a new options array.
     * @return $this
     */
    public function setOpts(array $value) {
        $this->opts = $value;
        return $this;
    }

    /**
     * Get the value of a passed option.
     *
     * @param string $option The name of the option to get.
     * @param mixed $default The default value if the option does not exist.
     * @return mixed Returns the option or {@link $default} if it does not exist.
     */
    public function getOpt($option, $default = null) {
        return Cli::val($option, $this->opts, $default);
    }

    /**
     * Alias of `getOpt()`.
     *
     * @param string $name The name of the opt to get.
     * @return mixed
     */
    public function get(string $name) {
        return $this->getOpt($name, null);
    }

    /**
     * Determine whether or not an option has been set.
     *
     * @param string $option The name of the option.
     * @return bool Returns **true** if the option has been set or **false** otherwise.
     */
    public function hasOpt(string $option): bool {
        return array_key_exists($option, $this->opts);
    }

    /**
     * Set an option.
     *
     * @param string $option The name of the option.
     * @param mixed $value The value of the option.
     * @return $this
     */
    public function setOpt($option, $value) {
        $this->opts[$option] = $value;
        return $this;
    }

    /**
     * Return the json serializable data for the args.
     *
     * @return array Returns an array of data that can be used to serialize the args to json.
     *
     * @psalm-return array{command: mixed, opts: mixed, args: mixed, meta: mixed}
     */
    public function jsonSerialize() {
        return [
            'command' => $this->command,
            'opts' => $this->opts,
            'args' => $this->args,
            'meta' => $this->meta
        ];
    }

    /**
     * Whether a offset exists.
     *
     * @param mixed $offset An offset to check for.
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists($offset) {
        return isset($this->opts[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset) {
        return $this->getOpt($offset, null);
    }

    /**
     * Offset to set.
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value) {
        $this->setOpt($offset, $value);
    }

    /**
     * Offset to unset.
     *
     * @param mixed $offset The offset to unset.
     * @return void
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset) {
        unset($this->opts[$offset]);
    }

    /**
     * Whether or not an arg exists.
     *
     * @param string|int $arg The name of the arg or the zero based position of it.
     * @return bool
     */
    public function hasArg($arg): bool {
        if (is_string($arg)) {
            return isset($this->args[$arg]);
        } elseif (is_int($arg)) {
            return $arg < count($this->args);
        } else {
            throw new InvalidArgumentException("Args::hasArg() expects an integer or string.", 400);
        }
    }
}
