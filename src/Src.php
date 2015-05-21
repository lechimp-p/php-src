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

class Src {
    /**
     * Request or register a constructor for a service.
     *
     * A service is an object that only exists once in the process. It is
     * initialized on the first request for the service and is only initialized
     * once. 
     *
     * @param   string          $name
     * @param   Closure|null    $construct 
     * @throws  Exceptions/UnknownService
     * @return  mixed
     */
    public function service($name, $construct = null) {
    }
}
