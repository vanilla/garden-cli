<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Application;

use Exception;
use Garden\Cli\Args;
use Garden\Cli\Cli;
use Garden\Cli\Schema\OptSchema;
use Garden\Container\Container;
use Garden\Container\Reference;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * An opinionated CLI application class to reduce boilerplate.
 */
class CliApplication extends Cli {
    public const META_ACTION = 'action';

    public const META_DISPATCH_TYPE = 'dispatchType';
    public const META_DISPATCH_VALUE = 'dispatchValue';

    public const TYPE_CALL = 'call';
    public const TYPE_PARAMETER = 'parameter';
    const ALLOWED_TYPES = ['int', 'string', 'bool', 'array'];
    const OPT_COMMAND = 'command';
    const OPT_SETTERS = 'setters';
    const OPT_DESCRIPTION = 'description';
    const OPT_PREFIX = 'prefix';

    /**
     * @var Container
     */
    private $container;

    /**
     * @var DocBlockFactory
     */
    private $factory;

    /**
     * CliApplication constructor.
     */
    public function __construct() {
        $this->configureCli();
    }

    /**
     * Configure the CLI for usage.
     */
    protected function configureCli(): void {
    }

    /**
     * Get the container used for instantiating objects.
     *
     * @return Container
     */
    final public function getContainer(): Container {
        if ($this->container === null) {
            $this->container = $this->createContainer();
            $this->configureContainer();
            $this->container->setInstance(Container::class, $this->container);
        }
        return $this->container;
    }

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
     * Configure the Container for usage.
     */
    protected function configureContainer(): void {
    }

    /**
     * Run the program.
     *
     * @param array $argv Command line arguments.
     * @return int Returns the integer result of the command which should be propagated back to the command line.
     */
    public function main(array $argv): int {
        $args = $this->parse($argv);

        try {
            $action = $this->route($args);
            $r = $this->dispatch($action);
        } catch (Exception $ex) {
            /* @var LoggerInterface $log */
            $log = $this->container->get(LoggerInterface::class);
            $log->error($ex->getMessage());
            $r = $ex->getCode();
        }
        return is_numeric($r) ? (int)$r : 0;
    }

