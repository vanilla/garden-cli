<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Schema;

use Garden\Cli\Cli;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A data class for the schema of a single command line opt.
 */
class OptSchema implements JsonSerializable
{
    use MetaTrait;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $description;

    /**
     * @var string
     */
    private string $short;

    /**
     * @var string
     */
    private string $type;

    /**
     * @var bool
     */
    private bool $array;

    /**
     * @var bool
     */
    private bool $required;

    /**
     * OptSchema constructor.
     *
     * @param string $name The name of the opt in the form: `"$longName"` or `"$longName:$shortName"`.
     * @param string $description The description of the opt for users.
     * @param bool $required Whether or not the opt is required.
     * @param string $type The data type of the opt.
     * @param array $meta Additional meta information for the opt.
     */
    public function __construct(
        string $name,
        string $description,
        bool $required = false,
        string $type = "",
        array $meta = []
    ) {
        // Break the name up into its long and short form.
        [$long, $short] = explode(":", $name, 2) + ["", ""];
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the long name of the opt.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the description of the opt.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set the description of the opt.
     *
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the short name of the opt.
     *
     * @return string
     */
    public function getShortName(): string
    {
        return $this->short;
    }

    /**
     * Set the short name of the opt.
     *
     * @param string $short
     * @return $this
     */
    public function setShortName(string $short): static
    {
        $this->short = $short;
        return $this;
    }

    /**
     * Get the type of the opt.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type ?: Cli::TYPE_STRING;
    }

    /**
     * Set the type of the opt.
     *
     * @param string $type
     * @return $this
     */
    public function setType(string $type): static
    {
        if (str_ends_with($type, "[]")) {
            $this->setArray(true);
            $type = substr($type, 0, -2);
        } else {
            $this->setArray(false);
        }

        $this->type = match ($type) {
            "str", Cli::TYPE_STRING => Cli::TYPE_STRING,
            "bool", Cli::TYPE_BOOLEAN => Cli::TYPE_BOOLEAN,
            "int", Cli::TYPE_INTEGER => Cli::TYPE_INTEGER,
            default => throw new InvalidArgumentException(
                "Invalid type: $type. Must be one of string, boolean, or integer.",
                422
            ),
        };

        return $this;
    }

    /**
     * Whether or not this is an array opt.
     *
     * @return bool
     */
    public function isArray(): bool
    {
        return $this->array;
    }

    /**
     * Set whether or not this is an array opt.
     *
     * @param bool $isArray
     * @return $this
     */
    public function setArray(bool $isArray): static
    {
        $this->array = $isArray;
        return $this;
    }

    /**
     * Whether or not the opt is required.
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Set whether or not the opt is required.
     *
     * @param bool $required
     * @return $this
     */
    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Merge another opt schema into this one.
     *
     * @param OptSchema $opt
     */
    public function merge(OptSchema $opt): void
    {
        $this->name = $opt->name;
        $this->short = $opt->short;
        $this->description = $opt->description;
        $this->required = $opt->required;
        $this->type = $opt->type;
        $this->array = $opt->array;
        $this->mergeMetaArray($opt->getMetaArray());
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        return $vars;
    }
}
