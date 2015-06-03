<?php

include_once 'Bean_ConstImplementations.php';
include_once 'Bean_ConstTypes.php';
include_once 'Bean_Definition.php';
include_once 'Bean_Exception.php';

class Bean_Factory {
	
	/**
	 * Array of beans definition
	 *
	 * @var array
	 */
	private $beansDefinition;
	
	/**
	 * Array of variables defined into beans
	 *
	 * @var array
	 */
	private static $beansVariables = array();
	
	/**
	 * Array of instanciated singletons
	 *
	 * @var array of mixed
	 */
	private static $singletons = array();
	
	/**
	 * Application context filename
	 *
	 * @var string
	 */
	private $applicationContext;
	
	/**
	 * Variables context filename
	 *
	 * @var string
	 */
	private $variablesContext;
	
	/**
	 * Singleton
	 *
	 * @var Bean_Factory
	 */
	private static $instance;
	
	/**
	 * Set the execution context
	 * 
	 * @param string $applicationContext
	 * @param boolean $forceReload
	 */
	public function setApplicationContext($applicationContext, $forceReload=false) {
		// Do not reload context if already loaded
		if (($forceReload == false) &&(!strcmp($this->applicationContext, $applicationContext)))
			return;

		// Load context
		$this->applicationContext = $applicationContext;
		$filenames = array();
		$this->beansDefinition = array();
		
		$this->getIncludesFilenames($this->applicationContext, $filenames);
		$this->loadXmlIntoArray($filenames);
	}
	
	/**
	 * Constructor is hidden. Instead use Bean_Factory::getInstance()
	 *
	 */
	private function __construct(){}
	
	/**
	 * Get bean factory instance
	 * 
	 * @return Bean_Factory
	 */
	public static function getInstance() {
		if (!isset(self::$instance))
			self::$instance = new Bean_Factory();
		return self::$instance;
	}
	
	/**
	 * Force bean factory instance
	 *
	 * @param Bean_Factory $instance
	 */
	public static function setInstance($anInstance) {
		self::$instance = $anInstance;
	}
	
	/**
	 * Get Bean_Definition by its name
	 *
	 * @param string $beanName
	 * @return Bean_Definition
	 */
	private function getBeanDefinition($beanName) {
		if (array_key_exists($beanName, $this->beansDefinition))
			return $this->beansDefinition[$beanName];
		return null;
	}
	
	/**
	 * Load an xml file into an array
	 * Exemple :
	 * 
	 * Array(
	 * 	'BeanName' => array(
	 * 				'impl' => 'implementationName',
	 * 				'type' => 'typeName',
	 *				'value' => 'value', 
	 * 				'properties' => array(
	 * 								'name' => 'value',
	 * 								'name2' => 'value2' 
	 * 							)
	 * 				)
	 *)
	 */
	private function loadXmlIntoArray($filenames) {
		foreach($filenames as $filename) {
			$xml = simplexml_load_file($filename);
			foreach($xml->bean as $bean) {
				
				// Set beanDefinition object						
				$beanDefinition = new Bean_Definition();
				$beanAttributes = $bean->attributes();
				$beanDefinition->setName((string)$beanAttributes['name']);
				$beanDefinition->setImpl((string)$beanAttributes['impl']);
				$beanDefinition->setType((string)$beanAttributes['type']);
				$beanDefinition->setValue($this->replaceVariableIntoValue((string)$beanAttributes['value']));
				
				// Check for implementation tag
				if (!$beanDefinition->hasImplementation())
					throw new Bean_Exception("'impl' tag could not be found for bean '{$beanDefinition->getName()}'");
					
				// Check for valid types
				if ($beanDefinition->hasType() && !$beanDefinition->isSingleton())
					throw new Bean_Exception("Type '{$beanDefinition->getType()}' specified for bean '{$beanDefinition->getName()}' is not handled");
									
				// Check for duplicated singleton
				$existingBean = $this->getBeanDefinition($beanDefinition->getName());
				if ($existingBean != null)
					throw new Bean_Exception("Bean '".$beanDefinition->getName()."' definition is already defined");
					
				// Get properties(and corresponding values)
				$properties = array();
				foreach($bean->property as $property) {
					$propertyAttributes = $property->attributes();
					$attributeName =(string) $propertyAttributes['name'];
					$attributeName[0] = strtoupper($attributeName[0]);
					$propertyName = "set" . $attributeName;
					
					$propertyValue =(string) $propertyAttributes['value'];
					
					/*Setting a bean property with an array*/
					if($propertyValue === ''){						
						$chidldren = (array) $property->children();
						if(count($chidldren) >1 ){
							$beanArray = $property->bean->attributes();						
													
							if (!strcmp(strtolower($beanArray['impl']), Bean_ConstImplementations::_ARRAY))
								$propertyValue = $this->buildArray($property->bean);
							
							if(count($propertyValue) === 0)
								throw new Bean_Exception('Error, invalid array definition');
						}
					}
						
					$properties[$propertyName] = $this->replaceVariableIntoValue($propertyValue);
				}
				$beanDefinition->setProperties($properties);
				$beanDefinition->setValues($this->buildArray($bean));

				// Put bean within beansDefinition array
				$this->beansDefinition[$beanDefinition->getName()] = $beanDefinition;
			}
		}
	}
	
