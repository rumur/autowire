<?php

namespace Rumur\Autowiring;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Class Autowire.
 */
class Autowire
{
    /**
     * Holds the list of arguments, that will be passed to an instance during
     * resolve step.
     *
     * @var array<int|string, mixed>
     */
    protected array $args = [];

    /**
     * Resolved instances.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Bind map holds the list of classes that need to be instantiated.
     * The key is an interface and a value is the realization of it.
     *
     * @var array<string, mixed>
     */
    protected array $bound = [];

    /**
     * Instances that should be treated as singletons.
     *
     * @var array<string, boolean>
     */
    protected array $singletons = [];

    /**
     * Self Factory method.
     *
     * @param array<string, mixed> $binds The collection of binds abstracts.
     * @param array<string, mixed> $singletons The collection of singleton abstracts.
     *
     * @return static   The new instance of the auto wiring.
     */
    public static function create(array $binds = [], array $singletons = [])
    {
        $instance = new static();

        foreach ($binds as $abstract => $concrete) {
            $instance->bind($abstract, $concrete);
        }

        // Registering itself as a singleton,
        // in order to be able to autowire the same instance of itself
        // in cases where it's being requested.
        $singletons = array_merge($singletons, [ static::class => fn() => $instance ]);

        foreach ($singletons as $abstract => $concrete) {
            $instance->singleton($abstract, $concrete);
        }

        return $instance;
    }

