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
     * @throws  InvalidArgumentException    When $name == "Src"
     * @return  mixed
     */
    public function service($name, Closure $factory = null) {
        assert(is_string($name));

        if ($factory && $name == "Src") {
            throw new InvalidArgumentException("The name 'Src' is reserved.");
        }

        $name = "service::$name";

        if ($factory) {
            return $this->registerService($name, $factory);
        }
        else if ($name == "service::Src") {
            return $this;
        }
        else {
            return $this->requestService($name);
        }
    }

    /**
     * Request or register a factory.
     *
     * @param   string          $name
     * @param   Closure|null    $factory 
     * ?? @throws  Exceptions/UnknownService
     * ?? @throws  Exceptions/UnresolvableDependency
     * ?? @throws  InvalidArgumentException    When $name == "Src"
     * @return  mixed
     *
     */
    public function factory($name, Closure $factory = null) {
        if ($factory === null) {
            return $this->requestFactory($name);
        }

        return $this->registerFactory($name, $factory);
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
        $services = array_merge(array(), $this->services); // shallow copy    
        $entry = array( "construct" => $factory );
        $services[$name] = $entry;
        return $this->newSrc( $services
                            , $this->default_factory);
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
        $factory = $this->services[$name]["construct"];
        unset($this->services[$name]["construct"]);

        $service = $factory($this);
        $this->services[$name]["service"] = $service;

        return $service;
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
}
