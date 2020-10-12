<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\App;

use Garden\Cli\Args;
use Garden\Cli\Cli;
use Garden\Container\Container;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * An opinionated CLI application class to reduce boilerplate.
 */
class CliApplication {
    public const META_ACTION = 'action';

    public const META_DISPATCH_TYPE = 'dispatchType';
    public const META_DISPATCH_VALUE = 'dispatchValue';

    public const TYPE_CALL = 'call';
    public const TYPE_PARAMETER = 'parameter';
    const ALLOWED_TYPES = ['int', 'string', 'bool', 'array'];

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Cli
     */
    private $cli;

    /**
     * @var DocBlockFactory
     */
    private $factory;

    /**
     * @return Container
     */
    protected function createContainer(): Container {
        $dic = new Container();

        $dic->rule(LoggerInterface::class)
            ->setClass(NullLogger::class);

        return $dic;
    }

    /**
     * Get the container used for instantiating objects.
     *
     * @return Container
     */
    public final function getContainer(): Container {
        if ($this->container === null) {
            $this->container = $this->createContainer();
        }
        return $this->container;
    }

    /**
     * Get the CLI object used to parse CLI args.
     *
     * @return Cli
     */
    public final function getCli(): Cli {
        if ($this->cli === null) {
            $this->cli = $this->createCli();
        }
        return $this->cli;
    }

    /**
     * Create and configure the the `Cli` object used for parsing CLI args.
     *
     * This is a good method to override if you want to configure your `Cli` in a subclass.
     *
     * @return Cli
     */
    protected function createCli(): Cli {
        $cli = $this->getContainer()->get(Cli::class);
        return $cli;
    }

    /**
     * Run the program.
     *
     * @param array $argv Command line arguments.
     * @return int Returns the integer result of the command which should be propagated back to the command line.
     */
    public function main(array $argv): int {
        $args = $this->getCli()->parse($argv);

        try {
            $argsBak = $this->getContainer()->hasInstance(Args::class) ? $this->getContainer()->get(Args::class) : null;
            // Set the args in the container so they can be injected into classes.
            $this->getContainer()->setInstance(Args::class, $args);

            $action = $this->route($args);
            $r = $this->dispatch($action);

            return is_int($r) ? $r : 0;
        } catch (\Exception $ex) {
            /* @var LoggerInterface $log */
            $log = $this->container->get(LoggerInterface::class);
            $log->error($ex->getMessage());
            return $ex->getCode();
        } finally {
            $this->getContainer()->setInstance(Args::class, $argsBak);
        }
    }

    /**
     * Add a method to the application.
     *
     * The method will be reflected and its parameters will be added as opts. object setters can also be mapped.
     *
     * @param string $className The name of the class that has the method.
     * @param string $methodName The name of the method.
     * @param array $options Options to modify the behavior of the reflection.
     * @return $this
     */
    public function addMethod(string $className, string $methodName, array $options = []): self {
        $options += [
            'command' => Identifier::fromCamel($methodName)->toKebab(),
            'setters' => true,
        ];

        $class = new \ReflectionClass($className);
        $method = new \ReflectionMethod($className, $methodName);

        try {
            $methodDoc = $this->docBlocks()->create($method);
            $description = $methodDoc->getSummary();
        } catch (\Exception $ex) {
            $description = 'No description available.';
        }
        $this
            ->getCli()
            ->command($options['command'])
            ->description($description)
            ->meta(self::META_ACTION, "$className::$methodName")
        ;

        if ($options['setters']) {
            $setterFilter = [$this, $method->isStatic() ? 'staticSetterFilter': 'setterFilter'];

            $this->addSetters($class, $setterFilter);
        }

        $this->addParams($method);

        return $this;
    }

    /**
     * Route parsed command line arguments to an action.
     *
     * @param Args $args The args to route.
     * @return Args Returns a copy of `$args` ready for dispatching.
     */
    protected function route(Args $args): Args {
        $schema = $this->getCli()->getSchema($args->getCommand());

        if (null === $schema->getMeta(self::META_ACTION)) {
            throw new \InvalidArgumentException("The args don't specify an action to route to.");
        }
        $result = clone $args;
        return $result;
    }

