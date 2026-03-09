<?php

declare(strict_types=1);

namespace Saas\Core;

use ReflectionClass;
use RuntimeException;

class Container
{
    private array $bindings  = [];
    private array $instances = [];

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = ['factory' => $factory, 'singleton' => true];
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = ['factory' => $factory, 'singleton' => false];
    }

    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding  = $this->bindings[$abstract];
            $instance = ($binding['factory'])($this);
            if ($binding['singleton']) {
                $this->instances[$abstract] = $instance;
            }
            return $instance;
        }

        return $this->make($abstract);
    }

    public function make(string $class): mixed
    {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate {$class}");
        }

        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            return $ref->newInstance();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $params[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot resolve parameter {$param->getName()} for {$class}");
            }
        }

        return $ref->newInstanceArgs($params);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
}
