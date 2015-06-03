<?php

class Bean_Definition {
	
	/**
	 * Name
	 *
	 * @var string
	 */
	private $name;
	
	/**
	 * Type
	 *
	 * @var string
	 */
	private $type;
	
	/**
	 * Implementation class name
	 *
	 * @var string
	 */
	private $impl;
	
	/**
	 * Value
	 *
	 * @var object
	 */
	private $value;
	
	/**
	 * Array of values
	 *
	 * @var array
	 */
	private $values;
	
	/**
	 * Array of properties
	 *
	 * @var array
	 */
	private $properties;
		
	/**
	 * @return string
	 */
	public function getImpl() {
		return $this->impl;
	}
	
	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * @return array
	 */
	public function getProperties() {
		return $this->properties;
	}
	
	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
	
	/**
	 * @return object
	 */
	public function getValue() {
		return $this->value;
	}
	
	/**
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}
	
	/**
	 * @param string $impl
	 */
	public function setImpl($impl) {
		$this->impl = $impl;
	}
	
	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}
	
	/**
	 * @param array $properties
	 */
	public function setProperties($properties) {
		$this->properties = $properties;
	}
	
	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}
	
	/**
	 * @param object $value
	 */
	public function setValue($value) {
		$this->value = $value;
	}
	
	/**
	 * @param array $values
	 */
	public function setValues($values) {
		$this->values = $values;
	}
	
	/**
	 * Is singleton
	 *
	 * @return boolean
	 */
	public function isSingleton() {
		if (strcmp($this->type, Bean_ConstTypes::SINGLETON) == 0){
			return true;
		} else {
		    return false;
		}
	}
	
	/**
	 * @return boolean
	 */
	public function isEvaluateImplementation() {
		if (strcmp(strtolower($this->impl), Bean_ConstImplementations::EVALUATE) == 0){
			return true;
		} else {
		    return false;
		}		
	}
	
	/**
	 * Has implementation
	 *
	 * @return boolean
	 */
	public function hasImplementation() {
		if (($this->impl == null) || ($this->impl == '')){
			return false;
		} else {
		    return true;
		}
	}
	
	/**
	 * @return boolean
	 */
	public function isStringImplementation() {
		if (strcmp(strtolower($this->impl), Bean_ConstImplementations::STRING) == 0){
			return true;
		} else {
		    return false;
		}			
	}
	
	/**
	 * @return boolean
	 */	
	private function hasStringValue() {
		if (($this->value == null) || ($this->value == '')){
			return false;
		} else {
		    return true;
		}				
	}
	
	/**
	 * Get string value
	 *
	 * @return string
	 */
	public function getStringValue() {
		if (! $this->hasStringValue()){
			return null;
		} else {
		    return $this->value;
		}
	}
	
	/**
	 * @return boolean
	 */
	public function isArrayImplementation() {
		if (strcmp(strtolower($this->impl), Bean_ConstImplementations::_ARRAY) == 0){
			return true;
		} else {
		    return false;
		}			
	}
	
	/**
	 * @return boolean
	 */
	public function isClassImplementation() {
		if (class_exists($this->impl)){
			return true;
		} else {
    		return false;
		}			
	}		
	
	/**
	 * @return boolean
	 */	
	public function hasType() {
		if (($this->type == null) || ($this->type == '')){
			return false;
		} else {
		    return true;
		}			
	}

}
