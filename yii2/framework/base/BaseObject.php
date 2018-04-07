<?php
namespace yii\base;

use Yii;

class BaseObject implements Configurable {
	public static function className() {
		return get_called_class();
	}

	public function __construct($config = []) {
		if (!empty($config)) {
			Yii::configure($this, $config);
		}
		$this->init();
	}

	public function init() {
	}

	public function __get($name) {
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter();
		} elseif (method_exists($this, 'set' . $name)) {
			throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
		}

		throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
	}

	public function __set($name, $value) {
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter($value);
		} elseif (method_exists($this, 'get' . $name)) {
			throw new InvalidCallException('Setting read-only property: ' . get_calss($this) . '::' . $name);
		} else {
			throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
		}
	}

	public function __isset($name) {
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter() !== null;
		}

		return false;
	}

	public function __unset($name) {
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter(null);
		} elseif (method_exists($this, 'get' . $name)) {
			throw new InvalidCallException('Unsetting read-only property: ' . get_class($this) . '::' . $name);
		}
	}

	public function __call($name, $params) {
		throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::$name()");
	}

	public function hasProperty($name, $checkVars = true) {
		return $this->canGetProperty($name, $checkVars) || $this->canSetProperty($name, false);
	}

	public function canGetProperty($name, $checkVars = true) {
		return method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name);
	}

	public function canSetProperty($name, $checkVars = true) {
		return method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name);
	}

	public function hasMethod($name) {
		return method_exists($this, $name);
	}
}

