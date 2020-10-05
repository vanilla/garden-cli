<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Schema;


use Garden\Cli\Cli;

class OptSchema implements \JsonSerializable {
    use MetaTrait;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $short;

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $array;

    /**
     * @var bool
     */
    private $required;

    public function __construct(string $name, string $description, bool $required = false, string $type = '', array $meta = []) {
        // Break the name up into its long and short form.
        [$long, $short] = explode(':', $name, 2) + ['', ''];
        $this->setName($long);
        $this->setShortName($short);

        $this->setDescription($description);
        $this->setRequired($required);
        $this->setType($type);

        $this->setMetaArray($meta);
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description) {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getShortName(): string {
        return $this->short;
    }

    /**
     * @param string $short
     * @return $this
     */
    public function setShortName(string $short) {
        $this->short = $short;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type ?: Cli::TYPE_STRING;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type) {
        if (substr($type, -2) === '[]') {
            $this->setArray(true);
            $type = substr($type, 0, -2);
        } else {
            $this->setArray(false);
        }

        switch ($type) {
            case 'str':
            case Cli::TYPE_STRING:
                $this->type = Cli::TYPE_STRING;
                break;
            case 'bool':
            case Cli::TYPE_BOOLEAN:
                $this->type = Cli::TYPE_BOOLEAN;
                break;
            case 'int':
            case Cli::TYPE_INTEGER:
                $this->type = Cli::TYPE_INTEGER;
                break;
            default:
                throw new \InvalidArgumentException("Invalid type: $type. Must be one of string, boolean, or integer.", 422);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isArray(): bool {
        return $this->array;
    }

    /**
     * @param bool $isArray
     * @return $this
     */
    public function setArray(bool $isArray) {
        $this->array = $isArray;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool {
        return $this->required;
    }

    /**
     * @param bool $required
     * @return $this
     */
    public function setRequired(bool $required) {
        $this->required = $required;
        return $this;
    }

    public function merge(OptSchema $opt): void {
        $this->name = $opt->name;
        $this->short = $opt->short;
        $this->description = $opt->description;
        $this->required = $opt->required;
        $this->type = $opt->type;
        $this->array = $opt->array;
        $this->mergeMetaArray($opt->getMetaArray());
    }

    public function jsonSerialize() {
        $vars = get_object_vars($this);
        return $vars;
    }
}
