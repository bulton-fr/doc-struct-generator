# doc-struct-generator
Generate methods struct for all classes of a project. Can be used like help to write documentation.

[![Build Status](https://travis-ci.org/bulton-fr/doc-struct-generator.svg?branch=develop)](https://travis-ci.org/bulton-fr/doc-struct-generator) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bulton-fr/doc-struct-generator/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/bulton-fr/doc-struct-generator/?branch=develop) [![Latest Stable Version](https://poser.pugx.org/bulton-fr/doc-struct-generator/v/stable.svg)](https://packagist.org/packages/bulton-fr/doc-struct-generator) [![License](https://poser.pugx.org/bulton-fr/doc-struct-generator/license.svg)](https://packagist.org/packages/bulton-fr/doc-struct-generator)

# Install
With composer
`composer require bulton-fr/doc-struct-generator`

Because dependency `phpdocumentor/reflection-common` in `2.0.0-beta1`, you should have PHP >= 7.1

I will see if I can doing anything to allow PHP 5.x versions.

# Use it

## With ProjectParser class
This class will take all classes declared into your composer project, and inspect her. The other function of this class is to save all class already parsed to avoid to re-parse it again if another class extend it (for exemple).

About composer, it should have the classmap generated. So you should use the option `-o` when you install or update your composer project.

Exemple : `composer update -o`

To use ProjectParser :
```php
$project = new \bultonFr\DocStructGenerator\ProjectParser('myVendorPath', ['myNamespaceToInspect\\']);
echo $project->run();
```
The constructor take three parameters:
* `$vendorDir` The path to the vendor dir
* `$nsToParse` An array of all namespace to parse
* `$nsToIgnore` An array of all namespace to ignore

Exemple with my BFW project:
I create the file : `/docs/parser.php`
```php
<?php

$autoload = require_once(__DIR__.'/../vendor/autoload.php');

$project = new \bultonFr\DocStructGenerator\ProjectParser(
    __DIR__.'/../vendor',
    'BFW\\',
    'BFW\\Test\\'
);
echo $project->run();
```
I ask to ProjectParse to generate the structure for classes with a namespace started by `\BFW\`, but to ignore all classes with a namespace who started by `\BFW\Test` to not have my unit test classes inspected.

## Without ProjectParser class
Without ProjectParser, you will loop on all your classes manually. For each class, you will instantiate ClassParser and take him the class name you want to inspect, with the namespace.

Exemple:
```php
$parser = new \bultonFr\DocStructGenerator\ClassParser('myClass');
echo $parser->run();
```

# What is returned ?
If I use ClassParser on the class ProjectParser, it will return :
```
bultonFr\DocStructGenerator\ProjectParser
self public __construct(string $vendorDir, string[]|string $nsToParse, [string[]|string $nsToIgnore=array()])
void public addToClasses(\bultonFr\DocStructGenerator\ClassParser $parser)
\bultonFr\DocStructGenerator\ClassParser[] public getClasses()
\bultonFr\DocStructGenerator\ClassParser public getClassesByName(string $className)
\Composer\Autoload\ClassLoader|null public getComposerLoader()
string[] public getNsToIgnore()
string[] public getNsToParse()
string public getVendorDir()
bool public hasClasses(string $className)
bool protected isIgnoredNS(string $className)
void protected obtainClassList()
void protected obtainComposerLoader()
string public run()
```
