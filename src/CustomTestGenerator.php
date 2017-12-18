<?php

namespace SebastianBergmann\PHPUnit\SkeletonGenerator;

/**
 * Custom generator that do not rewrite all tests stubs
 *
 * @author Maxime BERGEON <mbergeon@nsi.admr.org>
 * @version 6.0.4
 * @since 6.0.4
 */
class CustomTestGenerator extends AbstractGenerator
{
    
    //~(/\*(.+)?\*/)?\s*(?|(public|private|protected)\s+)?const\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=\s*(.*);~umiUs
    const CONSTANT_REGEX = "~(/\*(.+)?\*/)?\s*(?|(public|private|protected)\s+)?const\s+%s\s*=\s*(.*);~umiUs";
    
    /**
     *  @var boolean
     */
    protected $bStrictMode = false;
    
    /**
     * @var array
     */
    protected $methodNameCounter = array();

    /**
     * Constructor.
     *
     * @param string $inClassName
     * @param string $inSourceFile
     * @param string $outClassName
     * @param string $outSourceFile
     * @param int $strictMode
     * @throws \RuntimeException
     */
    public function __construct($inClassName, $inSourceFile = '', $outClassName = '', $outSourceFile = '', $strictMode = 0)
    {
        
        if (class_exists($inClassName)) {
            $reflector    = new \ReflectionClass($inClassName);
            $inSourceFile = $reflector->getFileName();

            if ($inSourceFile === false) {
                $inSourceFile = '<internal>';
            }

            unset($reflector);
        } else {
            if (empty($inSourceFile)) {
                $possibleFilenames = array(
                    $inClassName . '.php',
                    str_replace(
                        array('_', '\\'),
                        DIRECTORY_SEPARATOR,
                        $inClassName
                    ) . '.php'
                );

                foreach ($possibleFilenames as $possibleFilename) {
                    if (is_file($possibleFilename)) {
                        $inSourceFile = $possibleFilename;
                    }
                }
            }

            if (empty($inSourceFile)) {
                throw new \RuntimeException(
                    sprintf(
                        'Neither "%s" nor "%s" could be opened.',
                        $possibleFilenames[0],
                        $possibleFilenames[1]
                    )
                );
            }

            if (!is_file($inSourceFile)) {
                throw new \RuntimeException(
                    sprintf(
                        '"%s" could not be opened.',
                        $inSourceFile
                    )
                );
            }

            $inSourceFile = realpath($inSourceFile);
            include_once $inSourceFile;

            if (!class_exists($inClassName)) {
                throw new \RuntimeException(
                    sprintf(
                        'Could not find class "%s" in "%s".',
                        $inClassName,
                        $inSourceFile
                    )
                );
            }
        }

        if (empty($outClassName)) {
            $outClassName = $inClassName . 'Test';
        }

        if (empty($outSourceFile)) {
            $outSourceFile = dirname($inSourceFile) . DIRECTORY_SEPARATOR . $outClassName . '.php';
        }

        
        parent::__construct(
            $inClassName,
            $inSourceFile,
            $outClassName,
            $outSourceFile
        );
        
        $this->bStrictMode = ($strictMode == 1);

    }
    
