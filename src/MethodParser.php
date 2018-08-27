<?php

namespace bultonFr\MethodsHeaderGenerator;

use \phpDocumentor\Reflection\DocBlockFactory;
use \phpDocumentor\Reflection\DocBlock\Tag;

/**
 * Parse a method to find all parameters infos and return infos
 */
class MethodParser
{
    /**
     * @var \bultonFr\MethodsHeaderGenerator\ClassParser $classParser The class
     * parser instance corresponding to the class where is the current method
     */
    protected $classParser;
    
    /**
     * @var \ReflectionMethod $reflection The reflection instance who describe
     * the method
     */
    protected $reflection;
    
    /**
     * @var string $name The method name
     */
    protected $name;
    
    /**
     * @var \ReflectionParameter[] $reflectionParamsList The list of all
     * parameter find by reflection system
     */
    protected $reflectionParamsList;
    
    /**
     * @var \stdClass[] $params List of all method parameters found and parser
     */
    protected $params = [];
    
    /**
     * @var \stdClass $return Infos about the method returned data
     */
    protected $return;
    
    /**
     * Construct
     * Populate properties and get all parameters from reflection system.
     * 
     * @param \bultonFr\MethodsHeaderGenerator\ClassParser $classParser
     * @param \ReflectionMethod $method
     */
    public function __construct(
        ClassParser $classParser,
        \ReflectionMethod $method
    )  {
        $this->classParser          = $classParser;
        $this->reflection           = $method;
        $this->name                 = $method->name;
        $this->reflectionParamsList = $this->reflection->getParameters();
        $this->return               = (object) [
            'type' => '???'
        ];
    }
    
    /**
     * Getter accessor to property classParser
     * 
     * @return \bultonFr\MethodsHeaderGenerator\ClassParser
     */
    public function getClassParser()
    {
        return $this->classParser;
    }

    /**
     * Getter accessor to property reflection
     * 
     * @return \ReflectionMethod
     */
    public function getReflection()
    {
        return $this->reflection;
    }
    
    /**
     * Getter accessor to property name
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Getter accessor to property reflectionParamsList
     * 
     * @return \ReflectionParameter[]
     */
    public function getReflectionParamsList()
    {
        return $this->reflectionParamsList;
    }

    /**
     * Getter accessor to property params
     * 
     * @return \stdClass[]
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Getter accessor to property return
     * 
     * @return \stdClass
     */
    public function getReturn()
    {
        return $this->return;
    }
    
    /**
     * Run the method analyse to find all about parameters and returned value
     * 
     * @return void
     */
    public function run()
    {
        $methodDocBlock = $this->reflection->getDocComment();
        if ($methodDocBlock === false) {
            return;
        }
        
        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock        = $docBlockFactory->create($methodDocBlock);
        $tagsList        = $docBlock->getTags();
        
        $this->return->type = null;
        
        foreach ($tagsList as $tagInfos) {
            $this->analyseTag($tagInfos);
        }

        $this->checkReturnType();
    }
    
    /**
     * Analyse all tag find into the class docBlock
     * 
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tagInfos The tag instance
     * 
     * @return void
     */
    protected function analyseTag(Tag $tagInfos)
    {
        if ($tagInfos->getName() === 'param') {
            $this->tagParam($tagInfos);
        } elseif ($tagInfos->getName() === 'return') {
            $this->tagReturn($tagInfos);
        }
    }
    
    /**
     * Parse the tag @param and get datas from it
     * 
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tagInfos The tag instance
     * 
     * @return void
     */
    protected function tagParam(Tag $tagInfos)
    {
        $parameterIndex = count($this->params);
        $reflParamInfos = $this->reflectionParamsList[$parameterIndex];

        $paramInfos = (object) [
            'type'         => $tagInfos->getType()->__toString(),
            'name'         => $tagInfos->getVariableName(),
            'optional'     => $reflParamInfos->isOptional(),
            'defaultValue' => null,
            'passedByRef'  => $reflParamInfos->isPassedByReference(),
            'variadic'     => $reflParamInfos->isVariadic()
        ];
        
        if (empty($paramInfos->type)) {
            $paramInfos->type = $reflParamInfos->getType();
        }
        
        if (empty($paramInfos->name)) {
            $paramInfos->name = $reflParamInfos->name;
        }

        if ($paramInfos->optional === true && $paramInfos->variadic === false) {
            $paramInfos->defaultValue = $reflParamInfos->getDefaultValue();
        }

        $this->params[] = $paramInfos;
    }
    
    /**
     * Parse the tag @return and get datas from it
     * 
     * @param \phpDocumentor\Reflection\DocBlock\Tag $tagInfos The tag instance
     * 
     * @return void
     */
    protected function tagReturn(Tag $tagInfos)
    {
        $type = $tagInfos->getType()->__toString();
        if ($type === '$this') {
            $type = 'self';
        }

        $this->return->type = $type;
    }
    
