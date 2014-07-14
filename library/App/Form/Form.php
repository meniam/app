<?php

namespace App\Form;

class Form extends Fieldset
{
    const METHOD_POST = 'POST';

    const METHOD_GET = 'GET';

    /**
     * @var array
     */
    protected $attributes = array('method' => self::METHOD_POST);

    public function setAutocomplete($autocompleteFlag = false)
    {
        return $this->setAttribute('autocomplete', ($autocompleteFlag ? 'on' : 'off'));
    }

    /**
     * @return Element
     */
    public function getAutocomplete()
    {
        return $this->getAttribute('autocomplete');
    }

    /**
     * @param $enctype
     * @return Element
     */
    public function setEnctype($enctype)
    {
        return $this->setAttribute('enctype', $enctype);
    }

    /**
     * @return mixed|null
     */
    public function getEnctype()
    {
        return $this->getAttribute('enctype');
    }

    /**
     * @param $method
     * @return Form
     */
    public function setMethod($method)
    {
        return $this->setAttribute('method', strtoupper((string)$method));
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->getAttribute('method');
    }

    /**
     * @param $action
     * @return Form
     */
    public function setAction($action)
    {
        return $this->setAttribute('action', (string)$action);
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->getAttribute('action');
    }



    public function render($decorator = null)
    {
        if (!$decorator && !($decorator = $this->getDecorator())) {
            return '';
        }

        $twig = $this->getTwig();

        $this->setMultiplePosition(0);

        $i = 0;
        $body = '';
        $elements = array();
        foreach ($this->getIterator() as $element) {
            $element->setMultiplePosition($i++);


            /** @var $element Element */
            if ($render = $element->render()) {
                $body .= $render;

                $elements['render'][$element->getName()] = $render;
            }
        }

        $data = array('form' => $this->toArray(),
                      'element' => $elements,
                      'body' => $body);

        return $twig->render($decorator . '.twig',
            $data);

     }

    public function toArray()
    {
        $elementArray = array();
        foreach ($this->getIterator() as $element) {
            $elementArray[] = $element->toArray();
        }

        return array_merge(array(
                                'name' => $this->getName(),
                                'action' => $this->getAction(),
                                'method' => $this->getMethod()), $this->getAttributes());
    }
}