    /**
     * @return string
     */
    public function generate()
    {
        
        $class = new \ReflectionClass(
            $this->inClassName['fullyQualifiedClassName']
        );

        $testClass = null;
        
        if (file_exists($this->outClassName['fullyQualifiedClassName'] .'.php')) {
            require($this->outClassName['fullyQualifiedClassName'] .'.php');
            if (class_exists($this->outClassName['fullyQualifiedClassName'])) {
                $testClass = new \ReflectionClass(
                    $this->outClassName['fullyQualifiedClassName']
                );
            }
        }
        
        $methods           = '';
        $incompleteMethods = '';

        foreach ($class->getMethods() as $method) {
            
            if (!$method->isConstructor() &&
                !$method->isAbstract() &&
                $method->isPublic() &&
                $method->getDeclaringClass()->getName() == $this->inClassName['fullyQualifiedClassName'] &&
                !preg_match("#@Deprecated#Ui", $method->getDocComment())) {
                
                $assertAnnotationFound = false;
                
                if (preg_match_all('/@assert(.*)$/Um', $method->getDocComment(), $annotations)) {
                    foreach ($annotations[1] as $annotation) {
                        if (preg_match('/\((.*)\)\s+([^\s]*)\s+(.*)/', $annotation, $matches)) {
                            switch ($matches[2]) {
                                case '==':
                                    $assertion = 'Equals';
                                    break;

                                case '!=':
                                    $assertion = 'NotEquals';
                                    break;

                                case '===':
                                    $assertion = 'Same';
                                    break;

                                case '!==':
                                    $assertion = 'NotSame';
                                    break;

                                case '>':
                                    $assertion = 'GreaterThan';
                                    break;

                                case '>=':
                                    $assertion = 'GreaterThanOrEqual';
                                    break;

                                case '<':
                                    $assertion = 'LessThan';
                                    break;

                                case '<=':
                                    $assertion = 'LessThanOrEqual';
                                    break;

                                case 'throws':
                                    $assertion = 'exception';
                                    break;

                                default:
                                    throw new \RuntimeException(
                                        sprintf(
                                            'Token "%s" could not be parsed in @assert annotation.',
                                            $matches[2]
                                        )
                                    );
                            }
                            
                            $matches = array_map('trim', $matches);
                            
                            if ($assertion == 'exception') {
                                $template = 'TestMethodException';
                            } elseif ($assertion == 'Equals' && strtolower($matches[3]) == 'true') {
                                $assertion = 'True';
                                $template  = 'TestMethodBool';
                            } elseif ($assertion == 'NotEquals' && strtolower($matches[3]) == 'true') {
                                $assertion = 'False';
                                $template  = 'TestMethodBool';
                            } elseif ($assertion == 'Equals' && strtolower($matches[3]) == 'false') {
                                $assertion = 'False';
                                $template  = 'TestMethodBool';
                            } elseif ($assertion == 'NotEquals' && strtolower($matches[3]) == 'false') {
                                $assertion = 'True';
                                $template  = 'TestMethodBool';
                            } else {
                                $template = 'TestMethod';
                            }
                            
                            if ($method->isStatic()) {
                                $template .= 'Static';
                            }

                            $methodTemplate = new \Text_Template(
                                sprintf(
                                    '%s%stemplate%s%s.tpl',
                                    __DIR__,
                                    DIRECTORY_SEPARATOR,
                                    DIRECTORY_SEPARATOR,
                                    $template
                                )
                            );

                            $origMethodName = $method->getName();
                            $methodName     = ucfirst($origMethodName);

                            if (isset($this->methodNameCounter[$methodName])) {
                                $this->methodNameCounter[$methodName]++;
                            } else {
                                $this->methodNameCounter[$methodName] = 1;
                            }

                            if ($this->methodNameCounter[$methodName] > 1) {
                                $methodName .= $this->methodNameCounter[$methodName];
                            }

                            $methodTemplate->setVar(
                                array(
                                    'annotation'     => trim($annotation),
                                    'arguments'      => $matches[1],
                                    'assertion'      => isset($assertion) ? $assertion : '',
                                    'expected'       => $matches[3],
                                    'origMethodName' => $origMethodName,
                                    'className'      => $this->inClassName['fullyQualifiedClassName'],
                                    'methodName'     => $methodName
                                )
                            );

                            $methods .= $methodTemplate->render();

                            $assertAnnotationFound = true;
                        }
                    }
                }

                // <!--
                $testMethod = 'test' . ucfirst($method->name);
                if ($testClass !== null && $testClass->hasMethod($testMethod) && !preg_match("#@ForceUpdate#Ui", $method->getDocComment())) {
                    $methods .= $this->retrieveMethodImplFromTestClass($testClass, $testMethod);
                    $this->methodNameCounter[$testMethod] = 1;
                } else { //->
                    if (!$assertAnnotationFound) {
                        $methodTemplate = new \Text_Template(
                            sprintf(
                                '%s%stemplate%sIncompleteTestMethod.tpl',
                                __DIR__,
                                DIRECTORY_SEPARATOR,
                                DIRECTORY_SEPARATOR
                            )
                        );

                        $methodTemplate->setVar(
                            array(
                                'className'      => $this->inClassName['fullyQualifiedClassName'],
                                'methodName'     => ucfirst($method->getName()),
                                'origMethodName' => $method->getName()
                            )
                        );

                        $incompleteMethods .= $methodTemplate->render();
                    }
                }
            }
        }

        $classTemplate = new \Text_Template(
            sprintf(
                '%s%stemplate%sCustomTestClass.tpl',
                __DIR__,
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR
            )
        );

        if ($this->outClassName['namespace'] != '') {
            $namespace = "\nnamespace " .
                $this->outClassName['namespace'] . ";\n";
        } else {
            $namespace = '';
        }

        $aTemplatedMethods = array (
            'setUp'     => 'SetUp',
            'tearDown'  => 'TearDown'
        );
        
        $aTemplatedMethodsOutput = array();
        
        foreach ($aTemplatedMethods as $sMethod => $sTemplateName) {
            if (empty($testClass) || 
                (!empty($testClass) && 
                (!$testClass->hasMethod($sMethod) ||
                ($testClass->hasMethod($sMethod) && $testClass->getMethod($sMethod)->getDeclaringClass()->getName() != $testClass->getName())))) {

                $setUpTemplate = new \Text_Template(
                    sprintf(
                        '%s%stemplate%s'. $sTemplateName .'.tpl',
                        __DIR__,
                        DIRECTORY_SEPARATOR,
                        DIRECTORY_SEPARATOR
                    )
                );
                $setUpTemplate->setVar(
                    array (
                        'className'      => $this->inClassName['className'],
                    )
                );
                $aTemplatedMethodsOutput[$sMethod] = $setUpTemplate->render();
            } else {
                $aTemplatedMethodsOutput[$sMethod] = $this->retrieveMethodImplFromTestClass($testClass, $sMethod);
            }
            
            if (isset($this->methodNameCounter[$sMethod])) {
                $this->methodNameCounter[$sMethod]++;
            } else {
                $this->methodNameCounter[$sMethod] = 1;
            }
        }
        
        $sProperties = $this->backUpProperties($testClass);
        
        $sConstants = $this->backUpConstants($testClass);
        
        if (!$this->bStrictMode) {
            $methods .= $this->backUpNonImplementingTest($testClass);
        }
        
        $classTemplate->setVar(
            array(
                'namespace'          => $namespace,
                'namespaceSeparator' => !empty($namespace) ? '\\' : '',
                'className'          => $this->inClassName['className'],
                'testClassName'      => $this->outClassName['className'],
                'methods'            => $methods . $incompleteMethods,
                'date'               => date('Y-m-d'),
                'time'               => date('H:i:s'),
                'setUp'              => $aTemplatedMethodsOutput['setUp'],
                'tearDown'           => $aTemplatedMethodsOutput['tearDown'],
                'properties'         => $sProperties,
                'constants'          => $sConstants
            )
        );

        return $classTemplate->render();
    }
    
