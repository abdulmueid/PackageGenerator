<?php

namespace WsdlToPhp\PackageGenerator\File;

use WsdlToPhp\PackageGenerator\Container\PhpElement\Method;
use WsdlToPhp\PackageGenerator\Container\PhpElement\Property;
use WsdlToPhp\PackageGenerator\Container\PhpElement\Constant;
use WsdlToPhp\PackageGenerator\Model\Struct as StructModel;
use WsdlToPhp\PackageGenerator\Model\StructAttribute as StructAttributeModel;
use WsdlToPhp\PackageGenerator\Model\AbstractModel;
use WsdlToPhp\PackageGenerator\File\Utils as FileUtils;
use WsdlToPhp\PackageGenerator\Generator\Utils as GeneratorUtils;
use WsdlToPhp\PhpGenerator\Element\PhpAnnotationBlock;
use WsdlToPhp\PhpGenerator\Element\PhpAnnotation;
use WsdlToPhp\PhpGenerator\Element\PhpMethod;
use WsdlToPhp\PhpGenerator\Element\PhpProperty;
use WsdlToPhp\PhpGenerator\Element\PhpConstant;
use WsdlToPhp\PhpGenerator\Component\PhpClass;
use WsdlToPhp\PackageGenerator\ConfigurationReader\XsdTypes;

