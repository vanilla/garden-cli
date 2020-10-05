<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\App;

use Garden\Cli\Args;
use Garden\Cli\Cli;
use Garden\Cli\Schema\OptSchema;
use Garden\Container\Container;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 *
 */
class CliApplication {
    public const META_ACTION = 'action';

    public const META_DISPATCH_TYPE = 'dispatchType';
    public const META_DISPATCH_NAME = 'dispatchName';

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

    protected function createContainer(): Container {
        return new Container();
    }

    protected function getContainer(): Container {
        if ($this->container === null) {
            $this->container = $this->createContainer();
        }
        return $this->container;
    }

    public function getCli(): Cli {
        if ($this->cli === null) {
            $this->cli = $this->createCli();
        }
        return $this->cli;
    }

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
            $oldArgs = $this->getContainer()->hasInstance(Args::class) ? $this->getContainer()->get(Args::class) : null;
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
            $this->getContainer()->setInstance(Args::class, $oldArgs);
        }
    }

    public function addMethod(string $className, string $methodName, array $options = []) {
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
            $this->addSetters($class);
        }

        $this->addArgs($method);
    }

    protected function route(Args $args): Args {
        if (null === $args->getMeta(self::META_ACTION)) {
            throw new \InvalidArgumentException("The args don't specify an action to route to.");
        }
        $result = clone $args;
        return $result;
    }

    protected function dispatch(Args $args): int {
        if (null === $args->getMeta(self::META_ACTION)) {
            throw new \InvalidArgumentException("The args don't specify an action to dispatch to.");
        }

        $schema = $this->getCli()->getSchema($args->getCommand());
        $action = $args->getMeta(self::META_ACTION);

        if (is_string($action) && preg_match('`^([a-z0-9_]+)::([a-z0-9_])$`i', $action, $m)) {
            $className = $m[1];
            $methodName = $m[2];

            $obj = $this->getContainer()->get($className);

            /**
             * @var  string $optName
             * @var  \ReflectionMethod $method
             */
            foreach ($this->reflectSetters(new \ReflectionClass($obj)) as $optName => $method) {
                if ($args->hasOpt($optName)) {
                    $method->invoke($obj, [$args->getOpt($optName)]);
                }
            }

        }
    }

    /**
     * @param \ReflectionClass $class
     */
    protected final function addSetters(\ReflectionClass $class): void {
        /**
         * @var  string $optName
         * @var  \ReflectionMethod $method
         */
        foreach ($this->reflectSetters($class) as $optName => $method) {
            $param = $method->getParameters()[0];
            $type = $param->hasType() ? $param->getType()->getName() : '';
            $doc = $this->docBlocks()->create($method);
            $this->getCli()->opt(
                $optName,
                $doc->getSummary(),
                false,
                $type,
                [
                    self::META_DISPATCH_TYPE => self::TYPE_CALL,
                    self::META_DISPATCH_NAME => $method->getName(),
                ]
            );
        }
    }

    protected final function reflectSetters(\ReflectionClass $class): iterable {
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (strlen($name) <= 3 ||
                substr($name, 0, 3) !== 'set' ||
                $method->getNumberOfParameters() !== 1
            ) {
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

    private function addArgs(\ReflectionMethod $method) {
        $paramTags = $this->docBlocks()->create((string)$method->getDocComment())->getTagsByName('param');
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

//            $optional = $param->isOptional();
//            $hasDefault = $param->isDefaultValueAvailable();
//            $default = $param->getDefaultValue();

            $this->getCli()->opt(
                Identifier::fromMixed($param->getName())->toKebab(),
                $description ?? '',
                !($param->isOptional() || $param->isDefaultValueAvailable()),
                $type,
                [
                    self::META_DISPATCH_TYPE => self::TYPE_PARAMETER,
                    self::META_DISPATCH_NAME => $param->getName(),
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
