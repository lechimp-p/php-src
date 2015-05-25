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

### Explicit Dependency Declaration vs. Implicit Querying for Dependencies

### Relation to Autoloading

### Set Dependency Once or Modify Later

### Long-Term Development

### Initialisation and Configuration

### Should Src be the only global or be passed explicitly?

## Usage
