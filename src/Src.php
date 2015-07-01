<?php
/******************************************************************************
 * php-src - A library that handles creation of objects and management of 
 * global services to help you refactor your legacy code base.
 *
 * Copyright (c) 2014 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received 
 * a copy of the along with the code.
 */

namespace Lechimp\Src;

use Closure;
use InvalidArgumentException;

/**
 * Source for services and new objects.
 *
 * A source contains services and factories for object, which are both subsummed 
 * under the name provider. One can register services and factories by name and 
 * later request them.
 *
 * The source is an immutable structure, which means that updates are non
 * distructive but rather result in a new source object. This simplifies the
 * reasoning over the code the source is used in. 
 *
 */
class Src {
    /**
     * Request a service or register a factory for a service.
     *
     * A service is an object that provides, well, some service to an app.
     * Think of database connections, an event system etc. The source tries to
     * construct as little instances of the service as possible.
     *
     * If second argument is omitted, requests a service. Returns a service
     * object then. 
     *
     * If second argument is set, expects a closure that takes the source itself
     * as the first argument. The closure is expected to construct a service 
     * object, where every dependency of the service has to be requested from the
     * source when the service is constructed.
     *
     * Returns an updated version of the source then, which is non destructive
     * to the previous version of the source.
     *
     * If a service with a similar name was registered before, overwrites the
     * previously registered service. If there already exists an instance of the
     * service, the source discards that instances and the instances of all other
     * providers that depend on that service.
     *
     * @param   string          $name
     * @param   Closure|null    $factory 
     * @throws  Exceptions/UnknownService
     * @throws  Exceptions/UnresolvableDependency
     * @return  mixed
     */
    public function service($name, Closure $factory = null) {
        assert(is_string($name));

        if ($factory) {
            return $this->registerService($name, $factory);
        }
        else {
            return $this->requestService($name);
        }
    }

    /**
     * Get a getter for a service.
     *
     * This won't initialize the service directly but rather return an
     * anonymus function that will return the service at a later time.
     * This could be used for service dependencies that will not be needed
     * in every case.
     * 
     * @param   string          $name
     * @throws  InvalidArgumentException    When $name == "Src"
     * @return  Closure 
     */
    public function lazy($name) {
        $this->recordDependency("service::$name");
        return function() use ($name) {
            return $this->service($name);
        };
    }

    /**
     * Get the names of all services known to this source.
     *
     * @return  string[]
     */
    public function services() {
        return $this->providerNames("service");
    }
    

    /**
     * Request or register a factory.
     *
     * A factory is a function that creates new instances of a class.
     *
     * If the second argument is omitted, request a factory for a class. Returns
     * the factory in that case.
     *
     * If the second argument is provided, register a factory for a class. Returns
     * an updated version of the source in that case.
     *
     * If there was no factory registered for a class, throws an UnknownClass 
     * exception unless a default factory was registered.
     * 
     * @param   string          $name
     * @param   Closure|null    $factory 
     * @throws  Exceptions/UnknownClass
     * @return  mixed
     *
     */
    public function factory($name, Closure $factory = null) {
        assert(is_string($name));

        if ($factory) {
            return $this->registerFactory($name, $factory);
        }
        else {
            return $this->requestFactory($name);
        }

    }

    /**
     * Register a default factory for classes.
     *
     * This factory is used, when no factory for a class could be 
     * found. The closure must be a function taking the Src and the class
     * name as arguments, followed by an array of parameters for the class 
     * factory.
     *
     * @param   Closure         $factory
     * @return  Src
     */
    public function defaultFactory(Closure $factory) {
        return $this->newSrc( array()
                            , $factory);
    }

    /**
     * Get the dependencies of a service or a factory.
     *
     * Prefix service names with "service::" and factories with "factory::".
     *
     * As a sideeffect will construct the service or factory in question if 
     * it is not constructed yet.
     *
     * Returns a list of direct dependencies only.
     *
     * @param   string      $name
     * @throws  Exceptions\UnknownService
     * @throws  Exceptions\UnknownClass
     * @return  string[]
     */
    public function dependenciesOf($name) {
        $this->requestProvider($name);
        return $this->dependencies["$name"];
    }

