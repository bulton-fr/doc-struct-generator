<?php

namespace bultonFr\DocStructGenerator;

/**
 * Usefull to improve performence when we parse a big project. All classes
 * already parsed are saved, so not need to re-parse it again.
 * 
 * Parse all class from composer autoloader list.
 * To have the full list, run a "composer update" with "-o" argument.
 */
class ProjectParser
{
    /**
     * @var string $vendorDir Path to the composer vendor dir
     */
    protected $vendorDir = '';
    
    /**
     * @var \Composer\Autoload\ClassLoader|null $composerLoader Composer
     * auto-loader
     */
    protected $composerLoader;
    
    /**
     * @var \bultonFr\DocStructGenerator\ClassParser[] $classes All classes who
     * are already parsed
     */
    protected $classes = [];
    
    /**
     * @var string[] $nsToParse All namespace to parse (should start by him)
     */
    protected $nsToParse;
    
    /**
     * @var string[] $nsToIgnore All namespace to ignore (should start by him)
     */
    protected $nsToIgnore;
    
    /**
     * Construct
     * Convert nsToParse and nsToIgnore to array if need.
     * Obtain the composer loader.
     * 
     * @param string $vendorDir Path to the composer vendor dir
     * @param string[]|string $nsToParse All namespace to parse
     *  (should start by him)
     * @param string[]|string $nsToIgnore All namespace to ignore
     *  (should start by him)
     */
    public function __construct($vendorDir, $nsToParse, $nsToIgnore = [])
    {
        $this->vendorDir = $vendorDir;
        
        if (!is_array($nsToParse)) {
            $nsToParse = [$nsToParse];
        }
        if (!is_array($nsToIgnore)) {
            $nsToIgnore = [$nsToIgnore];
        }
        
        $this->nsToParse  = $nsToParse;
        $this->nsToIgnore = $nsToIgnore;
        
        $this->obtainComposerLoader();
    }
    
    /**
     * Getter accessor to property vendorDir
     * 
     * @return string
     */
    public function getVendorDir()
    {
        return $this->vendorDir;
    }

    /**
     * Getter accessor to property composerLoader
     * 
     * @return \Composer\Autoload\ClassLoader|null
     */
    public function getComposerLoader()
    {
        return $this->composerLoader;
    }

    /**
     * Getter accessor to property classes
     * 
     * @return \bultonFr\DocStructGenerator\ClassParser[]
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Getter accessor to property nsToParse
     * 
     * @return string[]
     */
    public function getNsToParse()
    {
        return $this->nsToParse;
    }

    /**
     * Getter accessor to property nsToIgnore
     * 
     * @return string[]
     */
    public function getNsToIgnore()
    {
        return $this->nsToIgnore;
    }
    
    /**
     * Check if a class exist into the list
     * 
     * @param string $className The class name we want to check
     * 
     * @return bool
     */
    public function hasClasses($className)
    {
        return isset($this->classes[$className]);
    }
    
    /**
     * Return the ClassParser object for a class name
     * 
     * @param string $className The class name for which obtain the ClassParser
     * 
     * @return \bultonFr\DocStructGenerator\ClassParser
     * 
     * @throws \Exception If the class not exist into the list
     */
    public function getClassesByName($className)
    {
        if ($this->hasClasses($className) === false) {
            throw new \Exception('Class not found');
        }
        
        return $this->classes[$className];
    }
    
    /**
     * Add a new class to the list
     * 
     * @param \bultonFr\DocStructGenerator\ClassParser $parser The ClassParser
     * object to add
     * 
     * @return void
     */
    public function addToClasses(ClassParser $parser)
    {
        $className                 = $parser->getClassName();
        $this->classes[$className] = $parser;
    }
    
    /**
     * Obtain the composer loader
     * Into this own method to allow people to extends this class to not use
     * composer to obtain the class list.
     * 
     * @return void
     */
    protected function obtainComposerLoader()
    {
        $this->composerLoader = require($this->vendorDir.'/autoload.php');
    }
    
    /**
     * Obtain the class list from the composer class map
     * Into this own method to allow people to extends this class to not use
     * composer to obtain the class list.
     * 
     * @return void
     */
    protected function obtainClassList()
    {
        return $this->composerLoader->getClassMap();
    }
    
    /**
     * Read the class list, check namespace to know if the class should be
     * parsed or not, and run the parser if allowed.
     * 
     * @return string
     */
    public function run()
    {
        $classMap = $this->obtainClassList();
        $output   = '';
        
        foreach ($classMap as $className => $classFilePath) {
            if ($this->isIgnoredNS($className) === true) {
                continue;
            }
            
            if ($this->hasClasses($className)) {
                $parser = $this->getClassesByName($className);
            } else {
                $parser = new ClassParser($className, $this);
                $this->addToClasses($parser);
            }
            
            $parser->run();
            
            $output .= $parser."\n\n";
        }
        
        return $output;
    }
    
    /**
     * Check if a class should be parsed or not from this name (with namespace)
     * Use properties nsToIgnore and nsToParse to know.
     * 
     * @param string $className The class name to check, with namespace
     * 
     * @return boolean
     */
    protected function isIgnoredNS($className) {
        foreach ($this->nsToIgnore as $toIgnore) {
            if (strpos($className, $toIgnore) === 0) {
                return true;
            }
        }
        
        foreach ($this->nsToParse as $toParse) {
            if (strpos($className, $toParse) === 0) {
                return false;
            }
        }
        
        return true;
    }
}
