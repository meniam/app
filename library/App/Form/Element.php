<?php


namespace App\Form;

use App\Filter\Filter;
use App\Form\Exception\InvalidArgumentException;
use App\Validator\Validator;
use Model\Mysql\AbstractModel;
use Model\Result\Result;
use Model\Validator\ValidatorSet;
use Traversable;
use Zend\Filter\AbstractFilter;
use Zend\InputFilter\InputFilter;
use Zend\Stdlib\ArrayUtils;
use Zend\Validator\AbstractValidator;

class Element
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $errors = array();

    /**
     * @var array
     */
    protected $value = array();

    /**
     * @var array
     */
    protected $attributes = array();

    /**
     * @var array
     */
    protected $labelAttributes;

    /**
     * @var array custom options
     */
    protected $options = array();

    /**
     * Флаг, котороый показывает что фильтры не инициализированы
     *
     * @var bool
     */
    protected $dirtyFilters = false;

    /**
     * Флаг, котороый показывает что валидаторы не инициализированы
     *
     * @var bool
     */
    protected $dirtyValidators = false;

    /**
     * @var Fieldset
     */
    protected $form;

    /**
     * Разрешено ли пустое значение
     *
     * @var bool
     */
    protected $allowEmpty = true;

    /**
     * Пустой элемент в multiple полях
     *
     * @var null
     */
    protected $emptyOption = null;


    /**
     * Опции для выбораx
     *
     * @var array
     */
    protected $valueOptions = array();

    /**
     * @param        $message
     * @param string $code
     * @param string $field
     *
     * @return $this
     */
    public function addError($message, $code = 'general', $field = 'global')
    {
        $this->errors[(string) $field][(string) $code] = (string)$message;
        return $this;
    }

    /**
     * @param array $errorList
     *
     * @return $this
     */
    public function addErrorList(array $errorList)
    {
        foreach ($errorList as $field => $messageList) {
            foreach ($messageList as $code => $message) {
                $this->addError($message, $code, $field);
            }
        }

        return $this;
    }

    /**
     * Получить массив
     * @return mixed
     */
    public function getErrors($withChild = true)
    {
        return $this->getErrorList();
    }

    /**
     * @return array
     */
    public function getErrorList()
    {
        return $this->errors;
    }

    /**
     * @param ValidatorSet $validatorSet
     *
     * @return $this
     */
    public function addErrorFromValidatorSet(ValidatorSet $validatorSet)
    {
        if ($messageList = $validatorSet->getMessageList()) {
            return $this->addErrorList($messageList);
        }

        return $this;
    }

    /**
     * @param  null|int|string  $name    Optional name for the element
     * @param array             $attributes
     * @param  array            $options Optional options for the element
     * @return \App\Form\Element
     */
    public function __construct($name = null, $attributes = array(), $options = array())
    {
        if (null !== $name) {
            $this->setAttribute('name', $name);
            $this->setName($name);
        }

        if (!empty($options)) {
            $this->setOptions($options);
        }

        if (!empty($attributes)) {
            $this->setAttributes($attributes);
        }

        $this->init();
    }

    /**
     * Если значение будет равно нулю, то поле выводиться не будет
     *
     * @param null|string $emptyOption
     * @return $this
     */
    public function setEmptyOption($emptyOption)
    {
        $this->emptyOption = $emptyOption;
        return $this;
    }

    /**
     * @return null
     */
    public function getEmptyOption()
    {
        return $this->emptyOption;
    }

    /**
     * @param  array $options
     * @return $this
     */
    public function setValueOptions(array $options)
    {
        $this->valueOptions = $options;
        return $this;
    }

    /**
     * @return array
     */
    public function getValueOptions()
    {
        return $this->valueOptions;
    }

    /**
     * @param $element
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getNameFromMixed($element)
    {
        if (is_array($element) && isset($element['name'])) {
            $name = $element['name'];
        } elseif ($element instanceof Element) {
            $name = $element->getName();
        } elseif (is_scalar($element)) {
            $name = (string)$element;
        } else {
            throw new InvalidArgumentException('Unknown element type');
        }

        return (string)$name;
    }

    /**
     * @param bool $isMultipleFlag
     * @return Element
     */
    public function setIsMultiple($isMultipleFlag = true)
    {
        return $this->setOption('is_multiple', $isMultipleFlag);
    }

    /**
     * @return bool
     */
    public function isMultiple()
    {
        return (bool)$this->getIsMultiple();
    }

    /**
     * @return mixed|NULL
     */
    public function getIsMultiple()
    {
        return $this->getOption('is_multiple');
    }

    /**
     * This function is automatically called when creating element with factory. It
     * allows to perform various operations (add elements...)
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * @param $id
     * @return Element
     */
    public function setId($id)
    {
        $this->setAttribute('id', (string)$id);
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * @param $multipleFlag
     * @return Element
     */
    public function setMultiple($multipleFlag)
    {
        $this->setOption('multiple', (bool)$multipleFlag);
        return $this;
    }

    /**
     * @return bool
     */
    public function getMultiple()
    {
        return (bool)$this->getOption('multiple');
    }

    /**
     * @param $label
     * @return Element
     */
    public function setLabel($label)
    {
        $this->setAttribute('label', (string)$label);
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getLabel()
    {
        return $this->getAttribute('label');
    }

    /**
     * @param $title
     * @return Element
     */
    public function setTitle($title)
    {
        $this->setAttribute('title', (string)$title);
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getTitle()
    {
        return $this->getAttribute('title');
    }

    /**
     * @param $placeholder
     * @return Element
     */
    public function setPlaceholder($placeholder)
    {
        return $this->setAttribute('placeholder', (string)$placeholder);
    }

    /**
     * @return mixed|null
     */
    public function getPlaceholder()
    {
        return $this->getAttribute('placeholder');
    }

    /**
     * Get defined options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set options for an element. Accepted options are:
     *
     * @param  array|Traversable $options
     * @return Element
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        foreach ($options as $k => $v) {
            $this->setOption($k, $v);
        }

        return $this;
    }

    /**
     * Remove a single attribute
     *
     * @param string $key
     * @return Element
     */
    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
        return $this;
    }

    /**
     * Remove many attributes at once
     *
     * @param array $keys
     * @return Element
     */
    public function removeAttributes(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->attributes[$key]);
        }

        return $this;
    }

    /**
     * Clear all attributes
     *
     * @return Element
     */
    public function clearAttributes()
    {
        $this->attributes = array();
        return $this;
    }

    /**
     * @param $decorator
     * @return Element
     */
    public function setDecorator($decorator)
    {
        return $this->setAttribute('decorator', (string)$decorator);
    }

    /**
     * @param $type
     * @return Element
     */
    public function setType($type)
    {
        return $this->setAttribute('type', $type);
    }

    /**
     * @param string $default
     * @return string
     */
    public function getType($default = 'text')
    {
        if (!$type = $this->getAttribute('type')) {
            return $default;
        }

        return $type;
    }

    /**
     * @return array
     */
    public function getLabelAttributes()
    {
        $attributes = $this->getAttributes();

        $result = array();
        foreach ($attributes as $attribute => $value) {
            if (substr($attribute, 0, 6) == 'label_') {
                $result[substr($attribute, 6)] = $value;
            }
        }

        return $result;
    }

    /**
     * @param null|string $decorator
     * @return string
     */
    public function render($decorator = null)
    {
        if (!$decorator && !($decorator = $this->getDecorator())) {
            return '';
        }

        $view = $this->getView($decorator);

        // Если не находим нужных контекстов, то возвращаем пустую строку
        if (!$view->hasContext('input_context') && !$view->hasContext('multiple_input_context')) {
            return '';
        }

        // Label Atributes это атрибуты начинающиеся с label_ (только эту часть мы вырезаем)
        $labelAttributes = $this->getLabelAttributes();
        $labelData = array('id' => $this->getId(), 'label' => $this->getLabel());

        if (!empty($labelAttributes)) {
            $labelData = array_merge($labelData, $labelAttributes);
        }

        ///////////////////////////////////////////////////////////////////////////////////
        // Рендеринг Single элемента
        ///////////////////////////////////////////////////////////////////////////////////
        if ($view->hasContext('input_context')) {
            $data = $this->toArray();

            // Рендерим контекст элемента
            $view->block('input_context', $this->toArray());

            // Рендерим label
            if ($view->hasContext('input_context/label_context')) {
                $view->block('input_context/label_context', $labelData);
            }

            // Рендерим значения
            if ($view->hasContext('input_context/value_context')) {
                //$values = $this->getValue();
                $dataValues = $this->getValueAsString();
                foreach ((array)$dataValues as $k => $v) {
                    $view->block('input_context/value_context', array('key' => $k, 'value' => $v));
                }
            }

            // Рендерим варианты значения
            if ($view->hasContext('input_context/value_option_context')) {
                $valueOptions = $this->getValueOptions();
                foreach ($valueOptions as $k => $v) {
                    $isSelected = false;
                    $dataValues = $this->getValue();
                    foreach ((array)$dataValues as $dataValue) {
                        if ($k == $dataValue) {
                            $isSelected = true;
                            break;
                        }
                    }
                    $view->block('input_context/value_option_context', array('key' => $k, 'value' => $v, 'selected' => $isSelected));
                }
            }

            // Рендерим сообщения об ошибке для одного элемента
            if ($view->hasContext('input_context/message_context')) {
                $errorList = $this->getErrorList();
                foreach ($errorList as $field => $messages) {
                    foreach ($messages as $code => $message) {
                        $view->block('input_context/message_context', array('field' => $field, 'code' => $code, 'message' => $message));
                    }
                }
            }
        }/** else

        ///////////////////////////////////////////////////////////////////////////////////
        // Рендеринг Multiple элементов
        ///////////////////////////////////////////////////////////////////////////////////
        if ($view->hasContext('multiple_input_context')) {
            // рендеринг multiple_input
            $values = $this->getValue();
            $view = $this->getView($decorator);

            // Рендерим label
            if ($view->hasContext('/multiple_input_context/label_context')) {
                $view->block('/multiple_input_context/label_context', $labelData);
            }

            $this->isValid();
            // Рендерим общие сообщения об ошибке
            if ($view->hasContext('multiple_input_context/message_context')) {
                $errorList = $this->getErrorList();
                foreach ($errorList as $field => $messages) {
                    foreach ($messages as $code => $message) {
                        $view->block('multiple_input_context/message_context', array('field' => $field, 'code' => $code, 'message' => $message));
                    }
                }
            }

            foreach ($values as $k => $value) {
                $elementArray = $this->toArray();
                $elementArray['value'] = $value;

                $view->block('multiple_input_context/input_context', $elementArray);

                if ($view->hasContext('multiple_input_context/input_context/label_context')) {
                    $view->block('multiple_input_context/input_context/label_context', $labelData);
                }

                if ($messageList = $this->getMessages($k)) {
                    foreach ($messageList as $code => $message) {
                        $view->block('multiple_input_context/input_context/message_context', array('code' => $code, 'message' => $message));
                    }
                }
            }
        }*/
        return $view->parse();
    }

    /**
     * @param null $decorator
     * @param null $context
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function renderLabel($decorator = null, $context = null)
    {
        if (!$decorator && !($decorator = $this->getDecorator())) {

            return '';
        }

        $view = $this->getView($decorator);
        $labelData = array('id' => $this->getId(), 'label' => $this->getLabel());

        $result = '';

        if ($context) {
            if ($view->hasContext($context)) {
                return $view->fetch($context, $labelData);
            } else {
                throw new InvalidArgumentException('Unknown context - ' . $context);
            }
        }

        if ($view->hasContext('label_context')) {
            $result = $view->fetch('label_context', $labelData);
        } elseif ($view->hasContext('input_context/label_context')) {
            $result = $view->fetch('input_context/label_context', $labelData);
        } elseif ($view->hasContext('multiple_input_context/label_context')) {
            $result = $view->fetch('multiple_input_context/label_context', $labelData);
        } elseif ($view->hasContext('multiple_input_context/input_context/label_context')) {
            $result = $view->fetch('multiple_input_context/input_context/label_context', $labelData);
        }

        return $result;
    }

    /**
     * @return null
     */
    public function getDecorator()
    {
        if (!$this->hasAttribute('decorator')) {
            return null;
        }

        return $this->getAttribute('decorator');
    }

    /**
     * Does the element has a specific attribute ?
     *
     * @param  string $key
     * @return bool
     */
    public function hasAttribute($key)
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Retrieve a single element attribute
     *
     * @param  $key
     * @return mixed|null
     */
    public function getAttribute($key)
    {
        if ($key == 'value') {
            return $this->getValue();
        }

        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        return $this->attributes[$key];
    }

    /**
     * @param $decorator
     * @throws Exception\InvalidArgumentException
     * @return \App\Mvc\View
     */
    protected function getView($decorator)
    {
        $viewPathArray = $this->getViewPath();
        $filename = $decorator . '.phtml';

        foreach ($viewPathArray as $viewPath) {
            if (is_file($viewPath . DIRECTORY_SEPARATOR . $filename)) {
                ini_set('blitz.path', $viewPath . DIRECTORY_SEPARATOR);
                return new \App\Mvc\View($filename, $viewPath . DIRECTORY_SEPARATOR);
            }
        }

        throw new InvalidArgumentException('Decorator ' . $decorator . ' not found in path');
    }
    /**
     * Получить пути где лежат декораторы
     *
     * @return array
     */
    public function getViewPath()
    {
        $viewPath = isset($this->options['view_path']) && is_array($this->options['view_path']) ? $this->options['view_path'] : array();

        if ($this->getForm()) {
            $viewPath = array_merge($viewPath, $this->getForm()->getViewPath());
        }

        if (count($viewPath) > 1) {
            $viewPath = array_unique($viewPath);
        }

        return $viewPath;
    }

    /**
     * Return the specified option
     *
     * @param string $option
     * @return mixed
     */
    public function getOption($option)
    {
        if ($option == 'view_path') {
            return $this->getViewPath();
        } elseif (!isset($this->options[$option])) {
            return null;
        }

        return $this->options[$option];
    }

    /**
     * @return $this
     */
    public function getTopForm()
    {
        if ($this->form) {
            return $this->form->getTopForm();
        }

        return $this;
    }

    /**
     * @return Fieldset
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param Fieldset $form
     * @return Element
     */
    public function setForm(Fieldset $form)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Set a single element attribute
     *
     * @param  string $key
     * @param  mixed  $value
     * @return Element
     */
    public function setAttribute($key, $value)
    {
        // Do not include the value in the list of attributes
        if ($key === 'value') {
            $this->setValue($value);
        } elseif ($key === 'name') {
            $this->attributes[$key] = $value;
            $this->setName($value);
        } else {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function toArray()
    {
        $array = array('name'  => $this->getInputName(),
                       'label' => $this->getAttribute('label'),
                       'value' => $this->getValueAsString(),
                       'on_load' => $this->getAttribute('onload'),
                       'is_multiple' => $this->getMultiple()
                       );

        if (!$this->isValid()) {
            $errorList = $this->getErrors();
            foreach ($errorList as $field => $messageList) {
                if (is_array($messageList)) {
                    foreach ($messageList as $code => $message) {
                        $array['errors'][] = array(
                            'code' => $code,
                            'message' => $message
                        );
                    }
                }
            }
        }

        return array_merge($this->getAttributes(), $array);
    }

    /**
     * Get value for name
     *
     * @return string|int
     */
    public function getName()
    {
        return $this->getAttribute('name');
    }


    protected $multiplePosition;

    /**
     * @param mixed $multiplePosition
     */
    public function setMultiplePosition($multiplePosition)
    {
        $this->multiplePosition = $multiplePosition;
    }

    /**
     * @return mixed
     */
    public function getMultiplePosition()
    {
        return $this->multiplePosition;
    }

    /**
     * @return string
     */
    public function getInputName()
    {
        if (!$name = $this->getName()) {
            return '';
        }

        // Есть родительская форма
        if ($this->getForm() && $formName = $this->getForm()->getInputName()) {
            $name = $formName . '[' . $name . ']';
        }

        if ($this->getIsMultiple() == true) {
            $name .= '[' . $this->getMultiplePosition() . ']';
        } elseif (!$this->getForm() && ($this instanceof Fieldset)) {
            $name = $this->getName();
        }

        return $name;
    }

    /**
     * Set value for name
     *
     * @param  string $name
     * @return Element
     */
    public function setName($name)
    {
        $name = (string)$name;

        if (substr($name, -2) == '[]') {
            $name = substr($name, 0, -2);
        }

        $this->attributes['name'] = $name;
        $this->name = $name;
        return $this;
    }

    /**
     * Retrieve the element filtered value
     *
     * @return array
     */
    public function getValue()
    {
        $values = (array)$this->value;
        foreach ($values as &$v) {
            /*if ($modelLink = $this->getModelLink()) {
                $modelName = $modelLink[0];
                $v = $modelName::getInstance()->filterValue($v, $modelLink[1]);
            }*/

            if (empty($v)) {
                continue;
            }

            if ($filters = $this->getFilters()) {
                foreach ($filters as $filter) {
                    if (!$v = $filter->filter($v)) {
                        break;
                    }
                }
            }
        }

        return isset($values[1]) ? $values : reset($values);
    }

    public function getValueAsString()
    {
        $value = $this->getValue();
        if ($value && is_array($value)) {
            return implode(' ', $value);
        }

        return (string)$this->getValue();
    }

    public function removeValue()
    {
        $this->value = array();
    }

    /**
     * Set the element value
     *
     * @param  mixed $value
     * @return Element
     */
    public function setValue($value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }

        foreach ($value as &$v) {
            $v = $this->prepareValue($v);
        }

        $this->value = $value;
        return $this;
    }

    /**
     * @param      $value
     * @param null $key
     * @return $this
     */
    public function addValue($value, $key = null)
    {
        $value = $this->prepareValue($value);

        if ($key) {
            $this->value[$key] = $value;
        } else {
            $this->value[] = $value;
        }

        return $this;
    }

    /**
     * @param $value
     * @return string
     */
    public function prepareValue($value)
    {
        return htmlspecialchars($value);
    }

    /**
     * @return mixed
     */
    public function getModelLink()
    {
        return $this->getOption('model_link');
    }

    /**
     * Получить список фильтров
     *
     * @return array|AbstractFilter[]
     */
    public function getFilters()
    {
        if ($this->dirtyFilters && $filters = $this->getOption('filters')) {

            foreach ($filters as $k => $filter) {
                if (is_array($filter)) {
                    $filters[$k] = Filter::getFilterInstance($filter['type'], $filter['params']);
                }
            }

            $this->setOption('filters', $filters);
            $this->dirtyFilters = false;
        }

        return $this->getOption('filters');
    }

    /**
     * Получить список валидаторов
     *
     * @return array|AbstractValidator[]
    public function getValidators()
    {
        if ($this->dirtyValidators && $validators = $this->getOption('validators')) {

            foreach ($validators as $k => $validator) {
                if (is_array($validator)) {
                    $validators[$k] = Validator::getValidatorInstance($validator['type'], $validator['params']);
                }
            }

            $this->setOption('validators', $validators);
            $this->dirtyValidators = false;
        }

        return $this->getOption('validators');
    }
     */

    /**
     * @param $name
     * @param $value
     * @throws Exception\InvalidArgumentException
     * @return Element
     */
    public function setOption($name, $value)
    {
        if ($name == 'view_path') {
            if (!is_array($value)) {
                $value = array($value);
            }

            foreach ($value as &$v) {
                $v = realpath($v);

                if (empty($v)) {
                    throw new InvalidArgumentException('View path not exists');
                }
            }

            $this->options['view_path'] = $value;
        } elseif ($name == 'model_link') {
            $this->setModelLink($value);
        } elseif ($name == 'filters') {
            $this->options['filters'] = array();

            if (!is_array($value)) {
                throw new InvalidArgumentException('Filters must be an array');
            }

            /*foreach ($value as $filter) {
                $this->addFilter($filter);
            }*/
        }/* elseif ($name == 'validators') {
            $this->options['validators'] = array();

            if (!is_array($value)) {
                throw new InvalidArgumentException('Validators must be an array');
            }

            foreach ($value as $validator) {
                $this->addValidator($validator);
            }
        } */ else {
            $this->options[(string)$name] = $value;
        }
        return $this;
    }

    /**
     * @param      $model
     * @param null $field
     * @return $this
     */
    public function setModelLink($model, $field = null)
    {
        if (empty($model)) {
            unset($this->options['model_link']);
        } elseif (is_string($model) && !$field) {
            $this->options['model_link'] = array($model, $this->getName());
        } elseif (is_array($model) && count($model) == 1) {
            $this->options['model_link'] = array(reset($model), $this->getName());
        } else {
            $this->options['model_link'] = array((string)$model, ($field ? $field : $this->getName()));
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function removeModelLink()
    {
        return $this->setModelLink(null);
    }

    /**
     * Retrieve all attributes at once
     *
     * @return array|Traversable
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set many attributes at once
     *
     * Implementation will decide if this will overwrite or merge.
     *
     * @param  array|Traversable $arrayOrTraversable
     * @return Element
     * @throws Exception\InvalidArgumentException
     */
    public function setAttributes($arrayOrTraversable)
    {
        if (!is_array($arrayOrTraversable) && !$arrayOrTraversable instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable argument; received "%s"',
                __METHOD__,
                (is_object($arrayOrTraversable) ? get_class($arrayOrTraversable) : gettype($arrayOrTraversable))
            ));
        }
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * @param $filters
     * @return $this
    public function setFilters($filters)
    {
        $this->options['filters'] = array();

        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }

        return $this;
    }
     */

    /**
     * @param       $type
     * @param array $params
     * @throws Exception\InvalidArgumentException
     * @internal param $filter
     * @return $this
    public function addFilter($type, array $params = null)
    {
        if (is_array($type)) {
            if (is_int(key($type))) {
                $filter = array('type' => $type[0],
                                'params' => (isset($type[1]) ? $type[1] : array()));
            } elseif (isset($type['type'])) {
                if (!isset($type['params'])) {
                    $type['params'] = array();
                }
                $filter = $type;
            } else {
                throw new InvalidArgumentException('Unknown type');
            }
            $this->dirtyFilters = true;
        } elseif (is_scalar($type)) {
            $params = empty($params) ? array() : $params;

            $filter = array('type' => (string) $type,
                            'params' => $params);
            $this->dirtyFilters = true;
        } elseif ($type instanceof AbstractFilter) {
            $filter = $type;
        } else {
            throw new InvalidArgumentException('Unknown type');
        }

        $this->addOption('filters', $filter);
        return $this;
    }
     */

    /**
     * @param $name
     * @param $value
     * @return Element
     */
    public function addOption($name, $value)
    {
        $name = (string)$name;
        if (isset($this->options[$name]) && !is_array($this->options[$name])) {
            $this->options[$name] = array($this->options[$name]);
        }

        $this->options[$name][] = $value;
        return $this;
    }

    /**
     * @param $filter
     * @return bool
    public function hasFilter($filter)
    {
        if ($filters = $this->getOption('filters')) {
            foreach ($filters as $_filter) {
                if ($filter == $_filter) {
                    return true;
                }
            }
        }

        return false;
    }
     */

    /**
     * @param null|object $filter
     * @return null
    public function getFilter($filter = null)
    {
        if ($filter && $filters = $this->getOption('filters')) {
            foreach ($filters as $_filter) {
                if ($filter == $_filter) {
                    return $_filter;
                }
            }
            return null;
        } else {
            return $this->getFilters();
        }
    }
     */

    /**
     * @param       $type
     * @param array $params
     * @throws Exception\InvalidArgumentException
     * @return $this
    public function addValidator($type, array $params = null)
    {
        if (is_array($type)) {
            if (is_int(key($type))) {
                $validator = array('type' => $type[0],
                                'params' => (isset($type[1]) ? $type[1] : array()));
            } elseif (isset($type['type'])) {
                $validator = $type;
            }
            $this->dirtyValidators = true;
        } elseif (is_scalar($type)) {
            $params = empty($params) ? array() : $params;

            $validator = array('type' => (string) $type,
                            'params' => $params);
            $this->dirtyValidators = true;
        } elseif ($type instanceof AbstractValidator) {
            $validator = $type;
        } elseif ($type instanceof InputFilter) {
            $validator = $type;
        }

        if (!isset($validator)) {
            throw new InvalidArgumentException('Unknown type');
        }

        $this->addOption('validators', $validator);
        return $this;
    }
     */

    /**
     * @param $validator
     * @return bool
    public function hasValidator($validator)
    {
        if ($validators = $this->getValidators()) {
            foreach ($validators as $_validator) {
                if ($validator == $_validator) {
                    return true;
                }
            }
        }

        return false;
    }
     */

    /**
     * @param null|object $validator
     * @return null
    public function getValidator($validator = null)
    {
        if ($validator && $validators = $this->getValidators()) {
            foreach ($validators as $_validator) {
                if ($validator == $_validator) {
                    return $_validator;
                }
            }
            return null;
        } else {
            return $this->getValidators();
        }
    }
     */

    /**
     * Retrieve the element value
     *
     * @return array
     */
    public function getRawValue()
    {
        return (array)$this->value;
    }

    /**
     * @param $class
     * @return Element
     */
    public function addClass($class)
    {
        $currentClass = $this->getClass();
        $classArray = explode(' ', $currentClass);

        if (!in_array($class, $classArray)) {
            $this->setClass($currentClass . ' ' . $class);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return (string)$this->getAttribute('class');
    }

    /**
     * @param $class
     * @return Element
     */
    public function setClass($class)
    {
        return $this->setAttribute('class', (string)$class);
    }

    /**
     * @param $class
     * @return Element
     */
    public function removeClass($class)
    {
        $currentClass = $this->getClass();
        $classArray = explode(' ', $currentClass);

        foreach ($classArray as $k => $_class) {
            if ($_class == $class) {
                unset($classArray[$k]);
            }
        }

        $this->setClass(implode(' ', $classArray));
        return $this;
    }

    /**
     * @param $allowEmpty
     * @return $this
     */
    public function setAllowEmpty($allowEmpty)
    {
        $this->allowEmpty = (bool) $allowEmpty;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowEmpty()
    {
        return $this->allowEmpty;
    }

    /**
     * Проверить элемент на валидность
     *
     * Если элемент не валиден вернется false
     * сообщения об ошибках запишутся в messages
     *
     * @return bool
     */
    public function isValid()
    {
        return !count($this->errors);
    }

    /**
     * Get validation error messages, if any.
     *
     * Returns a list of validation failure messages, if any.
     *
     * @param null $valueNumber
     * @return array
    public function getMessages($valueNumber = null)
    {
        if (is_null($valueNumber)) {
            return $this->messages;
        } elseif (isset($this->messages[$valueNumber])) {
            return $this->messages[$valueNumber];
        } else {
            return array();
        }
    }
     */

    /**
     * Set a list of messages to report when validation fails
     *
     * @param  array|Traversable $messages
     * @param int                $valueNumber
     * @throws Exception\InvalidArgumentException
     * @return Element
    public function setMessages($messages, $valueNumber = 0)
    {
        if (!is_array($messages) && !$messages instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable object of validation error messages; received "%s"',
                __METHOD__,
                (is_object($messages) ? get_class($messages) : gettype($messages))
            ));
        }

        $this->messages[$valueNumber] = $messages;
        return $this;
    }

    public function addMessages(array $messages)
    {
        foreach ($messages as $k => $v) {
            $this->messages[$k] = $v;
        }

        return $this;
    }
     */


    /**
     * @return $this
    public function clearMessages()
    {
        $this->messages = array();
        return $this;
    }
     */

    /**
     * @param Result $result
     * @return $this
     */
    public function addResult(Result $result)
    {
        foreach ($result->getErrorList() as $field => $errorList) {
            $childElement = $this->getChild($field);

            if ($childElement && $childElement instanceof Element) {
                foreach ($errorList as $code => $message) {
                    $childElement->addError($message, $code, $field);
                }
            } else {
                $this->addErrorList($errorList);
            }
        }

        foreach ($result->getChildList() as $name => $child) {
            list($name) = explode('.', $name, 2);

            $childElement = $this->getChild($name);

            if ($childElement && $childElement instanceof Element) {
                $childElement->addResult($child);
            } elseif (!$childElement) {
                $this->getTopForm()->addResult($child);
            }
        }

        return $this;
    }

    /**
     * @param      $name
     *
     * @return Element|Fieldset|Form|bool
     */
    public function getChild($name)
    {
        if (isset($this->iterator)) {
            foreach ($this->getIterator() as $elementOrFieldset) {
                if ($name == $elementOrFieldset->getName()) {
                    return $elementOrFieldset;
                }
            }
        }
        return false;
    }

}