    /*********************
     * Internals 
     *********************/
    // All things providers.
    protected $factories = array();
    protected $instances = array();
    protected $in_construction = array();
    protected $dependencies = array();
    protected $reverse_deps = array();
    protected $default_factory = null;

    // For tracking the tree of sources.
    protected $ancestor = null;
    
    // For recording of dependencies
    protected $records = array();
    protected $paused_records = array();
    protected $internal_records = array();

    // For construction:

    public function __construct( Src $ancestor = null
                               , array $new_providers = array() 
                               , Closure $new_default_factory = null) {
        $this->ancestor = $ancestor;

        $new_provider_names = array_keys($new_providers);
        assert(count($new_provider_names) <= 1);

        $instances = array();
        $dependencies = array();
        $reverse_deps = array();
        foreach ($new_provider_names as $name) {
            if ($ancestor && !array_key_exists($name, $ancestor->reverse_deps)) {
                $reverse_deps[$name] = array();
            }
        }

        if ($ancestor) {
            $this->factories = array_merge($ancestor->factories, $new_providers);
            $this->instances = array_merge($ancestor->instances, $instances);
            $this->dependencies = array_merge($ancestor->dependencies, $dependencies);
            $this->reverse_deps = array_merge($ancestor->reverse_deps, $reverse_deps);
            $this->default_factory = $ancestor->default_factory;
        }

        if ($new_default_factory) {
            $this->default_factory = $new_default_factory;
        }

        if (!empty($new_provider_names)) {
            $this->refreshProvider($new_provider_names[0]);
        }
    }

    // For overloading the construction of new sources in derived classes.
    protected function newSrc( array $new_providers = array()
                             , Closure $new_default_factory = null) {
        return new Src($this, $new_providers, $new_default_factory);
    }

    // For providers: 
    //
    // A provider abstracts over services and factories. This makes it possible
    // to use the same logic for services and factories.

    protected function registerProvider($name, Callable $factory) {
        return $this->newSrc(array($name => $factory));
    }
  
    protected function requestProvider($name) {
        if (!array_key_exists($name, $this->factories)) {
            throw new Exceptions\UnknownService($name);
        }

        $this->recordDependency($name);

        if (array_key_exists($name, $this->instances)) {
            return $this->instances[$name];
        }

        return $this->initializeProvider($name);
    }

    protected function initializeProvider($name) {
        if (array_key_exists($name, $this->in_construction)) {
            throw new Exceptions\UnresolvableDependency($name);
        } 

        // This is for detection of unresolvable dependencies.
        $this->in_construction[$name] = true;

        $token = $this->recordDependenciesInternal();

        $factory = $this->factories[$name];
        $instance = $factory($this);
        $this->instances[$name] = $instance;

        // Dependencies of this provider
        $deps = $this->getDependencyRecordInternal($token);
        $this->dependencies[$name] = $deps;

        // Add this providers as reverse dependency to all it's
        // dependencies
        foreach ($deps as $dep) {
            $this->reverse_deps[$dep][] = $name;
        }

        // Track every provider, that depends on this provider
        $this->reverse_deps[$name] = array();

        unset($this->in_construction[$name]);

        return $instance;
    }

    protected function refreshProvider($name) {
        assert(array_key_exists($name, $this->reverse_deps));
 
        foreach ($this->reverse_deps[$name] as $dep) {
            $this->refreshProvider($dep);
        }
       
        unset($this->instances[$name]);
        $this->dependencies[$name] = array();
        $this->reverse_deps[$name] = array();
    }

    /**
     * Get the names of all known providers.
     *
     * If namespace param is given, will only return the providers
     * in that namespace.
     *
     * @param   string  $namespace
     * @return  string[]
     */
    protected function providerNames($namespace = null) {
        // TODO: maybe cache that stuff.
        $names = array_keys($this->factories);
        if (!$namespace) {
            return $names;
        }
        
        $filtered = array();
        foreach ($names as $name) {
            $matches = array();
            if (preg_match("/^$namespace::(.*)$/", $name, $matches)) {
                $filtered[] = $matches[1];
            }
        }
        return $filtered;
    }

