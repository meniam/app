<?php

namespace App\Form;

use Model\Result\Result;

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

        $view = $this->getView($decorator);
        $this->setMultiplePosition(0);

        $formArray = $this->toArray();
        $i = 0;
        foreach ($this->getIterator() as $element) {
            $element->setMultiplePosition($i++);

            /** @var $element Element */
            if ($render = $element->render()) {
//                if ($element->isMultiple()) {
                    $valueList = $element->getValue();
  //              }

                //if ($element->getName() == 'name') {
                  //  print_r($valueList);
                    //die;
                //}
                if (is_array($valueList) && !empty($valueList)) {
                    $renderElement = clone $element;

                    $elementName = $renderElement->getName();
                    $formArray[$elementName . '_label']   = $renderElement->getLabel();

                    $formArray[$elementName . '_value']   = $renderElement->getValue();
                    $formArray[$elementName . '_element']  = $renderElement->toArray();
                    $formArray[$elementName . '_valid']  = $renderElement->isValid();
                    $formArray[$elementName . '_not_valid']  = !$renderElement->isValid();


                    foreach ($valueList as $value) {
                        $renderElement = clone $element;
                        $renderElement->setValue($value);
                        if (isset($formArray[$elementName . '_render'])) {
                            $formArray[$elementName . '_render']   .= $renderElement->render();
                        } else {
                            $formArray[$elementName . '_render']    = $renderElement->render();
                        }
                        if (isset($formArray['element_render']['render'])) {
                            $formArray['element_render']['render']   .= $renderElement->render();
                        } else {
                            $formArray['element_render']['render']    = $renderElement->render();
                        }
                    }
                } else {
                    $elementName = $element->getName();
                    $formArray[$elementName . '_label']   = $element->getLabel();

                    $formArray[$elementName . '_value']   = $element->getValueAsString();
                    $formArray[$elementName . '_render']   = $render;
                    $formArray['element_render']['render'] = $render;
                    $formArray[$elementName . '_element']  = $element->toArray();
                    $formArray[$elementName . '_valid']  = $element->isValid();
                    $formArray[$elementName . '_not_valid']  = !$element->isValid();
                }
            }
        }

        // Рендерим сообщения об ошибке для одного элемента
        if ($view->hasContext('form_context/message_context')) {
            $messageList = $this->getErrorList();
            //print_r($messageList);
            foreach ($messageList as $messages) {
                foreach ($messages as $code => $message) {
                    $formArray['message_context'][] = array('code' => $code, 'message' => $message);
                }
            }
        }

        $view->set($formArray);
        $view->block('form_context', $formArray);
        return $view->parse();
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
                                'elements' => $elementArray,
                                'method' => $this->getMethod()), $this->getAttributes());
    }
}