    /**
     * Back up existing functions that do not *explicitely* perform tests
     * 
     * @author Maxime BERGEON <mbergeon@nsi.admr.org>
     * @version 6.0.4
     * @since 6.0.4
     * 
     * @param ReflectionClass $testClass
     * @return string output
     */
    protected function backUpNonImplementingTest($testClass) {
        $output = '';
        foreach ($testClass->getMethods() as $oMethod) {
            if ($oMethod->getDeclaringClass()->getName() == $testClass->getName() &&
                !array_key_exists($oMethod->getName(), $this->methodNameCounter)
            ) {
                $output .= $this->retrieveMethodImplFromTestClass($testClass, $oMethod->getName());
                $this->methodNameCounter[$oMethod->getName()] = 1;
            }
        }
        
        return $output;
    }
    
    /**
     * Back up existing properties
     * 
     * @author Maxime BERGEON <mbergeon@nsi.admr.org>
     * @version 6.0.4
     * @since 6.0.4
     * 
     * @param ReflectionClass $testClass
     * @return string output
     */
    protected function backUpProperties ($testClass) {
        
        $sProperties = '';
        if (!empty($testClass)) {
            foreach ($testClass->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() == $testClass->getName()) {
                    $oPropertyTemplate = new \Text_Template(
                        sprintf(
                            '%s%stemplate%sProperty.tpl',
                            __DIR__,
                            DIRECTORY_SEPARATOR,
                            DIRECTORY_SEPARATOR
                        )
                    );
                    $oPropertyTemplate->setVar(
                        array(
                            'doc'   => $property->getDocComment(),
                            'name'  => $property->getName(),
                            'value' => ($property->isPublic() ? $property->getValue(new $this->inClassName['fullyQualifiedClassName']) : ''),
                            'visibility' => ($property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private'))
                        )
                    );
                    $sProperties .= $oPropertyTemplate->render();
                }
            }
        }
        
        return $sProperties;
    }
    