    // For services:   

    protected function registerService($name, Callable $factory) {
        $name = "service::$name";
        return $this->registerProvider($name, $factory);
    }


    protected function requestService($name) {
        $name = "service::$name";
        return $this->requestProvider($name);
    }

    // For factories:

    protected function requestFactory($name) {
        try {
            return $this->requestProvider("factory::$name");
        }
        catch (Exceptions\UnknownService $e) {
            if ($this->default_factory !== null) {
                return $this->getDefaultFactory($name);
            }
            throw new Exceptions\UnknownClass($name);
        }
    }

    protected function registerFactory($name, $factory) {
        $name = "factory::$name";
        return $this->registerProvider($name, function($src) use ($factory){
            return function() use ($src, $factory) {
                $args = array_merge(array($src), func_get_args());
                return call_user_func_array($factory, $args);
            };
        });
    }

    protected function getDefaultFactory($name) {
        return function() use ($name) {
            $args = func_get_args();
            $def = $this->default_factory;
            return $def($this, $name, $args);
        };
    }

    // For recording of dependencies
    //
    // Maybe i should make this public?

    /**
     * Start to record the dependencies that are requested from the Src.
     *
     * Returns a token that could later be used with getDependencyRecord
     * to get an array of the dependencies.
     *
     * @return Internal\RecordToken
     */
    protected function recordDependencies() {
        $token = Internal\RecordToken::get();

        $this->records[$token->value()] = array();        

        return $token;    
    }

    /**
     * Pause the recording for a token.
     *
     * @throws  InvalidArgumentException
     * @param Internal\RecordToken      $token
     */
    protected function pauseDependencyRecord(Internal\RecordToken $token) {
        if (!array_key_exists($token->value(), $this->records)) {
            throw new InvalidArgumentException("Unknown, paused or already used token supplied.");
        }

        $this->paused_records[$token->value()] = $this->records[$token->value()];
        unset($this->records[$token->value()]);
    }
    /**
     * Resume the recording for a token.
     *
     * @throws  InvalidArgumentException
     * @param Internal\RecordToken      $token
     */
    protected function resumeDependencyRecord(Internal\RecordToken $token) {
        if (!array_key_exists($token->value(), $this->paused_records)) {
            throw new InvalidArgumentException("Unknown, resumed or already used token supplied.");
        }

        $this->records[$token->value()] = $this->paused_records[$token->value()];
        unset($this->paused_records[$token->value()]);
    }

    /**
     * Get the result of a dependency record.
     *
     * Could only be used once per RecordToken.
     *
     * @throws  InvalidArgumentException
     * @param   Internal\RecordToken    $token
     * @return  string[]
     */
    protected function getDependencyRecord(Internal\RecordToken $token) {
        if (!array_key_exists($token->value(), $this->records)) {
            throw new InvalidArgumentException("Unknown or already used token supplied.");
        }

        $res = $this->records[$token->value()];
        unset($this->records[$token->value()]);

        return $res;
    }

    // Add a provider to all current dependency records.
    protected function recordDependency($name) {
        foreach ($this->records as &$deps) {
            $deps[] = $name;
        }
    }

    // Start an internal record of dependencies.
    protected function recordDependenciesInternal() {
        $token = $this->recordDependencies();

        // If there already is a recording going on for internally
        // tracking the dependencies of each provider, we pause it as
        // we only want the direct dependencies of each provider.
        $last = end($this->internal_records);
        if ($last) {
            $this->pauseDependencyRecord($last);
        }

        $this->internal_records[$token->value()] = $token;
        return $token;
    }

    // Get the results for an internal record of dependencies.
    protected function getDependencyRecordInternal(Internal\RecordToken $token) {
        assert(array_key_exists($token->value(), $this->internal_records));
        assert($token->value() == end($this->internal_records)->value());
        assert(array_key_exists($token->value(), $this->records));
        
        array_pop($this->internal_records);
        if (end($this->internal_records)) {
            $this->resumeDependencyRecord(end($this->internal_records));
        }

        return $this->getDependencyRecord($token); 
    }
}
