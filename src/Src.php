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
     * Request or register a constructor for a service.
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
        return function() use ($name) {
            return $this->service($name);
        };
    }

    /**
     * Get the dependencies of a service.
     *
     * As a sideeffect will construct the service in question if it is not
     * constructed yet.
     *
     * Returns a list of direct dependencies only.
     *
     * @param   string      $name
     * @throws  Exceptions\UnknownService
     * @return  string[]
     */
    public function dependenciesOf($name) {
        $this->service($name);
        return $this->services[$name]["dependencies"];
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
     * Register a default constructor for classes.
     *
     * This constructor is used, when no constructor for a class could be 
     * found. The closure must be a function taking the Src and the class
     * name as arguments, followed by an array of parameters for the class 
     * constructor.
     *
     * @param   Closure         $factory
     * @return  Src
     */
    public function defaultFactory(Closure $factory) {
        return $this->newSrc( $this->services
                            , $factory);
    }


    /*********************
     * Internals 
     *********************/
    protected $services = array();
    protected $default_factory = null;

    // For construction:

    public function __construct( array &$services = null
                               , Closure $default_factory = null) {
        if ($services) {
            $this->services = $services;
        }
        if ($default_factory) {
            $this->default_factory = $default_factory;
        }
    }

    protected function newSrc( array &$services
                             , $default_factory) {
        assert($default_factory instanceof Closure || $default_factory === null);
        return new Src($services, $default_factory);
    }

    // For services: 

    protected function registerService($name, Callable $factory) {
        $name = "service::$name";
        $services = array_merge(array(), $this->services); // shallow copy    

        if (array_key_exists($name, $services)) {
            self::refreshDependentServices($services, $name);
        }
        else {
            $services[$name] = array( "in_construction" => false
                                    , "dependencies" => array()
                                    , "reverse_dependencies" => array()
                                    );
        }
        $services[$name]["constructor"] = $factory;
        return $this->newSrc( $services
                            , $this->default_factory);
    }

    protected function requestService($name) {
        $name = "service::$name";
        if (!array_key_exists($name, $this->services)) {
            throw new Exceptions\UnknownService($name);
        }

        $this->recordDependency($name);

        if (array_key_exists("service", $this->services[$name])) {
            return $this->services[$name]["service"];
        }

        return $this->initializeService($name);
    }

    protected function initializeService($name) {
        if ($this->services[$name]["in_construction"]) {
            throw new Exceptions\UnresolvableDependency($name);
        } 

        // This is for detection of unresolvable dependencies.
        $this->services[$name]["in_construction"] = true;

        $token = $this->recordDependenciesInternal();

        $factoryor = $this->services[$name]["constructor"];
        $service = $factoryor($this);
        $this->services[$name]["service"] = $service;

        // Dependencies of this service
        $deps = $this->getDependencyRecordInternal($token);
        $this->services[$name]["dependencies"] = $deps;

        // Add this services as reverse dependency to all it's
        // dependencies
        foreach ($deps as $dep) {
            $this->services[$dep]["reverse_dependencies"][] = $name;
        }

        // Track every service, that depends on this service
        $this->services[$name]["reverse_dependencies"] = array();

        $this->services[$name]["in_construction"] = false;

        return $service;
    }

    static protected function refreshDependentServices(&$services, $name) {
        assert(array_key_exists($name, $services));
 
        foreach ($services[$name]["reverse_dependencies"] as $dep) {
            self::refreshDependentServices($services, $dep);
        }

        $services[$name] = array( "constructor" => $services[$name]["constructor"]
                                , "in_construction" => false
                                , "dependencies" => array()
                                , "reverse_dependencies" => array()
                                );
    }

    // For factories:

    protected function requestFactory($name) {
        try {
            return $this->service("factory::$name");
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
        return $this->service($name, function($src) use ($factory){
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

    // Add a service to all current dependency records.
    protected function recordDependency($name) {
        foreach ($this->records as &$deps) {
            $deps[] = $name;
        }
    }

    // Start an internal record of dependencies.
    protected function recordDependenciesInternal() {
        $token = $this->recordDependencies();

        // If there already is a recording going on for internally
        // tracking the dependencies of each service, we pause it as
        // we only want the direct dependencies of each service.
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
