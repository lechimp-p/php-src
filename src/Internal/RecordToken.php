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

namespace Lechimp\Src\Internal;

/**
 * A token for the dependency recorder used internally in
 * the Src.
 */
final class RecordToken {
    private static $count = 0;
    private $value; 

    public static function get() {
        return new RecordToken(self::$count++); 
    }

    private function __construct($value) {
        $this->value = $value; 
    }

    public function value() {
        return $this->value;
    }
}