    /**
     * Dispatch a routed set of args to their action and return the result.
     *
     * @param Args $args The args to dispatch.
     * @return mixed Returns the result of the dispatched method.
     */
    protected function dispatch(Args $args) {
        $schema = $this->getCli()->getSchema($args->getCommand());

        if (null === $schema->getMeta(self::META_ACTION)) {
            throw new \InvalidArgumentException("The args don't specify an action to dispatch to.");
        }

        $action = $schema->getMeta(self::META_ACTION);

        if (is_string($action) && preg_match('`^([\a-z0-9_]+)::([a-z0-9_]+)$`i', $action, $m)) {
            $className = $m[1];
            $methodName = $m[2];

            $method = new \ReflectionMethod($className, $methodName);

            if ($method->isStatic()) {
                $obj = $className;
            } else {
                $obj = $this->getContainer()->get($className);
            }

            // Go through the opts, gather the parameters and call setters on the object.
            $optParams = [];
            foreach ($schema->getOpts() as $opt) {
                switch ($opt->getMeta(self::META_DISPATCH_TYPE)) {
                    case self::TYPE_CALL:
                        if ($args->hasOpt($opt->getName())) {
                            call_user_func(
                                [$obj, $opt->getMeta(self::META_DISPATCH_VALUE)],
                                $args->getOpt($opt->getName())
                            );
                        }
                        break;
                    case self::TYPE_PARAMETER:
                        if ($args->hasOpt($opt->getName())) {
                            $optParams[strtolower($opt->getMeta(self::META_DISPATCH_VALUE))] =
                                $args->getOpt($opt->getName());
                        }
                        break;
                }
            }

            $result = $this->getContainer()->call([$obj, $methodName], $optParams);
        } else {
            throw new \InvalidArgumentException("Invalid action: ".$action, 400);
        }

        return $result;
    }

    /**
     * Reflect and add object setters.
     *
     * @param \ReflectionClass $class The class to add the setters for.
     * @param callable $filter A filter that will determine if a method is a setter.
     */
    protected final function addSetters(\ReflectionClass $class, callable $filter = null): void {
        /**
         * @var  string $optName
         * @var  \ReflectionMethod $method
         */
        foreach ($this->reflectSetters($class, $filter) as $optName => $method) {
            $param = $method->getParameters()[0];
            $type = $param->hasType() ? $param->getType()->getName() : '';

            if (!empty($method->getDocComment())) {
                $doc = $this->docBlocks()->create($method);
                $description = $doc->getSummary();
            } else {
                $description = '';
            }
            $this->getCli()->opt(
                $optName,
                $description,
                false,
                $type,
                [
                    self::META_DISPATCH_TYPE => self::TYPE_CALL,
                    self::META_DISPATCH_VALUE => $method->getName(),
                ]
            );
        }
    }

    /**
     * Filter static setters.
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    protected final function staticSetterFilter(\ReflectionMethod $method): bool {
        if (!$method->isStatic()) {
            return false;
        } else {
            return $this->setterFilter($method);
        }
    }

    /**
     * Filter a setter based on whether it begins with "set".
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    protected final function setterFilter(\ReflectionMethod $method): bool {
        $name = $method->getName();
        if (strlen($name) <= 3 ||
            substr($name, 0, 3) !== 'set' ||
            $method->getNumberOfParameters() !== 1
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Reflect all of the setters on a class and yield them.
     *
     * @param \ReflectionClass $class The class to reflect.
     * @param callable|null $filter A filter used to determine whether or not a method qualifies as a setter.
     * @return iterable Returns an iterator in the form: `$optName => $reflectionMethod`.
     */
    protected final function reflectSetters(\ReflectionClass $class, callable $filter = null): iterable {
        if ($filter === null) {
            $filter = [$this, 'setterFilter'];
        }

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (!$filter($method)) {
                continue;
            }
            $param = $method->getParameters()[0];
            if (null === $this->allowedType($param)) {
                continue;
            }
            $optName = Identifier::fromPascal(substr($name, 3))->toKebab();
            yield $optName => $method;
        }
    }

    /**
     * @return DocBlockFactory
     */
    protected final function docBlocks(): DocBlockFactory {
        if ($this->factory === null) {
            $this->factory = DocBlockFactory::createInstance();
        }
        return $this->factory;
    }

    /**
     * Add a method's parameters to the current command.
     *
     * @param \ReflectionMethod $method
     */
    private function addParams(\ReflectionMethod $method) {
        if (!empty($method->getDocComment())) {
            $paramTags = $this->docBlocks()->create($method)->getTagsByName('param');
        } else {
            $paramTags = [];
        }
        /** @var Param[] $docs */
        $docs = [];
        foreach ($paramTags as $tag) {
            /** @var Param $tag */
            $docs[$tag->getVariableName()] = $tag;
        }

        foreach ($method->getParameters() as $param) {
            $type = $this->allowedType($param);
            if ($type === null) {
                continue;
            }
            $doc = $docs[$param->getName()] ?? null;
            if ($doc !== null && null !== $doc->getDescription()) {
                $description = (string)$doc->getDescription();
            }

            $this->getCli()->opt(
                Identifier::fromMixed($param->getName())->toKebab(),
                $description ?? '',
                !($param->isOptional() || $param->isDefaultValueAvailable()),
                $type,
                [
                    self::META_DISPATCH_TYPE => self::TYPE_PARAMETER,
                    self::META_DISPATCH_VALUE => $param->getName(),
                ]
            );
        }

    }

    /**
     * @param \ReflectionParameter $param
     * @return string|null
     */
    private function allowedType(\ReflectionParameter $param): ?string {
        if ($param->getClass()) {
            return null;
        } elseif (null === $param->getType()) {
            return '';
        } elseif (in_array($param->getType()->getName(), self::ALLOWED_TYPES)) {
            return $param->getType()->getName();
        } else {
            return null;
        }
    }
}