	/**
	 * Get all included files
	 *
	 * @param string $filename
	 * @param array $filenames
	 * @return array
	 */
	public function getIncludesFilenames($filename, &$filenames) {
		$filename = str_replace('\\', '/', $filename);
		if (!file_exists($filename) ||(!is_file($filename)))
			throw new Bean_Exception("File '$filename' could not be found");
		if (in_array($filename, $filenames))
			throw new Bean_Exception("File '$filename' was already loaded");
		
		$this->loadBeanVariables($filename);
		$matches = array();
		
		$xml = simplexml_load_file($filename);
		if ($xml == false)
			throw new Bean_Exception("Invalid xml file '$filename'");
		array_push($filenames, $filename);
		preg_match('/(^.*\/).*\.xml$/', $filename, $matches);
		
		foreach($xml->include as $includeFile) {
			$includeFileAttribut = $includeFile->attributes();
			$includeValue = $this->replaceVariableIntoValue((string)$includeFileAttribut['filename']);

			if (substr($includeValue, 0, 1) == '/'){
				// Do nothing: $includeValue = $includeValue
			} else {
				$includeValue = $matches[1] . $includeValue;
			}

			$filename = realpath($includeValue);
			if ($filename == false)
				throw new Bean_Exception("Include file '$includeValue' could not be found");
			$this->getIncludesFilenames($filename, $filenames);
		}
	}
	
	/**
	 * Load tags <variable></variable> from bean file into class array
	 * Load tags <environment></environment> from bean file into class array
	 *
	 * @param string $filename
	 */
	private function loadBeanVariables($filename){
		if (!file_exists($filename) ||(!is_file($filename)))
			throw new Bean_Exception("File '$filename' could not be found");
		
		$xml = simplexml_load_file($filename);
		if ($xml == false)
			throw new Bean_Exception("Invalid xml file '$filename'");

		// Load tags <variable></variable>
		foreach($xml->variable as $newVariableDefinition) {
			$variableAttributes = $newVariableDefinition->attributes();
			$variableName =(string)$variableAttributes['name'];
			$variableValue =(string)$variableAttributes['value'];
			$variableImplementation = (string)$variableAttributes['impl'];
			
			if(!array_key_exists($variableName, self::$beansVariables)){
				
				//Build array
				if(isset($variableImplementation) && strcmp(strtolower($variableImplementation), Bean_ConstImplementations::_ARRAY) == 0){
					$variableValue = $this->buildArray($newVariableDefinition);
				}
					
				
				self::$beansVariables['$'.$variableName] = $variableValue;
			}
				
		}
		
		// Load tags <environment></environment>
		foreach($xml->environment as $environment) {
			$environmentName =(string)$environment;
			if(getenv($environmentName) != ''){
				if(!array_key_exists($environmentName, self::$beansVariables)) {
					self::$beansVariables['$'.$environmentName] = getenv($environmentName);
					
					// We need to be able to handle replacement of environment variables in variables
					// Example : sshPublic/Private keys locations
					foreach (self::$beansVariables as $varName => $varValue) {
					    if (!is_array($varValue)) {
					        if(strrpos($varValue, $environmentName) !== false){
            					if(is_string($varValue)) {
            					    $found = preg_replace('/\\'.'$'.$environmentName.'/', getenv($environmentName), $varValue);
            						self::$beansVariables[$varName]    = $found;
            					} 
            				}
					    }
					}
				}
			}else
				throw new Bean_Exception("Error, undefined environment variable $environmentName.");
		}
	}

