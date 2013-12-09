<?php

namespace App\Form;
use Zend\Stdlib\PriorityQueue;

class Fieldset extends Element
{
    /**
     * @var array
     */
    protected $fieldsetArray = array();

    /**
     * @var array
     */
    protected $elementArray = array();

    /**
     * @var array
     */
    protected $byName = array();

    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var \Zend\Stdlib\PriorityQueue
     */
    protected $iterator;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @param  null|int|string  $name    Optional name for the element
     * @param array             $attributes
     * @param  array            $options Optional options for the element
     */
    public function __construct($name = null, $attributes = array(), $options = array())
    {
        $this->iterator = new PriorityQueue();
        parent::__construct($name, $attributes, $options);
    }

    /**
     * @param mixed $data
     * @param bool  $isPopulate
     * @return Element
     */
    public function setValue($data, $isPopulate = true)
    {
        return $this->setData($data, $isPopulate);
    }

    /**
     * @param      $data
     * @param bool $isPopulate
     * @return Fieldset
     */
    public function setData($data, $isPopulate = true)
    {
        if ($this->getIsMultiple()) {
            $this->data = reset($data);
        } else {
            $this->data = $data;
        }

        if ($isPopulate) {
            $this->populateData();
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function removeData()
    {
        foreach ($this->getIterator() as $elementOrFieldset) {
            $elementOrFieldset->removeValue();
        }

        $this->data = null;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRawData()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = array();
        foreach ($this->getIterator() as $elementOrFieldset) {
            $data[$elementOrFieldset->getName()] = $elementOrFieldset->getValue();
        }

        return $data;
    }

    /**
     * Применить данные к форме
     *
     * @return Fieldset
     */
    public function populateData()
    {
        if (!is_array($this->data) || empty($this->data)) {
            return $this;
        }

        $name = $this->getName();

        $data = isset($this->data[$name]) ? $this->data[$name] : $this->data;

        foreach ($data as $k => $v) {
            /** @var $elementOrFieldset Element */
            if (is_int($k) || !$elementOrFieldset = $this->get($k)) {
                continue;
            }

            $elementOrFieldset->setValue($v);
        }

        return $this;
    }

    public function add($elementOrFieldset, $flags = array())
    {
        if (is_array($elementOrFieldset) || ($elementOrFieldset instanceof \Traversable && !$elementOrFieldset instanceof Element)) {
            $elementOrFieldset = $this->getFormFactory()->create($elementOrFieldset);
        }

        if (!$elementOrFieldset instanceof Element) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s requires that $elementOrFieldset be an object implementing %s; received "%s"',
                __METHOD__,
                __NAMESPACE__ . '\Element',
                (is_object($elementOrFieldset) ? get_class($elementOrFieldset) : gettype($elementOrFieldset))
            ));
        }

        $name = $elementOrFieldset->getName();
        if ((null === $name || '' === $name)
            && (!array_key_exists('name', $flags) || $flags['name'] === '')
        ) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: element or fieldset provided is not named, and no name provided in flags',
                __METHOD__
            ));
        }

        if (array_key_exists('name', $flags) && $flags['name'] !== '') {
            $name = $flags['name'];

            // Rename the element or fieldset to the specified alias
            $elementOrFieldset->setName($name);
        }

        $elementOrFieldset->setForm($this);

        $order = 0;
        if (array_key_exists('priority', $flags)) {
            $order = $flags['priority'];
        }
        $this->iterator->insert($elementOrFieldset, $order);

        $this->byName[$name] = $elementOrFieldset;

        if ($elementOrFieldset instanceof Fieldset) {
            $this->fieldsetArray[$name] = $elementOrFieldset;
            return $this;
        }

        $this->elementArray[$name] = $elementOrFieldset;
        return $this;
    }

    /**
     * Does the fieldset have an element/fieldset by the given name?
     *
     * @param  string $elementOrFieldset
     * @return bool
     */
    public function has($elementOrFieldset)
    {
        return array_key_exists($elementOrFieldset, $this->byName);
    }

    /**
     * Retrieve a named element or fieldset
     *
     * @param  string $elementOrFieldset
     * @return ElementInterface
     */
    public function get($elementOrFieldset)
    {
        if (!$this->has($elementOrFieldset)) {
            return null;
        }
        return $this->byName[$elementOrFieldset];
    }

    /**
     * Remove a named element or fieldset
     *
     * @param  string $elementOrFieldset
     * @return FieldsetInterface
     */
    public function remove($elementOrFieldset)
    {
        if (!$this->has($elementOrFieldset)) {
            return $this;
        }

        $entry = $this->byName[$elementOrFieldset];
        unset($this->byName[$elementOrFieldset]);
        $this->iterator->remove($entry);

        if ($entry instanceof FieldsetInterface) {
            unset($this->fieldsetArray[$elementOrFieldset]);
            return $this;
        }

        unset($this->elementArray[$elementOrFieldset]);
        return $this;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->iterator->count();
    }

    /**
     * @return \Zend\Stdlib\PriorityQueue|Form[]|Fieldset[]|Element[]
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * Compose a form factory to use when calling add() with a non-element/fieldset
     *
     * @param  Factory $factory
     * @return Form
     */
    public function setFormFactory(Factory $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Retrieve composed form factory
     *
     * Lazy-loads one if none present.
     *
     * @return Factory
     */
    public function getFormFactory()
    {
        if (null === $this->factory) {
            $this->setFormFactory(new Factory());
        }

        return $this->factory;
    }

    public function isValid()
    {
        if (count($this->errors)) {
            return false;
        }

        foreach ($this->getIterator() as $child) {
            if (!$child->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получить массив
     * @return mixed
     */
    public function getErrors($withChild = true)
    {
        $errors = parent::getErrors();

        if ($withChild && $this->count()) {
            $element = $this->getIterator();
            foreach ($element as $childResult) {
                if ($eList = $childResult->getErrors($withChild)) {
                    $errors[$childResult->getName()] = $eList;
                }
            }
        }

        return $errors;
    }

    /*
    public function getMessages($valueNumber = null)
    {
        if ($this->messages) {
            return $this->messages;
        }

        foreach ($this->getIterator() as $child) {
            if ($messages = $child->getMessages()) {
                $this->setMessages(reset($messages));
            }
        }

        return $this->messages;
    }*/
}
