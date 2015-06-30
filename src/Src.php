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
 * Never keep a reference to a Src-object that was not requested
 * from the source object via $src->service("Src").
 *
 */
class Src {
    /**
     * Request or register a factory for a service.
     *
     * A service is an object that only exists once in the process. It is
     * initialized on the first request for the service and is only initialized
     * once. 
     *
     * If second argument is omitted, requests a service. If second argument is
     * set, expects a closure that takes the source itself as the first argument.
     *
     * Returns the service if second argument is omitted. Returns an updated
     * version of itself is second argument is used, where the update is non
     * destructive to the previous version of the source. This makes it possible
     * to give the source to someone else without worrying that he modifies it
     * in an odd way.
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
        return $this->providers["$name"]["dependencies"];
    }

    /*********************
     * Internals 
     *********************/
    protected $providers = array();
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

        $this->providers = array_merge( $ancestor ? $ancestor->providers : array()
                                      , $new_providers);

        $this->default_factory = $new_default_factory
                               ? $new_default_factory
                               : ($ancestor ? $ancestor->default_factory : null);

        if (!empty($new_provider_names)) {
            self::refreshDependentProviders($this->providers, $new_provider_names[0]);
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
        if (array_key_exists($name, $this->providers)) {
            $reverse_dependencies = $this->providers[$name]["reverse_dependencies"];
        }
        else {
            $reverse_dependencies = array();
        }

        return $this->newSrc(array
                    ( $name => array
                        ( "in_construction" => false
                        , "dependencies" => array()
                        , "reverse_dependencies" => $reverse_dependencies
                        , "factory" => $factory
                        )
                    ));

        return $this->newSrc( $providers
                            , $this->default_factory);
    }
  
    protected function requestProvider($name) {
        if (!array_key_exists($name, $this->providers)) {
            throw new Exceptions\UnknownService($name);
        }

        $this->recordDependency($name);

        if (array_key_exists("provider", $this->providers[$name])) {
            return $this->providers[$name]["provider"];
        }

        return $this->initializeProvider($name);
    }

    protected function initializeProvider($name) {
        if ($this->providers[$name]["in_construction"]) {
            throw new Exceptions\UnresolvableDependency($name);
        } 

        // This is for detection of unresolvable dependencies.
        $this->providers[$name]["in_construction"] = true;

        $token = $this->recordDependenciesInternal();

        $factory = $this->providers[$name]["factory"];
        $provider = $factory($this);
        $this->providers[$name]["provider"] = $provider;

        // Dependencies of this provider
        $deps = $this->getDependencyRecordInternal($token);
        $this->providers[$name]["dependencies"] = $deps;

        // Add this providers as reverse dependency to all it's
        // dependencies
        foreach ($deps as $dep) {
            $this->providers[$dep]["reverse_dependencies"][] = $name;
        }

        // Track every provider, that depends on this provider
        $this->providers[$name]["reverse_dependencies"] = array();

        $this->providers[$name]["in_construction"] = false;

        return $provider;
    }

    static protected function refreshDependentProviders(&$providers, $name) {
        assert(array_key_exists($name, $providers));
 
        foreach ($providers[$name]["reverse_dependencies"] as $dep) {
            self::refreshDependentProviders($providers, $dep);
        }

        $providers[$name] = array( "factory" => $providers[$name]["factory"]
                                , "in_construction" => false
                                , "dependencies" => array()
                                , "reverse_dependencies" => array()
                                );
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
        $names = array_keys($this->providers);
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