	/**
	 * Replace variable name into string by the value
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function replaceVariableIntoValue($value){
		$found = null;
		
		foreach(self::$beansVariables as $varName => $varValue){
			if(is_array($value)){
				/*Array*/
				foreach ($value as $arrayKey=>$arrayValue){
					if(is_array($arrayValue))
						$value[$arrayKey] = $this->replaceVariableIntoValue($arrayValue);
					else{
						/*Variable is a string*/
						if(is_string($varValue))
							$value[$arrayKey] = preg_replace('/\\'.$varName.'/', $varValue, $arrayValue);
							
						/*Variable is an array*/
						elseif (is_array($varValue) && $varName == $arrayValue){
							//do nothing
							$value[$arrayKey] = $varValue;
						}
						
					} 
				}
			}else {
				/*String*/
				if(strrpos($value, $varName) !== false){
					if(!isset($found)){
						if(is_string($varValue))
							$found = preg_replace('/\\'.$varName.'/', $varValue, $value);
						elseif(is_array($varValue))
							$value = $varValue;
					} 
					else
						throw new Bean_Exception('Error, ambigous environment variable definition. Unable to find which variable to used');
				}
			}			
		}
		return (isset($found)) ? $found : $value;
	}
	
    /**
     * Build array
     *
     * @param SimpleXMLElement $bean
     * @param array $result
     * @return array
     */
	private function buildArray($bean, $result = null) {
		if (!isset($result))
			$result = array();
		
		foreach($bean->value as $value) {
			$valueAttributes = $value->attributes();
			$valueKey =((string)$valueAttributes['key'] !== '') ? (string)$valueAttributes['key'] : null;
			 
			if (isset($value->bean)) {
				$beanAttributes = $value->bean->attributes();
				$beanImpl = $beanAttributes['impl'];
				
				if (!strcmp(strtolower($beanImpl), Bean_ConstImplementations::_ARRAY)) {
					$subHash = $this->buildArray($value->bean);
					if (isset($valueKey))
						$result = self::populateArray($valueKey, $subHash, $result);
					else
						$result[] = $subHash;
				} elseif (!strcmp(strtolower($beanImpl), Bean_ConstImplementations::EVALUATE)) {					
					$beanValue =(string)$beanAttributes['value'];
					$stringToEval = str_replace('"', '\'', $beanValue);
					$evalResult = null;
					eval("\$evalResult = $stringToEval");
					$result[$valueKey] = $evalResult;
				} else
					throw new Bean_Exception("Implementation must be an array or a function to evaluate but not '$beanImpl'");
			} else {
				$valueTrimmed = trim((string)$value);
				if (isset($valueKey))
					$result = self::populateArray($valueKey, $this->replaceVariableIntoValue($valueTrimmed), $result);
				else
					$result[] = $valueTrimmed;
			}
		}
		return $result;
	}
	
	/**
	 * Populate array for key and value
	 * 	- Check in array if a key already exists => implements new values into array
	 * 	- Check if the key is a combined key with ',' seperator
	 *
	 * @param string $valueKey
	 * @param string $value
	 * @param array $array
	 * 
	 * 
	 * @return array
	 */
	private static function populateArray($valueKey, $value, $array){
		foreach (explode(',', $valueKey) as $key){
			$keyTrimmed = trim($key);
			if(array_key_exists($keyTrimmed, $array)){
				if(is_array($array[$keyTrimmed]))
					$array[$keyTrimmed][] = $value;
				else{
					$curentValue = $array[$keyTrimmed];
					$array[$keyTrimmed] = array($curentValue, $value);
				}
			}
			else
				$array[$keyTrimmed] = $value;
		}
		
		return $array;
	}
	
	/**
	 * Get bean
	 * @param string $key
	 * @return object $implementation
	 */
	public function getBean($key) {
		
		// Get bean definition
		$beanDefinition = $this->getBeanDefinition($key);
		if ($beanDefinition == null)
			throw new Bean_Exception("No such bean name '$key'");

		// Return singleton already instanciated
		if ($beanDefinition->isSingleton() && $this->singletonAlreadyInstanciated($beanDefinition)) {
			return $this->getSingletonInstance($beanDefinition);
		}

		// Instanciate 'String'
		if ($beanDefinition->isStringImplementation()) {
			$instance = $beanDefinition->getStringValue();
			if ($instance == null)
				throw new Bean_Exception("Bean '{$beanDefinition->getName()}' has no value (expecting a string value)");
		}
		// Instanciate 'Evaluate'
		elseif ($beanDefinition->isEvaluateImplementation()) {
			$stringToEval = str_replace('"', '\'', $beanDefinition->getValue());
			$result = null;
			eval("\$result = $stringToEval");
			$instance = $result;					
		}			
		// Instanciate 'Array'
		elseif ($beanDefinition->isArrayImplementation()) {
			$instance = $beanDefinition->getValues();					
		}	
		// Instanciate classes
		else {
			
			// Instanciate object
			if (!$beanDefinition->isClassImplementation()){
				throw new Bean_Exception("Implementation '{$beanDefinition->getImpl()}' could not be found for bean '{$beanDefinition->getName()}'. Be sure your class loader has successfully loaded this implementation");
			}
			$classname = $beanDefinition->getImpl();
			$instance = new $classname();
								
			// Set properties of object 
			$properties = $beanDefinition->getProperties();
			if (count($properties) > 0) 
				foreach($properties as $propertyName => $propertyValue) {
					if ($propertyValue == null){
						throw new Bean_Exception("Property '{$propertyName}' has no value specified for bean '{$beanDefinition->getName()}'");
					}						
					if (!method_exists($instance, $propertyName)){
						throw new Bean_Exception("Could not found setter method '{$propertyName}' in implementation of bean '{$beanDefinition->getName()}'");
					}
					$instance->$propertyName($propertyValue);
			}
		}
			
		// Put singleton into singletons map
		if ($beanDefinition->isSingleton())
			$this->addToSingletons($beanDefinition, $instance);
			
		// After initialized
		if ((!($beanDefinition->isStringImplementation())) &&
			(!($beanDefinition->isEvaluateImplementation())) &&
			(!($beanDefinition->isArrayImplementation())) && method_exists($instance, 'afterBeanInitialized'))
			$instance->afterBeanInitialized();			

		// Return instance
		return $instance;
	}
	
	/** 
	 * @param Bean_Definition $beanDefinition
	 * @return boolean
	 */	
	private function singletonAlreadyInstanciated($beanDefinition) {
		$filename = $this->applicationContext;
		if (array_key_exists($filename, self::$singletons))
			return array_key_exists($beanDefinition->getName(), self::$singletons[$filename]);
		return false;		
	}
	
	/**
	 * @param Bean_Definition $beanDefinition
	 * @return object
	 */
	private function getSingletonInstance($beanDefinition) {
		return self::$singletons[$this->applicationContext][$beanDefinition->getName()];
	}	
	
	/**
	 * @param Bean_Definition $beanDefinition
	 * @param mixed $implementation
	 */
	private function addToSingletons($beanDefinition, $implementation) {
		if (!array_key_exists($this->applicationContext, self::$singletons))
			self::$singletons[$this->applicationContext] = array();
		self::$singletons[$this->applicationContext][$beanDefinition->getName()] = $implementation;
	}
	
	/**
	 * Return variable loaded
	 *
	 * @param string $varName
	 * @return string
	 */
	public function getVar($varName){
		return self::$beansVariables['$'.$varName]; 
	}	
}
