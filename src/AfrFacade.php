<?php

namespace Autoframe\Components\SocketCache;

use Closure;
use Autoframe\Components\Exception\AfrException;

abstract class AfrFacade
{

    /**
     * The application instance being facaded.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected static $app;

    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static array $resolvedInstance;

    /**
     * Run a Closure when the facade has been resolved.
     *
     * @param Closure $callback
     * @return void
     * @throws AfrException
     */
    public static function resolved(Closure $callback)
    {
        $accessor = static::getFacadeAccessor();

        if (static::$app->resolved($accessor) === true) {
            $callback(static::getFacadeRoot());
        }

        static::$app->afterResolving($accessor, function ($service) use ($callback) {
            $callback($service);
        });
    }


    /**
     * Hotswap the underlying instance behind the facade.
     *
     * @param mixed $instance
     * @return void
     * @throws AfrException
     */
    public static function swap($instance)
    {
        static::$resolvedInstance[static::getFacadeAccessor()] = $instance;

        if (isset(static::$app)) {
            static::$app->instance(
                static::getFacadeAccessor(), $instance
            );
        }
    }

    /**
     * Get the root object behind the facade.
     *
     * @return mixed
     * @throws AfrException
     */
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     * @throws AfrException
     */
    protected static function getFacadeAccessor(): string
    {
        throw new AfrException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param  object|string  $name
     * @return mixed
     */
    protected static function resolveFacadeInstance($name)
    {
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        if (static::$app) {
            return static::$resolvedInstance[$name] = static::$app[$name];
        }
    }

    /**
     * Clear a resolved facade instance.
     *
     * @param string $name
     * @return void
     */
    public static function clearResolvedInstance(string $name)
    {
        unset(static::$resolvedInstance[$name]);
    }

    /**
     * Clear all the resolved instances.
     *
     * @return void
     */
    public static function clearResolvedInstances()
    {
        static::$resolvedInstance = [];
    }

    /**
     * Get the application instance behind the facade.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    public static function getFacadeApplication()
    {
        return static::$app;
    }

    /**
     * Set the application instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws AfrException
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new AfrException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}
