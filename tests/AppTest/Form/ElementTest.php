<?php

namespace AppTest\Form;

require_once FIXTURES_PATH . '/Form/TestModel.php';

use App\Filter\Filter;
use App\Form\Exception\InvalidArgumentException;
use App\Form\Factory;
use App\Form\Fieldset;
use App\Form\Form;
use App\Validator\Validator;
use AppTest\TestCase as ParentTestCase;
use App\Form\Element;
use Zend\Validator\NotEmpty;

class ElementTest extends ParentTestCase
{

    public function testType()
    {
        $element = new Element();
        $this->assertEquals('text', $element->getType());
        $this->assertEquals('password', $element->getType('password'));

        $this->assertEquals('password', $element->setType('password')->getType());
    }




    public function testSetValueOptions()
    {
        $element = new Element('test');

        $options = array('0' => 'test');
        $element->setValueOptions($options);

        $this->assertEquals($options, $element->getValueOptions());

    }

    public function testEmptyOption()
    {
        $element = new Element('test');
        $element->setEmptyOption('test');
        $this->assertEquals('test', $element->getEmptyOption());
    }

    public function testMultiple()
    {
        $element = new Element('test');
        $this->assertFalse($element->getMultiple());
        $element->setMultiple(true);
        $this->assertTrue($element->getMultiple());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testOptions()
    {
        $element = new Element('test',array(), array('opt1' => 'value'));
        $this->assertInternalType('null', $element->getOption('opt_unknown'));
        $this->assertEquals('value', $element->getOption('opt1'));
        $this->assertEquals(array('opt1' => 'value'), $element->getOptions());

        $element->setOptions(array('model_link' => array('Model\TestModel')));
        $this->assertEquals(2, count($element->getModelLink()));

        $element = new Element('test', array(), array('opt1' => 'value'));
        $element->setOption('model_link', array('Model\TestModel'));
        $this->assertEquals(2, count($element->getModelLink()));

        $element = new Element('test', array(), array('opt' => 'value'));
        $this->assertEquals('value', $element->getOption('opt'));
        $this->assertEquals(array('value', 'value2'), $element->addOption('opt', 'value2')->getOption('opt'));

        // Exception
        $element->setOptions(true);
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetAttributes()
    {
        $element = new Element('test', new \ArrayObject(array('attr' => 'attr_value')), new \ArrayObject(array('opt1' => 'value')));
        $this->assertInternalType('string', $element->getAttribute('attr'));
        $this->assertEquals('attr_value', $element->getAttribute('attr'));
        $this->assertEquals(array('name' => 'test', 'attr' => 'attr_value'), $element->getAttributes());
        $this->assertEquals(array('attr' => 'attr_value'), $element->removeAttribute('name')->getAttributes());
        $this->assertEquals(array(), $element->clearAttributes()->getAttributes());

        $this->assertEquals(array('decorator' => 'test'), $element->setDecorator('test')->getAttributes());
        $this->assertEquals(array('decorator' => 'test'), $element->removeAttributes(array('test'))->getAttributes());
        $this->assertEquals('test', $element->getDecorator());
        $this->assertEquals('', $element->removeAttribute('decorator')->getDecorator());

        $element->setAttributes(null);
    }


    /**
     * @expectedException \App\Form\Exception\InvalidArgumentException
     */
    public function testViewPath()
    {
        $element = new Element('test');
        $this->assertEquals(array(), $element->getViewPath());


        $element = new Element('test', array(), array('view_path' => array(__DIR__, __DIR__)));
        $this->assertEquals(array(__DIR__), $element->getOption('view_path'));

        $element = new Element('test', array(), array('view_path' => __DIR__ . '/../'));
        $this->assertEquals(array(realpath(__DIR__ . '/../')), $element->getOption('view_path'));

        $form = new Form('test', array(), array('view_path' => array(__DIR__)));
        $this->assertEquals(array(__DIR__), $form->getViewPath());
        $form->add($element);

        $this->assertEquals(array(realpath(__DIR__ . '/../'), __DIR__), $element->getViewPath());
        $this->assertEquals(array(__DIR__), $form->getViewPath());

        $element = new Element('test', array(), array('view_path' => './unknown'));
        $this->assertEquals('', $element->getOption('view_path'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetName()
    {
        $element = new Element('test[]');
        $this->assertEquals('test', $element->getInputName());

        $this->assertEquals('test', $element->setMultiple(true)->getInputName());

        $element = new Element('test', array(), new \ArrayObject(array('opt1' => 'value')));
        $this->assertEquals('test', $element->getName());

        $this->assertEquals('test', $element->getNameFromMixed(array('name' => 'test')));
        $this->assertEquals('test', $element->getNameFromMixed('test'));
        $this->assertEquals('test', $element->getNameFromMixed($element));

        $element = new Element();
        $this->assertEquals(null, $element->getName());
        $element->setName('name');
        $this->assertEquals('name', $element->getName());

        $element = new Element();
        $this->assertEquals(null, $element->getInputName());
        $this->assertEquals(null, $element->setMultiple(true)->getInputName());
        $element->setName('test_name');
        $this->assertEquals('test_name', $element->setMultiple(true)->getInputName());
        $this->assertEquals('test_name', $element->setMultiple(false)->getInputName());

        $fieldset = new Fieldset('fieldset');
        $fieldset->add($element);

        $form = new Fieldset('form[]');
        $form->add($fieldset);

        $this->assertEquals('form[fieldset][test_name]',   $element->setMultiple(false)->getInputName());
        $this->assertEquals('form[fieldset][test_name]', $element->setMultiple(true)->getInputName());

        $form->setMultiple(true);
        $this->assertEquals('form[fieldset][test_name]',   $element->setMultiple(false)->getInputName());
        $this->assertEquals('form[fieldset][test_name]', $element->setMultiple(true)->getInputName());
        $form->setMultiple(false);

        $fieldset->setMultiple(true);
        $this->assertEquals('form[fieldset][test_name]',   $element->setMultiple(false)->getInputName());
        $this->assertEquals('form[fieldset][test_name]', $element->setMultiple(true)->getInputName());

        $form->setMultiple(true);
        $this->assertEquals('form[fieldset][test_name]',   $element->setMultiple(false)->getInputName());
        $this->assertEquals('form[fieldset][test_name]', $element->setMultiple(true)->getInputName());

        // exception
        $this->assertEquals('test', $element->getNameFromMixed(new \stdClass()));
    }


    public function testAddClass()
    {
        $element = new Element('test');
        $element->setClass('yo');

        $this->assertEquals('yo', $element->getClass());

        $element->addClass('yo');
        $this->assertEquals('yo', $element->getClass());

        $element->addClass('yo2');
        $this->assertEquals('yo yo2', $element->getClass());

        $element->removeClass('yo');
        $this->assertEquals('yo2', $element->getClass());

    }
}

