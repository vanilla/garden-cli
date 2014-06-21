<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

/**
 * This class represents the parsed and validated argument list.
 */
class Args implements \JsonSerializable, \ArrayAccess {
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
     * @return Args Returns $this for fluent calling.
     */
    public function addArg($value) {
        $this->args[] = $value;
        return $this;
    }

    /**
     * Get or set the argument array.
     *
     * The argument array is the array of files that usually go after the command options.
     * The arguments should not be confused with the options.
     *
     * @param array $value Set a new args array or pass null to get the current args array.
     * @return Args|array Returns the current args or $this for fluent setting.
     */
    public function args(array $value = null) {
        if ($value !== null) {
            $this->args = $value;
            return $this;
        }
        return $this->args;
    }

    /**
     * Get or set the command name associated with the args.
     *
     * @param string $value Set a new command or pass null to get the current command.
     * @return Args|string Returns the current command or $this for fluent setting.
     */
    public function command($value = null) {
        if ($value !== null) {
            $this->command = $value;
            return $this;
        }
        return $this->command;
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
     * @return Args Returns $this for fluent setting.
     */
    public function setMeta($name, $value) {
        $this->meta[$name] = $value;
        return $this;
    }

    /**
     * Gets or sets the entire options array.
     *
     * @param array $value Pass an array to set a new options array.
     * @return Args|array Returns either the current options array or $this for fluent setting.
     */
    public function opts(array $value = null) {
        if ($value !== null) {
            $this->opts = $value;
            return $this;
        }
        return $this->opts;
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
     * Set an option.
     *
     * @param string $option The name of the option.
     * @param mixed $value The value of the option.
     */
    public function setOpt($option, $value) {
        if ($value === null) {
            unset($this->opts[$option]);
        }
        $this->opts[$option] = $value;
    }

    /**
     * Return the json serializable data for the args.
     *
     * @return array Reurns an array of data that can be used to serialize the args to json.
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
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset) {
        return isset($this->opts[$offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
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
}