abstract class AbstractModelFile extends AbstractFile
{
    /**
     * @var string Meta annotation length
     */
    const ANNOTATION_META_LENGTH = '250';
    /**
     * @var string Long annotation string
     */
    const ANNOTATION_LONG_LENGTH = '1000';
    /**
     * @var string
     */
    const ANNOTATION_PACKAGE = 'package';
    /**
     * @var string
     */
    const ANNOTATION_SUB_PACKAGE = 'subpackage';
    /**
     * @var string
     */
    const ANNOTATION_RETURN = 'return';
    /**
     * @var string
     */
    const ANNOTATION_USES = 'uses';
    /**
     * @var string
     */
    const ANNOTATION_PARAM = 'param';
    /**
     * @var string
     */
    const ANNOTATION_VAR = 'var';
    /**
     * @var string
     */
    const ANNOTATION_SEE = 'see';
    /**
     * @var string
     */
    const ANNOTATION_THROWS = 'throws';
    /**
     * @var string
     */
    const METHOD_CONSTRUCT = '__construct';
    /**
     * @var string
     */
    const METHOD_SET_STATE = '__set_state';
    /**
     * @var string
     */
    const TYPE_STRING = 'string';
    /**
     * @var string
     */
    const TYPE_ARRAY = 'array';
    /**
     * @var AbstractModel
     */
    protected $model;
    /**
     * @param bool $withSrc
     * @return string
     */
    public function getFileDestination($withSrc = true)
    {
        return sprintf('%s%s%s', $this->getDestinationFolder($withSrc), $this->getModel()->getSubDirectory(), $this->getModel()->getSubDirectory() !== '' ? '/' : '');
    }
    /**
     * @param bool $withSrc
     * @return string
     */
    public function getDestinationFolder($withSrc = true)
    {
        $src = rtrim($this->generator->getOptionSrcDirname(), DIRECTORY_SEPARATOR);
        return sprintf('%s%s', $this->getGenerator()->getOptionDestination(), (bool) $withSrc && !empty($src) ? $src . DIRECTORY_SEPARATOR : '');
    }
    /**
     * @see \WsdlToPhp\PackageGenerator\File\AbstractFile::writeFile()
     * @param bool $withSrc
     * @return int|bool
     */
    public function writeFile($withSrc = true)
    {
        if (!$this->getModel() instanceof AbstractModel) {
            throw new \InvalidArgumentException('You MUST define the model before begin able to generate the file', __LINE__);
        }
        GeneratorUtils::createDirectory($this->getFileDestination($withSrc));
        $this->defineNamespace()->defineUseStatement()->addAnnotationBlock()->addClassElement();
        return parent::writeFile();
    }
    /**
     * @return AbstractModelFile
     */
    protected function addAnnotationBlock()
    {
        $this->getFile()->addAnnotationBlockElement($this->getClassAnnotationBlock());
        return $this;
    }
    /**
     * @param AbstractModel $model
     * @return AbstractModelFile
     */
    public function setModel(AbstractModel $model)
    {
        $this->model = $model;
        $this->getFile()->getMainElement()->setName($model->getPackagedName());
        return $this;
    }
    /**
     * @return AbstractModel
     */
    public function getModel()
    {
        return $this->model;
    }
    /**
     * @param string $name
     * @return StructModel|null
     */
    protected function getModelByName($name)
    {
        return $this->getGenerator()->getStruct($name);
    }
    /**
     * @param PhpAnnotationBlock $block
     * @return AbstractModelFile
     */
    protected function definePackageAnnotations(PhpAnnotationBlock $block)
    {
        $packageName = $this->getPackageName();
        if (!empty($packageName)) {
            $block->addChild(new PhpAnnotation(self::ANNOTATION_PACKAGE, $packageName));
        }
        if (count($this->getModel()->getDocSubPackages()) > 0) {
            $block->addChild(new PhpAnnotation(self::ANNOTATION_SUB_PACKAGE, implode(',', $this->getModel()->getDocSubPackages())));
        }
        return $this;
    }
    /**
     * @return string
     */
    protected function getPackageName()
    {
        $packageName = '';
        if ($this->getGenerator()->getOptionPrefix() !== '') {
            $packageName = $this->getGenerator()->getOptionPrefix();
        } elseif ($this->getGenerator()->getOptionSuffix() !== '') {
            $packageName = $this->getGenerator()->getOptionSuffix();
        }
        return $packageName;
    }
    /**
     * @param PhpAnnotationBlock $block
     * @return AbstractModelFile
     */
    protected function defineGeneralAnnotations(PhpAnnotationBlock $block)
    {
        foreach ($this->getGenerator()->getOptionAddComments() as $tagName => $tagValue) {
            $block->addChild(new PhpAnnotation($tagName, $tagValue));
        }
        return $this;
    }
    /**
     * @return PhpAnnotationBlock
     */
    protected function getClassAnnotationBlock()
    {
        $block = new PhpAnnotationBlock();
        $block->addChild($this->getClassDeclarationLine());
        $this->defineModelAnnotationsFromWsdl($block)->definePackageAnnotations($block)->defineGeneralAnnotations($block);
        return $block;
    }
    /**
     * @return string
     */
    protected function getClassDeclarationLine()
    {
        return sprintf($this->getClassDeclarationLineText(), $this->getModel()->getName(), $this->getModel()->getContextualPart());
    }
    /**
     * @return string
     */
    protected function getClassDeclarationLineText()
    {
        return 'This class stands for %s %s';
    }
    /**
     * @param PhpAnnotationBlock $block
     * @param AbstractModel $model
     * @return AbstractModelFile
     */
    protected function defineModelAnnotationsFromWsdl(PhpAnnotationBlock $block, AbstractModel $model = null)
    {
        FileUtils::defineModelAnnotationsFromWsdl($block, $model instanceof AbstractModel ? $model : $this->getModel());
        return $this;
    }
    /**
     * @return AbstractModelFile
     */
    protected function addClassElement()
    {
        $class = new PhpClass($this->getModel()->getPackagedName(), $this->getModel()->isAbstract(), $this->getModel()->getExtendsClassName() === '' ? null : $this->getModel()->getExtendsClassName());
        $this->defineConstants($class)
            ->defineProperties($class)
            ->defineMethods($class)
            ->defineStringMethod($class)
            ->getFile()
            ->addClassComponent($class);
        return $this;
    }
    /**
     * @return AbstractModelFile
     */
    protected function defineNamespace()
    {
        if ($this->getModel()->getNamespace() !== '') {
            $this->getFile()->setNamespace($this->getModel()->getNamespace());
        }
        return $this;
    }
    /**
     * @return AbstractModelFile
     */
    protected function defineUseStatement()
    {
        if ($this->getModel()->getExtends() !== '') {
            $this->getFile()->addUse($this->getModel()->getExtends(), null, true);
        }
        return $this;
    }
    /**
     * @param PhpClass $class
     * @return AbstractModelFile
     */
    protected function defineConstants(PhpClass $class)
    {
        $constants = new Constant($this->getGenerator());
        $this->getClassConstants($constants);
        foreach ($constants as $constant) {
            $annotationBlock = $this->getConstantAnnotationBlock($constant);
            if (!empty($annotationBlock)) {
                $class->addAnnotationBlockElement($annotationBlock);
            }
            $class->addConstantElement($constant);
        }
        return $this;
    }
    /**
     * @param PhpClass $class
     * @return AbstractModelFile
     */
    protected function defineProperties(PhpClass $class)
    {
        $properties = new Property($this->getGenerator());
        $this->getClassProperties($properties);
        foreach ($properties as $property) {
            $annotationBlock = $this->getPropertyAnnotationBlock($property);
            if (!empty($annotationBlock)) {
                $class->addAnnotationBlockElement($annotationBlock);
            }
            $class->addPropertyElement($property);
        }
        return $this;
    }
    /**
     * @param PhpClass $class
     * @return AbstractModelFile
     */
    protected function defineMethods(PhpClass $class)
    {
        $methods = new Method($this->getGenerator());
        $this->getClassMethods($methods);
        foreach ($methods as $method) {
            $annotationBlock = $this->getMethodAnnotationBlock($method);
            if (!empty($annotationBlock)) {
                $class->addAnnotationBlockElement($annotationBlock);
            }
            $class->addMethodElement($method);
        }
        return $this;
    }
    /**
     * @param Constant $constants
     */
    abstract protected function getClassConstants(Constant $constants);
    /**
     * @param PhpConstant $constant
     * @return PhpAnnotationBlock|null
     */
    abstract protected function getConstantAnnotationBlock(PhpConstant $constant);
    /**
     * @param Property $properties
     */
    abstract protected function getClassProperties(Property $properties);
    /**
     * @param PhpProperty $property
     * @return PhpAnnotationBlock|null
     */
    abstract protected function getPropertyAnnotationBlock(PhpProperty $property);
    /**
     * @param Method $methods
     */
    abstract protected function getClassMethods(Method $methods);
    /**
     * @param PhpMethod $method
     * @return PhpAnnotationBlock|null
     */
    abstract protected function getMethodAnnotationBlock(PhpMethod $method);
    /**
     * @param PhpClass $class
     * @return AbstractModelFile
     */
    protected function defineStringMethod(PhpClass $class)
    {
        $class->addAnnotationBlockElement($this->getToStringMethodAnnotationBlock());
        $class->addMethodElement($this->getToStringMethod());
        return $this;
    }
    /**
     * @return PhpAnnotationBlock
     */
    protected function getToStringMethodAnnotationBlock()
    {
        return new PhpAnnotationBlock(array(
            'Method returning the class name',
            new PhpAnnotation(self::ANNOTATION_RETURN, 'string __CLASS__'),
        ));
    }
    /**
     * @return PhpMethod
     */
    protected function getToStringMethod()
    {
        $method = new PhpMethod('__toString');
        $method->addChild('return __CLASS__;');
        return $method;
    }
    /**
     * @param StructAttributeModel $attribute
     * @return StructAttributeModel
     */
    protected function getStructAttribute(StructAttributeModel $attribute = null)
    {
        $struct = $this->getModel();
        if (empty($attribute) && $struct instanceof StructModel && $struct->getAttributes()->count() === 1) {
            $attribute = $struct->getAttributes()->offsetGet(0);
        }
        return $attribute;
    }
    /**
     * @param StructAttributeModel $attribute
     * @return StructModel|null
     */
    public function getModelFromStructAttribute(StructAttributeModel $attribute = null)
    {
        $model = null;
        $attribute = $this->getStructAttribute($attribute);
        if ($attribute instanceof StructAttributeModel) {
            $model = $attribute->getTypeStruct();
        }
        return $model;
    }
    /**
     * @param StructAttributeModel $attribute
     * @return StructModel|null
     */
    public function getRestrictionFromStructAttribute(StructAttributeModel $attribute = null)
    {
        $model = $this->getModelFromStructAttribute($attribute);
        if ($model instanceof StructModel && !$model->isRestriction()) {
            $model = null;
        }
        return $model;
    }
    /**
     * @param StructAttributeModel $attribute
     * @param bool $namespaced
     * @return string
     */
    public function getStructAttributeType(StructAttributeModel $attribute = null, $namespaced = false)
    {
        $attribute = $this->getStructAttribute($attribute);
        $inheritance = $attribute->getInheritance();
        if (empty($inheritance)) {
            $type = $attribute->getType();
        } else {
            $type = $inheritance;
        }
        if (!empty($type) && ($struct = $this->getGenerator()->getStruct($type))) {
            $inheritance = $struct->getTopInheritance();
            if (!empty($inheritance)) {
                $type = str_replace('[]', '', $inheritance);
            }
        }
        $model = $this->getModelFromStructAttribute($attribute);
        if ($model instanceof StructModel) {
            // issue #84: union is considered as string as it would be difficult to have a method that accepts multiple object types.
            // If the property has to be an object of multiple types => new issue...
            if ($model->isRestriction() || $model->isUnion()) {
                $type = self::TYPE_STRING;
            } elseif ($model->isStruct()) {
                $type = $model->getPackagedName($namespaced);
            } elseif ($model->isArray() && ($inheritanceStruct = $model->getInheritanceStruct()) instanceof StructModel) {
                $type = $inheritanceStruct->getPackagedName($namespaced);
            }
        }
        return $type;
    }
    /**
     * @param StructAttributeModel $attribute
     * @param bool $returnArrayType
     * @return string
     */
    protected function getStructAttributeTypeGetAnnotation(StructAttributeModel $attribute = null, $returnArrayType = true)
    {
        $attribute = $this->getStructAttribute($attribute);
        return sprintf('%s%s%s', $this->getStructAttributeTypeAsPhpType($attribute), $this->useBrackets($attribute, $returnArrayType) ? '[]' : '', $attribute->isRequired() ? '' : '|null');
    }
    /**
     * @param StructAttributeModel $attribute
     * @param bool $returnArrayType
     * @return string
     */
    protected function getStructAttributeTypeSetAnnotation(StructAttributeModel $attribute = null, $returnArrayType = true)
    {
        $attribute = $this->getStructAttribute($attribute);
        return sprintf('%s%s', $this->getStructAttributeTypeAsPhpType($attribute), $this->useBrackets($attribute, $returnArrayType) ? '[]' : '');
    }
    /**
     * @param StructAttributeModel $attribute
     * @param bool $returnArrayType
     * @return bool
     */
    protected function useBrackets(StructAttributeModel $attribute, $returnArrayType = true)
    {
        return $returnArrayType && $attribute->isArray();
    }
    /**
     * @param StructAttributeModel $attribute
     * @param bool $returnArrayType
     * @return string
     */
    protected function getStructAttributeTypeHint(StructAttributeModel $attribute = null, $returnArrayType = true)
    {
        $attribute = $this->getStructAttribute($attribute);
        return ($returnArrayType && $attribute->isArray()) ? self::TYPE_ARRAY : $this->getStructAttributeType($attribute, true);
    }
    /**
     * @param StructAttributeModel $attribute
     * @return string
     */
    public function getStructAttributeTypeAsPhpType(StructAttributeModel $attribute = null)
    {
        $attribute = $this->getStructAttribute($attribute);
        $attributeType = $this->getStructAttributeType($attribute, true);
        if (XsdTypes::instance()->isXsd($attributeType)) {
            $attributeType = self::getPhpType($attributeType);
        }
        return $attributeType;
    }
    /**
     * See http://php.net/manual/fr/language.oop5.typehinting.php for these cases
     * Also see http://www.w3schools.com/schema/schema_dtypes_numeric.asp
     * @param mixed $type
     * @param mixed $fallback
     * @return mixed
     */
    public static function getValidType($type, $fallback = null)
    {
        return XsdTypes::instance()->isXsd(str_replace('[]', '', $type)) ? $fallback : $type;
    }
    /**
     * See http://php.net/manual/fr/language.oop5.typehinting.php for these cases
     * Also see http://www.w3schools.com/schema/schema_dtypes_numeric.asp
     * @param mixed $type
     * @param mixed $fallback
     * @return mixed
     */
    public static function getPhpType($type, $fallback = self::TYPE_STRING)
    {
        return XsdTypes::instance()->isXsd(str_replace('[]', '', $type)) ? XsdTypes::instance()->phpType($type) : $fallback;
    }
}
