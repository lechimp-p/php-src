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
     * @param   Closure|null    $construct 
     * @throws  Exceptions/UnknownService
     * @throws  Exceptions/UnresolvableDependency
     * @throws  InvalidArgumentException    When $name == "Src"
     * @return  mixed
     */
    public function service($name, Closure $construct = null) {
        assert(is_string($name));

        if ($construct && $name == "Src") {
            throw new InvalidArgumentException("The name 'Src' is reserved.");
        }

        if ($construct) {
            return $this->registerService($name, $construct);
        }
        else if ($name == "Src") {
            return $this;
        }
        else {
            return $this->requestService($name);
        }
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
     * Register a constructor for a class.
     *
     * On construction with build , gives src and parameters to 
     * 
     * @param   string          $name
     * @param   Closure         $construct
     * @return  Src
     */
    public function constructorFor($name, Closure $construct) {
        $constructors = array_merge(array(), $this->constructors); 
        $constructors[$name] = $construct;
        return $this->newSrc( $this->services
                            , $constructors
                            , $this->default_constructor);
    }

    /**
     * Register a default constructor for classes.
     *
     * This constructor is used, when no constructor for a class could be 
     * found. The closure must be a function taking the Src and the class
     * name as arguments, followed by an array of parameters for the class 
     * constructor.
     *
     * @param   Closure         $construct
     * @return  Src
     */
    public function defaultConstructor(Closure $construct) {
        return $this->newSrc( $this->services
                            , $this->constructors
                            , $construct);
    }

    /**
     * Create a new object.
     *
     * @param   string          $name
     * @param   mixed           ...     Parameters for class construction.
     * @throws  Exceptions/UnknownClass
     * @return  mixed
     */
    public function construct($name) {
        $args = func_get_args();
        try {
            return $this->constructNamed($name, $args);
        }
        catch (Exceptions\UnknownClass $e) {
            if (!$this->default_constructor) {
                throw $e;
            }

            return $this->constructDefault($name, $args);
        }
    }


    /*********************
     * Internals 
     *********************/
    protected $services = array();
    protected $constructors = array();
    protected $default_constructor = null;
    
    // For recording of dependencies
    protected $records = array();
    protected $paused_records = array();
    protected $internal_records = array();

    // For construction:

    public function __construct( array &$services = null
                               , array &$constructors = null
                               , Closure $default_constructor = null) {
        if ($services) {
            $this->services = $services;
        }
        if ($constructors) {
            $this->constructors = $constructors;
        }
        if ($default_constructor) {
            $this->default_constructor = $default_constructor;
        }
    }

    protected function newSrc( array &$services
                             , array &$constructors
                             , $default_constructor) {
        assert($default_constructor instanceof Closure || $default_constructor === null);
        return new Src($services, $constructors, $default_constructor);
    }

    // For services: 

    protected function registerService($name, Callable $construct) {
        $services = array_merge(array(), $this->services); // shallow copy    
        $entry = array( "construct" => $construct );
        $services[$name] = $entry;
        return $this->newSrc( $services
                            , $this->constructors
                            , $this->default_constructor);
    }

    protected function requestService($name) {
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
        if (!array_key_exists("construct", $this->services[$name])) {
            throw new Exceptions\UnresolvableDependency($name);
        } 

        // This is for detection of unresolvable dependencies.
        $construct = $this->services[$name]["construct"];
        unset($this->services[$name]["construct"]);

        $token = $this->recordDependenciesInternal();

        $service = $construct($this);
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
        $this->services[$name]["reverse_dependencies"] = $deps;

        return $service;
    }

    // For construction:

    protected function constructNamed($name, &$args) {
        if (!array_key_exists($name, $this->constructors)) {
            throw new Exceptions\UnknownClass($name);
        }

        $args[0] = $this;
        $construct = $this->constructors[$name];
        return call_user_func_array($construct, $args);
    }

    protected function constructDefault($name, &$args) {
        unset($args[0]);
        $args = array_values($args);
        $def = $this->default_constructor;
        return $def($this, $name, $args);
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