    /**
     * Back up existing constants
     * 
     * @author Maxime BERGEON <mbergeon@nsi.admr.org>
     * @version 6.0.4
     * @since 6.0.4
     * 
     * @param ReflectionClass $testClass
     * @return string output
     */
    protected function backUpConstants ($testClass) {
               
        $sConstants = '';
        $sTestClassContent = file_get_contents($testClass->getFileName());
        preg_match("#class\h*(?P<classname>\w+)[^{}]*(\{(?:[^{}]*|(?2))*\})#mx", $sTestClassContent, $aMatches);
        $innerTestClassContent = $aMatches[2];
        preg_match("#^\h*(?:(?P<comment>\Q/*\E(?s:.*?)\Q*/\E)(?s:.*?))?(?:public|private)?\h*const\h*(?P<key>\w+)\h*=\h*(?P<value>[^;]+;)#mx", $innerTestClassContent, $aMatches);
        preg_replace_callback ("#^\h*(?:(?P<comment>\Q/*\E(?s:.*?)\Q*/\E)(?s:.*?))?(?:public|private)?\h*const\h*(?P<key>\w+)\h*=\h*(?P<value>[^;]+;)#mx", function($matches) use (&$sConstants) {
            $sConstants .= "\n" . $matches[0] ."\n";
        }, $innerTestClassContent);

        /*
        foreach ($testClass->getReflectionConstants() as $sName => $mConst) {
            if ($mConst->getDeclaringClass()->getName() == $testClass->getName()) {
                $sConstants .= "\n" . str_pad('', 4, ' ') . $mConst->getDocComment() . "\n";
                //$sConstants .= "\n" . ($mConst->isPublic() ? 'public' : ($mConst->isProtected() ? 'protected' : 'private'));
                $sConstants .= str_pad('', 4, ' ') . "const ". $mConst->getName() . ' = '. $testClass->getConstant($mConst->getName()) .';';
                
                $oConstantTemplate = new \Text_Template(
                    sprintf(
                        '%s%stemplate%sConstant.tpl',
                        __DIR__,
                        DIRECTORY_SEPARATOR,
                        DIRECTORY_SEPARATOR
                    )
                );
                $oConstantTemplate->setVar(
                    array(
                        'name'  => $sName,
                        'value' => (is_string($mValue) ? self::escape($mValue) : $mValue)
                    )
                );
                $sConstants .= $oConstantTemplate->render();
            }
        }*/
        
        return $sConstants;
    }
    
    /**
     * Retrieve method implementation code within the existing test class
     * 
     * @author Maxime BERGEON <mbergeon@nsi.admr.org>
     * @version 6.0.4
     * @since 6.0.4
     * 
     * @param ReflectionClass $testClass
     * @param type $testMethod
     * @return string output
     */
    protected function retrieveMethodImplFromTestClass($testClass, $testMethod) {
        $theTestMethod = $testClass->getMethod($testMethod);
        $filename = $theTestMethod->getFileName();
        $start_line = $theTestMethod->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $end_line = $theTestMethod->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode("", array_slice($source, $start_line, $length));
        
        return "\n". str_pad('', 4, ' ') . $theTestMethod->getDocComment() ."\n". $body;
    }
    
    /**
     * Escape a string
     * 
     * @WARNING depending on the visual representation of the variable from PHP view, string may be altered and backslashes may have been deleted. DO NOT PROVIDE REGEX HERE
     * 
     * @author Maxime BERGEON <mbergeon@nsi.admr.org>
     * @version 6.0.4
     * @since 6.0.4
     * 
     * @param $sValue string representation of the value
     * @return string output
     */
    protected static function escape ($sValue) {
        if (preg_match('/([^\\\\]\")/', $sValue) !== false) {
            return "'". $sValue ."'";
        } else {
            return '"'. $sValue .'"';
        }
    }
    
}
