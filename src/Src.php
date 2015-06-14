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
        else {
            return $this->requestService($name);
        }
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

        $service = $construct($this);
        $this->services[$name]["service"] = $service;

        return $service;
    }

    // For construction:

    public function constructNamed($name, &$args) {
        if (!array_key_exists($name, $this->constructors)) {
            throw new Exceptions\UnknownClass($name);
        }

        $args[0] = $this;
        $construct = $this->constructors[$name];
        return call_user_func_array($construct, $args);
    }

    public function constructDefault($name, &$args) {
        unset($args[0]);
        $args = array_values($args);
        $def = $this->default_constructor;
        return $def($this, $name, $args);
    }
}