    /**
     * Route parsed command line arguments to an action.
     *
     * @param Args $args The args to route.
     * @return Args Returns a copy of `$args` ready for dispatching.
     */
    protected function route(Args $args): Args {
        $schema = $this->getSchema($args->getCommand());

        if (null === $schema->getMeta(self::META_ACTION)) {
            throw new InvalidArgumentException("The args don't specify an action to route to.");
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
    public function dispatch(Args $args) {
        try {
            $argsBak = $this->getContainer()->hasInstance(Args::class) ? $this->getContainer()->get(Args::class) : null;
            // Set the args in the container so they can be injected into classes.
            $this->getContainer()->setInstance(Args::class, $args);

            $schema = $this->getSchema($args->getCommand());

            if (null === $schema->getMeta(self::META_ACTION)) {
                throw new InvalidArgumentException("The args don't specify an action to dispatch to.");
            }

            $action = $schema->getMeta(self::META_ACTION);

            if (is_string($action) && preg_match('`^([\a-z0-9_]+)::([a-z0-9_]+)$`i', $action, $m)) {
                /** @psalm-var class-string $className */
                $className = $m[1];
                $methodName = $m[2];

                $method = new ReflectionMethod($className, $methodName);

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
                throw new InvalidArgumentException("Invalid action: " . $action, 400);
            }

            return $result;
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
     * @psalm-param class-string $className
     * @param string $methodName The name of the method.
     * @param array $options Options to modify the behavior of the reflection.
     * @return $this
     */
    public function addMethod(string $className, string $methodName, array $options = []): self {
        $options += [
            self::OPT_COMMAND => Identifier::fromCamel($methodName)->toKebab(),
            self::OPT_SETTERS => false,
            self::OPT_DESCRIPTION => null,
        ];

        $class = new ReflectionClass($className);
        $method = new ReflectionMethod($className, $methodName);
        $description = $this->reflectDescription($method, $options['description']);

        $this
            ->command($options[self::OPT_COMMAND])
            ->description($description)
            ->meta(self::META_ACTION, $method->getDeclaringClass()->getName() . '::' . $method->getName());
        $this->addParams($method);

        if ($options[self::OPT_SETTERS]) {
            $setterFilter = [$this, $method->isStatic() ? 'staticSetterFilter' : 'setterFilter'];

            $this->addSetters($class, $setterFilter);
        }

        return $this;
    }

    /**
     * Map an object's constructor arguments to opts.
     *
     * This method will reflect an object's constructor and create opts for them. Any passed opts
     * will be configured on the container so that instances can be configured from the command line.
     *
     * TLDR: If you want to allow dependencies to be controlled from the command line then this is your method.
     *
     * @param string $className
     * @psalm-param class-string $className
     * @param array $options
     * @return $this
     */
    public function addConstructor(string $className, array $options = []): self {
        $options += [
            self::OPT_PREFIX => '',
        ];

        if ($this->getContainer()->hasInstance($className)) {
            throw new InvalidArgumentException("Cannot add a constructor for a class that has been instantiated: $className");
        }

        $method = (new ReflectionClass($className))->getConstructor();
        if ($method === null) {
            throw new InvalidArgumentException("Class does not have a constructor: $className", 400);
        }
        $args = [];
        $params = $this->reflectParams($method, $options);
        /**
         * @var OptSchema $opt
         * @var ReflectionParameter $params
         */
        foreach ($params as $optName => [$opt, $param]) {
            /** @var ReflectionParameter $param */
            $args[$param->getName()] = new Reference([Args::class, $optName]);
        }

        $this->getContainer()->rule($className)->setConstructorArgs($args);
        return $this;
    }

    /**
     * Map a class's factory method to the command line.
     *
     * This method takes the name of a class or a container rule and a callable and then makes that callable the factory
     * for that class or rule. All of the factory's parameters are wired up to command opts and the factory is configured
     * on the container.
     *
     * @param string $classOrRule The name of the class or container rule you want to set the factory on.
     * @param callable $factory The factory that returns an instance of the class.
     * @param array $options Additional options for the wiring.
     * @return $this
     */
    public function addFactory(string $classOrRule, callable $factory, array $options = []): self {
        $options += [
            self::OPT_PREFIX => '',
        ];

        $dic = $this->getContainer();
        $realFactory = function () use ($factory, $options, $dic) {
            /** @var Args $opts */
            $opts = $dic->get(Args::class);
            $method = self::reflectCallable($factory);
            $args = [];
            $params = $this->reflectParams($method, $options);
            foreach ($params as $optName => [$opt, $param]) {
                /** @var ReflectionParameter $param */
                $args[$param->getName()] = $opts->get($optName);
            }
            $r = $dic->call($factory, $args);
            return $r;
        };

        $this->getContainer()->rule($classOrRule)->setFactory($realFactory);
        return $this;
    }

    /**
     * Wire up a call to a class method when that class is instantiated.
     *
     * The most common use of `addCall` is wiring up setter injection from the command line. If you want to instantiate
     * a class and then call a setter from a command line opt then this is the method to use.
     *
     * @param string $className The name of the class.
     * @psalm-param class-string $className
     * @param string $methodName The name of the method.
     * @param array $options Additional options to control the wiring.
     * @return $this
     */
    public function addCall(string $className, string $methodName, array $options = []): self {
        $options += [
            self::OPT_PREFIX => '',
        ];

        $args = [];
        $method = new ReflectionMethod($className, $methodName);
        /**
         * @var OptSchema $opt
         * @var ReflectionParameter $param
         */
        foreach ($this->reflectParams($method, $options) as [$opt, $param]) {
            $args[$param->getName()] = new Reference([Args::class, $opt->getName()]);
        }
        $this->getContainer()->rule($className)->addCall($methodName, $args);
        return $this;
    }

    /**
     * Create a `ReflectionFunctionAbstract` from any callable.
     *
     * @param callable $callable The callable to reflect.
     * @return ReflectionFunctionAbstract Returns the reflection primitive.
     */
    private static function reflectCallable(callable $callable): ReflectionFunctionAbstract {
        if (is_array($callable)) {
            /** @psalm-suppress PossiblyInvalidArgument */
            return new ReflectionMethod(...$callable);
        } elseif (is_string($callable) || $callable instanceof \Closure) {
            return new \ReflectionFunction($callable);
        } else {
             return new ReflectionMethod($callable, '__invoke');
        }
    }

    /**
     * Add a closure/function to the application.
     *
     * @param string $command The name of the command to map the callable to.
     * @param callable $callable The callable to reflect.
     * @param array $options Options to modify the behavior of the reflection.
     * @return $this
     */
    public function addCallable(string $command, callable $callable, array $options = []): self {
        $options += [
            self::OPT_DESCRIPTION => null,
        ];

        if (is_array($callable)) {
            throw new InvalidArgumentException(
                "CliApplication::addCallable() does not support methods. Use CliApplication::addMethod() instead.",
                400
            );
        }

        $method = self::reflectCallable($callable);
        $description = $this->reflectDescription($method, $options[self::OPT_DESCRIPTION]);

        $this
            ->command($command)
            ->description($description)
            ->meta(self::META_ACTION, $callable);
        $this->addParams($method);

        return $this;
    }

    /**
     * @return DocBlockFactory
     */
    final protected function docBlocks(): DocBlockFactory {
        if ($this->factory === null) {
            $this->factory = DocBlockFactory::createInstance();
        }
        return $this->factory;
    }

    /**
     * Reflect and add object setters.
     *
     * @param ReflectionClass $class The class to add the setters for.
     * @param callable|null $filter A filter that will determine if a method is a setter.
     */
    final protected function addSetters(ReflectionClass $class, callable $filter = null): void {
        /**
         * @var  string $optName
         * @var  ReflectionMethod $method
         */
        foreach ($this->reflectSetters($class, $filter) as $optName => $method) {
            $param = $method->getParameters()[0];
            if (null === $t = $param->getType()) {
                $type = '';
            } else {
                $type = $t instanceof \ReflectionNamedType ? $t->getName() : (string)$t;
            }

            if (!empty($method->getDocComment())) {
                $doc = $this->docBlocks()->create($method);
                $description = $doc->getSummary();
            } else {
                $description = '';
            }
            $this->opt(
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
     * Reflect all of the setters on a class and yield them.
     *
     * @param ReflectionClass $class The class to reflect.
     * @param callable|null $filter A filter used to determine whether or not a method qualifies as a setter.
     * @return iterable Returns an iterator in the form: `$optName => $reflectionMethod`.
     */
    final protected function reflectSetters(ReflectionClass $class, callable $filter = null): iterable {
        if ($filter === null) {
            $filter = [$this, 'setterFilter'];
        }

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
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
     * Get the allowed opt type from a parameter.
     *
     * @param ReflectionParameter $param
     * @return string|null Returns the name of the allowed type or **null** if the parameter cannot be wired to an opt.
     */
    private function allowedType(ReflectionParameter $param): ?string {
        $type = $param->getType();

        if ($type === null) {
            return '';
        } elseif (!$type->isBuiltin()) {
            return null;
        } else {
            $type = $param->getType();
            $t = $type instanceof \ReflectionNamedType ? $type->getName() : (string)$type;
            if (in_array($t, self::ALLOWED_TYPES)) {
                $t = ['int' => 'integer', 'str' => 'string', 'bool' => 'boolean'][$t] ?? $t;
                return $t;
            }
        }
        return null;
    }

    /**
     * Add a method's parameters to the current command.
     *
     * @param ReflectionFunctionAbstract $method
     * @param array $options
     */
    private function addParams(ReflectionFunctionAbstract $method, array $options = []) {
        $options += [
            self::OPT_PREFIX => '',
        ];

        /**
         * @var OptSchema $opt
         * @var ReflectionParameter $param
         */
        foreach ($this->reflectParams($method) as [$opt, $param]) {
            $opt->setMetaArray([
                self::META_DISPATCH_TYPE => self::TYPE_PARAMETER,
                self::META_DISPATCH_VALUE => $param->getName(),
            ]);
            $this->addOpt($opt);
        }
    }

    /**
     * Reflect the parameters on a method and return the options.
     *
     * @param ReflectionFunctionAbstract $method
     * @param array $options
     * @return array Returns an array of arrays in the form: `[OptSchema, ReflectionParam]`.
     */
    private function reflectParams(ReflectionFunctionAbstract $method, array $options = []): array {
        $options += [
            self::OPT_PREFIX => '',
        ];

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

        $result = [];
        foreach ($method->getParameters() as $param) {
            $type = $this->allowedType($param);
            if ($type === null) {
                continue;
            }
            $doc = $docs[$param->getName()] ?? null;
            if ($doc !== null && null !== $doc->getDescription()) {
                $description = (string)$doc->getDescription();
            }
            $opt = new OptSchema(
                $options[self::OPT_PREFIX].Identifier::fromMixed($param->getName())->toKebab(),
                $description ?? '',
                !($param->isOptional() || $param->isDefaultValueAvailable()),
                $type ?: 'string',
                [
                    self::META_DISPATCH_TYPE => self::TYPE_PARAMETER,
                    self::META_DISPATCH_VALUE => $param->getName(),
                ]
            );
            $result[$opt->getName()] = [$opt, $param];
        }
        return $result;
    }

    /**
     * Filter static setters.
     *
     * @param ReflectionMethod $method
     * @return bool
     */
    final protected function staticSetterFilter(ReflectionMethod $method): bool {
        if (!$method->isStatic()) {
            return false;
        } else {
            return $this->setterFilter($method);
        }
    }

    /**
     * Filter a setter based on whether it begins with "set".
     *
     * @param ReflectionMethod $method
     * @return bool
     */
    final protected function setterFilter(ReflectionMethod $method): bool {
        $name = $method->getName();
        if (strlen($name) <= 3 ||
            substr($name, 0, 3) !== 'set' ||
            strcasecmp($name, 'setup') === 0 ||
            $method->getNumberOfParameters() !== 1
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Reflect a command's description.
     *
     * @param ReflectionFunctionAbstract $method The method
     * @param string|null $setting An explicitly set description that will be used if not null.
     * @return string
     */
    private function reflectDescription(ReflectionFunctionAbstract $method, string $setting = null): string {
        if ($setting === null) {
            try {
                $methodDoc = $this->docBlocks()->create($method);
                $description = $methodDoc->getSummary();
            } catch (Exception $ex) {
                $description = 'No description available.';
            }
        } else {
            $description = $setting;
        }
        return $description;
    }
}