    /**
     * Check if a return type has been found or not into method docblock
     * If no type has been found, we use the type returned by reflection class.
     * But if there are nothing too, we search into parent and interface.
     * If really nothing is found, we define to "???"
     * 
     * @return void
     */
    protected function checkReturnType()
    {
        if ($this->return->type !== null) {
            return;
        }
        
        if ($this->reflection->name === '__construct') {
            $this->return->type = 'self';
        } elseif ($this->reflection->getReturnType() !== null) {
            $this->return->type = $this->reflection->getReturnType();
        } else {
            $type = $this->searchReturnTypeIntoParentsAndInterfaces();

            if ($type !== '') {
                $this->return->type = $type;
            } else {
                $this->return->type = '???';
            }
        }
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
        $str = $this->return->type.' '
            .$this->obtainVisibility().' '
            .$this->obtainMethodName().'(';
        
        $nbOptionalParams = 0;
        foreach ($this->params as $paramIndex => $paramInfos) {
            if ($paramIndex > 0) {
                $str .= ', ';
            }
            
            if ($paramInfos->optional === true) {
                $nbOptionalParams++;
                $str .= '[';
            }
            
            $str .= $paramInfos->type.' '
                .$this->obtainParamName($paramInfos)
                .$this->obtainDefaultValue($paramInfos)
            ;
        }

        for ($i = 0; $i < $nbOptionalParams; $i++) {
            $str .= ']';
        }

        $str .= ')';
        
        return $str;
    }
    
    /**
     * Find the method visibility
     * 
     * @return string
     */
    protected function obtainVisibility()
    {
        if ($this->reflection->isPublic() === true) {
            return 'public';
        } elseif ($this->reflection->isProtected() === true) {
            return 'protected';
        } elseif ($this->reflection->isPrivate() === true) {
            return 'private';
        }
        
        return '';
    }
    
    /**
     * Obtain the method name with some prefix like static or abstract
     * 
     * @return string
     */
    protected function obtainMethodName()
    {
        $str = '';
        
        if ($this->reflection->isStatic() === true) {
            $str .= 'static ';
        }
        
        if (
            $this->reflection->isAbstract() === true &&
            $this->classParser->getReflection()->isInterface() === false
        ) {
            $str .= 'abstract ';
        }
        
        $str .= $this->reflection->name;
        
        return $str;
    }
    
    /**
     * Obtain a parameter name with some prefix like the symbole for a var
     * passed by ref or the symbole for a variadic parameter
     * 
     * @param \stdClass $paramInfos Parsed Informations about the parameter
     * 
     * @return string
     */
    protected function obtainParamName(\stdClass $paramInfos)
    {
        $str = '';
        
        if ($paramInfos->variadic === true) {
            $str .= '...';
        }
        if ($paramInfos->passedByRef === true) {
            $str .= '&';
        }

        $str .= '$'.$paramInfos->name;
        return $str;
    }
    
    /**
     * Obtain the default value for a parameter.
     * The default value obtained from reflection is on its PHP type. So a 
     * boolean for default value are really a boolean. So need to convert him
     * to string to be displayed. Idem for others types.
     * 
     * @param \stdClass $paramInfos Parsed Informations about the parameter
     * 
     * @return string
     */
    protected function obtainDefaultValue(\stdClass $paramInfos)
    {
        if ($paramInfos->optional === false) {
            return '';
        }
        
        if (is_string($paramInfos->defaultValue)) {
            $paramInfos->defaultValue = '"'.$paramInfos->defaultValue.'"';
        } elseif (is_bool($paramInfos->defaultValue)) {
            if ($paramInfos->defaultValue === true) {
                $paramInfos->defaultValue = 'true';
            } else {
                $paramInfos->defaultValue = 'false';
            }
        } elseif (is_null($paramInfos->defaultValue)) {
            $paramInfos->defaultValue = 'null';
        } elseif (is_array($paramInfos->defaultValue)) {
            if (count($paramInfos->defaultValue) === 0) {
                $paramInfos->defaultValue = 'array()';
            } else {
                $paramInfos->defaultValue = 'array(???)';
            }
        } elseif (is_object($paramInfos->defaultValue)) {
            //Not sure is possible...
            $paramInfos->defaultValue = get_class($paramInfos->defaultValue);
        }

        return '='.(string) $paramInfos->defaultValue;
    }
    
    /**
     * When the returned type has not be found into the current class, we need
     * to search in parent class and/or interface class implemented.
     * 
     * @return string
     */
    protected function searchReturnTypeIntoParentsAndInterfaces()
    {
        $parent = $this->classParser->getParserParent();
        
        if (is_object($parent) && isset($parent->getMethods()[$this->name])) {
            $method    = $parent->getMethods()[$this->name];
            $returnObj = $method->getReturn();

            return $returnObj->type;
        }
        
        $interfaces = $this->classParser->getParserInterfaces();
        foreach ($interfaces as $interfaceParser) {
            if (isset($interfaceParser->getMethods()[$this->name])) {
                $method    = $interfaceParser->getMethods()[$this->name];
                $returnObj = $method->getReturn();

                return $returnObj->type;
            }
        }
        
        return '';
    }
}
