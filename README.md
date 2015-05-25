[![Build Status](https://travis-ci.org/lechimp-p/php-src.svg?branch=master)](https://travis-ci.org/lechimp-p/php-src)
[![Scrutinizer](https://scrutinizer-ci.com/g/lechimp-p/php-src/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lechimp-p/php-src)
[![Coverage](https://scrutinizer-ci.com/g/lechimp-p/php-src/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/lechimp-p/php-src)

# Src

**A library that handles creation of objects and management of global services
to help you refactor your legacy code base**

## Is this a DI-Container, a Lib for Factories or what?

This library acts as a source for objects, no matter whether they are global
services or should be newly created. It's purpose is to help to decouple legacy
code bases, where services are introduced as globals and objects are created in
the style of `require_once` then `new`. 

## Rationale

In my work with the Open Source LMS ILIAS, i need to deal with a very large and
tangled code base (aka a Big Ball of Mud), that makes it hard to understand,
modify and test the system. As i want this code needs to transition to a less 
dependent and more modular state, i figured that some crucial tasks are to:

* get rid of globals
* make it possible to slip in custom classes in existing code

This would help to introduce unit test and customize the system more easily.

As the code base is huge and maintained by a lot of people, it is necessary that
any new solution

* could coexist with existing solutions
* could be introduced easily
* has a clear interface and usage
* provides benefit to all maintainers

This is necessary to make the new solution be adopted quickly and avoid lava
layers in the system. A library solving the afformentioned problems only is
really effective if it is used throughout the system.

## Considerations

### Setter Injection vs. Constructor Injection

Setter Injection is technique to inject dependencies of objects to the object
via setters on the object. With constructor injection, the dependencies are
passed to the constructor of an object.

Constructor injection provides the benefit, that it is impossible to create
an object in an not completely initialized state. With setter injection this
could happen easily if one forgets to set a dependency after construction. 

In the setter injection scenario this could be circumvented by setting a default
dependency on a global. I consider this an unsatisfying approach, as it could
potentially lead to hidden dependencies on globals, circumventing the aims of
this library.

I furthermore consider **Make invalid states unrepresentable!** a great 
guideline to ease the use of a system, as one could always use an existing 
object without some `if ($object->isValid()) ...`-pattern.

This lets constructor injection appear to be the better option.

### Dependency Declaration vs. Querying for Dependencies

Some DI-frameworks (e.g. [PHP-DI](http://php-di.org), [Dice](https://r.je/dice.html)
take the approach to inject dependencies either based on type hints on the 
parameters of an object constructor or based on annotations to the constructor
or class. I call this approach dependency declaration, as the required 
dependencies are declared upfront and the framework takes care to inject them
correctly.

Another approach is taken by [Pimple](http://pimple.sensiolabs.org), where a
consumer has to query for a dependency by it's name.

The dependency declaration approach seems to lead to a less code that needs to
be written by a consumer, where the querying approach seems to be a little more
verbose.

On the opposite, the declaration approach makes it necessary to do some 
inspection of class constructors or annotations at runtime, thus bearing more
overhead.

Furthermore the declaration approach requires more magic from the framework
and is less explicit as its functionality is hidden in typehints or annotions.

I consider **Explicit is better than implicit!** (thanks [Python](http://www.python.org)!)
a valuable approach, especially when it comes to huge systems with lots of
contributors with different experiences. The dependency declaration approach
also seems to be buildable on the query approach, if one really wants to take 
that direction.

This library therefore takes the road of querying for dependencies. 

### Relation to Autoloading

The problems this library aims to solve a connected to autoloading, as this
library deals with creating objects, while autoloading deals with loading source
code for a requested class. The problem autoloading solves somehow comes before
the problems this library solves, as we surely need to load some sourcecode
before we could create a class.

In an optimal scenario, autoloading and this library should be completely
orthagonal to each other, the further only dealing with loading of source files
and the latter only dealing with class creation.

As this library aims to help to improve legacy code, we could neverthelesss
not assume that autoloading is easily possible in a code base where this library
should be introduced.

### Set Dependency Once or Modify Later

### Long-Term Development

### Initialisation and Configuration

### Should Src be the only global or be passed explicitly?

## Usage
