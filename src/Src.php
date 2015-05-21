<?php
/******************************************************************************
 * php-src - A library that handles creation of objects and management of 
 * global services to help you refactor your legacy code base
 *
 * Copyright (c) 2014 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received 
 * a copy of the along with the code.
 */

namespace Lechimp\Src;

use Closure;

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
     * @return  mixed
     */
    public function service($name, Closure $construct = null) {
        assert(is_string($name));
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
        return $this->newSrc($this->services, $constructors);
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
        if (!array_key_exists($name, $this->constructors)) {
            throw new Exceptions\UnknownClass($name);
        }

        $args = func_get_args();
        $args[0] = $this;
        $construct = $this->constructors[$name];
        return call_user_func_array($construct, $args);
    }

    /*********************
     * Internals 
     *********************/
    protected $services = array();
    protected $constructors = array();

    // For construction:

    public function __construct(array &$services = null, array &$constructors = null) {
        if ($services) {
            $this->services = $services;
        }
        if ($constructors) {
            $this->constructors = $constructors;
        }
    }

    protected function newSrc(array &$services, array &$constructors) {
        return new Src($services, $constructors);
    }

    // For service: 

    protected function registerService($name, Callable $construct) {
        $services = array_merge(array(), $this->services); // shallow copy    
        $entry = array( "construct" => $construct );
        $services[$name] = $entry;
        return $this->newSrc($services, $this->constructors);
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
}
