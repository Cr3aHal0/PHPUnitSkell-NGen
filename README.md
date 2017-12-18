# PHPUnitSkell-NGen
PHPUnit skelgen module that brings several improvement to the generation code

> **Note:**
>
> This module is a work-around program I made for personal needs but that may fit yours too. Consider it in development ; you can send issues and I'll try as much as possible to answer them on my personal time depending on my schedule.

# Goal

The primary goal of this library was to help php-unit-skelgen being less brutal : When (re)generating test classes, one could hope to generate only unimplemented tests or force update of given methods according to given arguments. The default behavior of this library is :
- generate methods following ``@assert...`` convention (such as PHPUnit Skeleton Generator already does) if they do not exist or explicitely notice they need a force update (``@ForceUpdate``)
- keep existing declared class members aswell as constants

# How to install 

This component is available as a composer package (https://packagist.org/packages/mbergeon/php-unit-skell-n-gen) :

`` composer require mbergeon/php-unit-skell-n-gen ``
or 
`` composer.phar require mbergeon/php-unit-skell-n-gen ``

Update your **/PATH/TO/PHPUNIT_SKELGEN/CLI/Application.php** like so :

```
use SkellNGen\CLI\UpdateTestCommand;

class Application extends AbstractApplication
{

    public function __construct()
    {
        //... add the following line to the existing content
        $this->add(new UpdateTestCommand);
    }

```

And the component will do  the necessary to operate (thanks to Symfony Command component)

# How to use

Just as you would do with classic PHPUnit Skeleton Generator' commands :

`` PATH/TO/PHP_UNIT_FOLDER/phpunit-skelgen <command>``

# Verbose

`` update-test [-s|--strict] [--bootstrap BOOTSTRAP] [--] <class> [<class-source>] [<test-class>] [<test-source>] ``

> **Options:**
>
> - **--strict** : Delete test methods that do not explicitely implement tests for a source class method (based on their name)

# Coming next
