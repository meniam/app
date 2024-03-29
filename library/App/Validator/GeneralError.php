<?php

namespace App\Validator;

class GeneralError extends \Zend\Validator\AbstractValidator
{
	protected $_myKey = 'general';

	protected $messageTemplates = array('general' => 'Unknown error');

	public function setMessage($messageString, $messageKey = null)
	{
		$this->messageTemplates = array();
        $this->_myKey = $messageKey ? $messageKey : 'general';

        $this->abstractOptions['messageTemplates'][$this->_myKey] = $messageString;

        parent::setMessage($messageString, $this->_myKey);

		$this->messageTemplates[$this->_myKey] = $messageString;

		return $this;
	}

	/**
	 * Defined by Zend_Validate_Interface
	 *
	 * Returns true if and only if $value only contains digit characters
	 *
	 * @param  string $value
	 * @return boolean
	 */
	public function isValid($value)
	{
        $this->error($this->_myKey);

		return false;
	}

}
