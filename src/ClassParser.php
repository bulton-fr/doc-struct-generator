<?php

namespace bultonFr\DocStructGenerator;

use \phpDocumentor\Reflection\DocBlockFactory;
use \phpDocumentor\Reflection\DocBlock\Tag;

/**
 * Parse a class and find all methods into
 */
class ClassParser
{
    /**
     * @var string $className The name (with namespace) of the class to parse
     */
    protected $className = '';
    
    /**
     * @var \bultonFr\DocStructGenerator\ProjectParser|null $projectParser The
     * instance of the ProjectParser class who instanciate this class
     */
    protected $projectParser;
    
    /**
     * @var \ReflectionClass $reflection The reflection instance who describe
     * the class
     */
    protected $reflection;
    
    /**
     * @var \ReflectionClass|false $reflectionParent The reflection instance of
     * the parent class. False if no parent class.
     */
    protected $reflectionParent;
    
    /**
     * @var \bultonFr\DocStructGenerator\ClassParser|null $pârserParent The
     * parser instance of the parent class
     */
    protected $parserParent;
    
    /**
     * @var \bultonFr\DocStructGenerator\ProjectParser[] $parserInterface 
     * parsers instances for each interface implemented by the class
     */
    protected $parserInterfaces = [];
    
    /**
     * @var \phpDocumentor\Reflection\DocBlock\Tags\Method[] $dynamicMethods 
     * All methods find into class docBlock with @method
     */
    protected $dynamicMethods = [];
    
    /**
     * @var \bultonFr\DocStructGenerator\MethodParser[] $methods All methods
     * find into the class who are parsed.
     */
    protected $methods = [];
    
    /**
     * @var int $runStatus The current status of the run method.
     * * 0 : run() has never be called
     * * 1 : run() is currently called
     * * 2 : run() has been called
     * It's a security to not re-call run when we are already on it. When a
     * class have a dependency loop (should not be existing).
     */
    protected $runStatus = 0;
    
    /**
     * Construct
     * Instanciate ReflectionClass for the asked class and get the
     * ReflectionClass for parent class too.
     * 
     * @param string $className
     * @param \bultonFr\DocStructGenerator\ProjectParser|null $projectParser
     * The instance of ProjectParser If the class is instanciate from him,
     * else null.
     * It's used to improve performance (not re-parse a class)
     */
    public function __construct($className, $projectParser=null)
    {
        $this->className     = $className;
        $this->projectParser = $projectParser;
        $this->reflection    = new \ReflectionClass($this->className);
        
        $this->reflectionParent = $this->reflection->getParentClass();
    }
    
    /**
     * Getter accessor to property className
     * 
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
    
    /**
     * Getter accessor to property projectParser
     * 
     * @return \bultonFr\DocStructGenerator\ProjectParser|null
     */
    public function getProjectParser()
    {
        return $this->projectParser;
    }
    
    /**
     * Getter accessor to property reflection
     * 
     * @return \ReflectionClass
     */
    public function getReflection()
    {
        return $this->reflection;
    }
    
    /**
     * Getter accessor to property reflectionParent
     * 
     * @return \ReflectionClass|false
     */
    public function getReflectionParent()
    {
        return $this->reflectionParent;
    }

    /**
     * Getter accessor to property parserParent
     * 
     * @return \bultonFr\DocStructGenerator\ClassParser|null
     */
    public function getParserParent()
    {
        return $this->parserParent;
    }
    
    /**
     * Getter accessor to property parserInterfaces
     * 
     * @return \bultonFr\DocStructGenerator\ProjectParser[]
     */
    public function getParserInterfaces()
    {
        return $this->parserInterfaces;
    }

    /**
     * Getter accessor to property dynamicMethods
     * 
     * @return \phpDocumentor\Reflection\DocBlock\Tags\Method[]
     */
    public function getDynamicMethods()
    {
        return $this->dynamicMethods;
    }
    
    /**
     * Getter accessor to property methods
     * 
     * @return \bultonFr\DocStructGenerator\MethodParser[]
     */
    public function getMethods()
    {
        return $this->methods;
    }
    
    /**
     * Getter accessor to property runStatus
     * 
     * @return int
     */
    public function getRunStatus()
    {
        return $this->runStatus;
    }
    
    /**
     * Run the parser to analyse class methods
     * 
     * @throws \Exception
     * 
     * @return void
     */
    public function run()
    {
        if ($this->runStatus === 1) {
            throw new \Exception(
                'You are already on the run method.'
                .' Maybe you have an dependency loop.'
            );
        }
        
        $this->runStatus = 1;
        
        $this->instantiateParentParser();
        $this->instantiateInterfacesParser();
        
        $this->analyseDocBlock();
        $this->analyseMethods();
        
        $this->runStatus = 2;
    }
    