    /**
     * Binds the abstracts with their implementations as singleton.
     *
     * @param string $abstract The abstract key.
     * @param \Closure|string|null $concrete The class of the implementation.
     *
     * @return $this
     */
    public function singleton(string $abstract, $concrete = null)
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * Binds the abstracts with their implementations.
     *
     * @param string $abstract The abstract key.
     * @param \Closure|string $concrete The class of the implementation.
     * @param bool $singleton Optional. Marks class as singleton.
     *
     * @return $this
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false)
    {
        if (null === $concrete) {
            $concrete = $abstract;
        }

        if ($singleton) {
            $this->singletons[ $abstract ] = true;
        }

        $this->bound[ $abstract ] = $concrete;

        return $this;
    }

    /**
     * Makes an instance out of the abstract.
     *
     * @param string|callable $abstract Desired class for autowiring.
     * @param array $args Optional. The list arguments to override or build with.
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function make($abstract, array $args = [])
    {
        return $this->resolve($abstract, $args);
    }

    /**
     * Calls a Class method or a Closure by instantiating all its dependencies.
     *
     * @param callable $cb The Closure or a class method that needs to be instantiated automatically.
     * @param array $args <string, mixed>   The arguments that need to be overwritten instead of autowiring.
     *
     * @return false|mixed  The callable result or false on error.
     * @throws ReflectionException  When it's not possible to reflect the callable instance.
     */
    public function call(callable $cb, array $args = [])
    {
        if (( is_string($cb) && function_exists($cb) ) || $cb instanceof \Closure) {
            $reflection = new ReflectionFunction($cb);
        } else {
            $obj = $cb;
            $method = '__invoke';

            if (is_array($cb)) {
                [ $obj, $method ] = $cb;
            }

            if (is_string($cb)) {
                [ $obj, $method ] = explode('::', $cb);
            }

            $reflection = new \ReflectionMethod($obj, $method);
        }

        $this->setParamsBeforeBuild($args);

        $dependencies = $reflection->getParameters();

        $parameters = $this->resolveDependencies($dependencies);

        $this->discardParamsAfterBuild();

        return call_user_func_array($cb, $parameters);
    }

    /**
     * Resolves the abstract.
     * It checks if the abstract is registered as a singleton,
     * if so it returns already instantiated or creates a new one.
     *
     * @param string|callable $abstract Desired class for autowiring.
     * @param array $args Optional.The list of arguments to override or build with.
     *
     * @return mixed
     * @throws ReflectionException
     */
    protected function resolve($abstract, array $args)
    {
        // If bound was marked as a singleton & it's been already instantiated,
        // just return instantiated version of it.
        if (isset($this->singletons[ $abstract ], $this->instances[ $abstract ])) {
            return $this->instances[ $abstract ];
        }

        $this->setParamsBeforeBuild($args);

        $this->instances[ $abstract ] = $this->build($abstract);

        $this->discardParamsAfterBuild();

        return $this->instances[ $abstract ];
    }

    /**
     * The build process for an abstract instance.
     *
     * @param string|callable $abstract The bound variable or just an abstract one that needs to be build.
     *
     * @return mixed
     *
     * @throws ReflectionException In case if either the abstract or its dependencies are impossible to reflect.
     * @throws Exceptions\NotInstantiable In case if an abstract is a class and this class is not instantiable.
     */
    protected function build($abstract)
    {
        $concrete = $this->bound[ $abstract ] ?? $abstract;

        // If we encounter a Closure, it means developer has specified the build process.
        // In this case we need to pass desired args to this Closure and return what it returns.
        if ($concrete instanceof \Closure) {
            $closure = new ReflectionFunction($concrete);

            $dependencies = $closure->getParameters();

            $parameters = $this->resolveDependencies($dependencies);

            return call_user_func_array($concrete, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (! $reflector->isInstantiable()) {
            throw Exceptions\NotInstantiable::default($abstract);
        }

        $constructor = $reflector->getConstructor();

        // It means that the class doesn't have a constructor
        // So we can just return the instance right away.
        if (null === $constructor) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();

        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Sets arguments for a current build.
     *
     * @param array $args Build arguments.
     */
    protected function setParamsBeforeBuild(array $args): void
    {
        $this->args[] = $args;
    }

    /**
     * Discards passed arguments for a current build after it gets built.
     *
     * @return void
     */
    protected function discardParamsAfterBuild(): void
    {
        array_pop($this->args);
    }

    /**
     * @param ReflectionParameter[] $dependencies Collection of dependencies.
     *
     * @return array
     * @throws Exceptions\NotInstantiable In case if it's impossible to instantiate a dependency.
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if ($this->hasOverrideParam($dependency)) {
                if ($dependency->isVariadic()) {
                    $variadic = (array) $this->getOverrideParam($dependency);
                    array_push($results, ...$variadic);
                } else {
                    $results[] = $this->getOverrideParam($dependency);
                }

                continue;
            }

            if (null === $this->dependencyToParamName($dependency)) {
                $result = $this->resolvePrimitive($dependency);
            } else {
                $result = $this->resolveClass($dependency);
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Checks whether a dependency param has an alternative passed by the dev.
     *
     * @param ReflectionParameter $dependency The dependency parameter.
     *
     * @return bool True if there is an arg to override.
     */
    protected function hasOverrideParam(ReflectionParameter $dependency): bool
    {
        return array_key_exists($dependency->name, $this->getOverrideParamLatest());
    }

    /**
     * Gets an alternative param passed by the dev.
     *
     * @param ReflectionParameter $dependency The dependency parameter.
     *
     * @return mixed
     */
    protected function getOverrideParam(ReflectionParameter $dependency)
    {
        return $this->getOverrideParamLatest()[ $dependency->name ];
    }

    /**
     * Safely provides params.
     *
     * @return array
     */
    protected function getOverrideParamLatest(): array
    {
        return count($this->args) ? end($this->args) : [];
    }

    /**
     * Resolves primitive dependencies.
     *
     * @param ReflectionParameter $dependency Primitive dependency.
     *
     * @return mixed
     * @throws Exceptions\NotInstantiable In case if it's impossible to resolve the primitive.
     */
    protected function resolvePrimitive(ReflectionParameter $dependency)
    {
        if ($dependency->isDefaultValueAvailable()) {
            return $dependency->getDefaultValue();
        }

        if ($dependency->allowsNull()) {
            return null;
        }

        throw Exceptions\NotInstantiable::primitive($dependency->name);
    }

    /**
     * Resolves the class instance along with its dependencies.
     *
     * @param ReflectionParameter $dependency The dependency class.
     *
     * @return callable|mixed|null
     * @throws Exceptions\NotInstantiable In case if it's impossible to instantiate the class.
     */
    protected function resolveClass(ReflectionParameter $dependency)
    {
        try {
            return $this->make($this->dependencyToParamName($dependency));
        } catch (InvalidArgumentException | ReflectionException $e) {
            if ($dependency->isDefaultValueAvailable()) {
                return $dependency->getDefaultValue();
            }

            if ($dependency->allowsNull()) {
                return null;
            }

            throw Exceptions\NotInstantiable::class($dependency->name);
        }
    }

    /**
     * Converts the dependency to a parameter name.
     *
     * @param ReflectionParameter $dependency The dependency parameter.
     *
     * @return string|null
     */
    protected function dependencyToParamName(ReflectionParameter $dependency): ?string
    {
        $type = $dependency->getType();

        // If the parameter doesn't have a type, there is nothing we can do.
        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name  = $type->getName();
        $class = $dependency->getDeclaringClass();

        if (null !== $class) {
            if ('self' === $name) {
                return $class->getName();
            }

            if ('parent' === $name) {
                $parent = $class->getParentClass();

                if ($parent) {
                    return $parent->getName();
                }
            }
        }

        return $name;
    }
}
