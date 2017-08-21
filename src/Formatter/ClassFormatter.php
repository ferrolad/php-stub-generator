<?php
declare(strict_types=1);

namespace setasign\PhpStubGenerator\Formatter;

use ReflectionClass;
use setasign\PhpStubGenerator\Helper\FormatHelper;
use setasign\PhpStubGenerator\Parser\ParserInterface;
use setasign\PhpStubGenerator\PhpStubGenerator;

class ClassFormatter
{
    /**
     * @var ParserInterface
     */
    private $parser;

    /**
     * @var ReflectionClass
     */
    private $class;

    /**
     * ClassFormatter constructor.
     *
     * @param ParserInterface $parser
     * @param ReflectionClass $class
     */
    public function __construct(ParserInterface $parser, ReflectionClass $class)
    {
        $this->parser = $parser;
        $this->class = $class;
    }

    public function format(bool $ignoreSubElements = false): string
    {
        $n = PhpStubGenerator::$eol;
        $t = PhpStubGenerator::$tab;

        $result = '';
        $result .= FormatHelper::indentDocBlock($this->class->getDocComment(), 1, $t) . $n
            . $t;

        if ($this->class->isInterface()) {
            $result .= 'interface ';
        } elseif ($this->class->isTrait()) {
            $result .= 'trait ';
        } else {
            if ($this->class->isAbstract()) {
                $result .= 'abstract ';
            } elseif ($this->class->isFinal()) {
                $result .= 'final ';
            }

            $result .= 'class ';
        }
        $result .= $this->class->getShortName();
        $parentClass = null;
        try {
            $parentClass = $this->class->getParentClass();
        } catch (\Throwable $e) {
        }
        if ($parentClass instanceof ReflectionClass) {
            $result .= ' extends \\' . $parentClass->getName();
        }

        $interfaces = $this->class->getInterfaces();
        // remove interfaces from parent class if there is a parent class
        if ($parentClass instanceof ReflectionClass) {
            $interfaces = array_filter($interfaces, function (ReflectionClass $interface) use ($parentClass) {
                return !$parentClass->implementsInterface($interface->getName());
            });
        }

        // remove sub interfaces of other interfaces
        $interfaces = array_filter($interfaces, function (ReflectionClass $interface) use ($interfaces) {
            foreach ($interfaces as $compareInterface) {
                /**
                 * @var ReflectionClass $compareInterface
                 */
                if ($interface->implementsInterface($compareInterface->getName())) {
                    return false;
                }
            }
            return true;
        });

        $interfaceNames = array_map(function (ReflectionClass $interface) {
            return $interface->getName();
        }, $interfaces);

        if (count($interfaceNames) > 0) {
            if ($this->class->isInterface()) {
                $result .= ' extends ';
            } else {
                $result .= ' implements ';
            }

            $interfaceNames = array_map(function ($interfaceName) {
                return '\\' . $interfaceName;
            }, $interfaceNames);

            $result .= implode(', ', $interfaceNames);
        }

        $result .= $n . $t . '{' . $n;
        if (!$ignoreSubElements) {
            $result .= (new TraitUseBlockFormatter($this->class))->format();

            foreach ($this->class->getConstants() as $constantName => $constantValue) {
                $result .= (new ConstantFormatter($this->parser, $this->class, $constantName))->format();
            }

            foreach ($this->class->getProperties() as $property) {
                $result .= (new PropertyFormatter($this->class, $property))->format();
            }

            foreach ($this->class->getMethods() as $method) {
                $result .= (new MethodFormatter($this->class, $method))->format();
            }
        }
        $result .= '';

        $result .= $t . '}' . $n;

        return $result;
    }
}