    /**
     * Instantiate de parser for the parent class
     * 
     * @return void
     */
    protected function instantiateParentParser()
    {
        if ($this->reflectionParent === false) {
            return;
        }
        
        $this->parserParent = $this->newParser($this->reflectionParent->name);
        $this->parserParent->run();
    }
    
    /**
     * Instantiate the parser for all interfaces
     * 
     * @return void
     */
    protected function instantiateInterfacesParser()
    {
        foreach ($this->reflection->getInterfaces() as $interface) {
            $interfaceParser = $this->newParser($interface->name);
            $interfaceParser->run();
            
            $this->parserInterfaces[] = $interfaceParser;
        }
    }
    
    /**
     * Analyse all tags into the class docBlock.
     * 
     * @return void
     */
    protected function analyseDocBlock()
    {
        $classDocBlock = $this->reflection->getDocComment();
        if ($classDocBlock === false) {
            return;
        }
        
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock        = $docBlockFactory->create($classDocBlock);
        $tagsList        = $docBlock->getTags();
        
        foreach ($tagsList as $tagInfos) {
            $this->analyseDocBlockTag($tagInfos);
        }
    }
    
    /**
     * Analyse all methods find into the class by the reflection class
     * 
     * @return void
     */
    protected function analyseMethods()
    {
        $methods = $this->reflection->getMethods();
        
        foreach ($methods as $method) {
            $methodParser = new MethodParser($this, $method);
            $methodParser->run();
            
            $this->methods[$method->name] = $methodParser;
        }
    }
    
    /**
     * Instantiate a new ClassParser object.
     * Usefull if we use ProjectParser. In this case, we check ProjectParser
     * before to know if the class to parse has not been already parsed. If the
     * class was already parsed, we use it and not re-parse it again.
     * 
     * @param string $className The class name to parse
     * 
     * @return \bultonFr\DocStructGenerator\ClassParser
     */
    protected function newParser($className)
    {
        if ($this->projectParser === null) {
            return new ClassParser($className);
        }
        
        if ($this->projectParser->hasClasses($className)) {
            return $this->projectParser->getClassesByName($className);
        }
        
        $parser = new ClassParser($className);
        $this->projectParser->addToClasses($parser);
        return $parser;
    }
    
    /**
     * Analyse all tag find into the class docBlock
     * 
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tagInfos The tag instance
     * 
     * @return void
     */
    protected function analyseDocBlockTag(Tag $tagInfos)
    {
        if ($tagInfos->getName() === 'method') {
            $this->parseTagMethod($tagInfos);
        }
    }
    
    /**
     * Parse the tag @method and get datas from it
     * 
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tagInfos The tag instance
     * 
     * @return void
     */
    protected function parseTagMethod(Tag $tagInfos)
    {
        $this->dynamicMethods[$tagInfos->methodName] = $tagInfos->__toString();
    }
    
    /**
     * PHP Magic method __toString
     * Called when a class is treated like a string.
     * 
     * Display class name, parent class name and interfaces names. After show
     * all methods find into docblock with tag @method, and show all methods
     * declared into the class.
     * 
     * @link http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * 
     * @return string
     */
    public function __toString()
    {
        $str = $this->obtainClassNameInfos()."\n";
        
        //Sort methods by name
        ksort($this->dynamicMethods);
        ksort($this->methods);
        
        foreach ($this->dynamicMethods as $methodStr) {
            $str .= $methodStr."\n";
        }
        
        foreach ($this->methods as $method) {
            //Not display parent class methods who are not be override
            if ($method->getReflection()->class !== $this->reflection->name) {
                continue;
            }
            
            $str .= $method."\n";
        }
        
        return $str;
    }
    
    /**
     * Obtain class name with parent class name and interfaces names
     * 
     * @return string
     */
    public function obtainClassNameInfos()
    {
        $str = $this->className;
        
        if ($this->reflectionParent !== false) {
            $str .= ' extends '.$this->reflectionParent->name;
        }
        
        $interfaces = $this->reflection->getInterfaces();
        if (!empty($interfaces)) {
            $str .= ' implements ';
            
            foreach ($interfaces as $interfaceIndex => $interfaceInfos) {
                if ($interfaceIndex > 0) {
                    $str .= ', ';
                }
                
                $str .= $interfaceInfos->name;
            }
        }
        
        return $str;
    }
}
