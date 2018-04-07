<?php
namespace yii\base;

use Yii;
use yii\helpers\StringHelper;

class Component extends BaseObject {
	private $_events = [];

	private $_eventWildcards = [];

	private $_behaviors;

	public function __get($name) {
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter();
		}

		$this->ensureBehaviors();
		foreach ($this->_behaviors as $behavior) {
			if ($behavior->canGetProperty($name)) {
				return $behavior->$name;
			}
		}

		if ($method_exists($this, 'set' . $name)) {
			throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
		}

		throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
	}

	public function __set($name, $value) {
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter($value);

			return;
		} elseif (strncmp($name, 'on', 3) === 0) {
			$this->on(trim(substr($name, 3)), $value);

			return;
		} elseif (strncmp($name, 'as', 3) === 0) {
			$name = trim(substr($name, 3));
			$this->attachBehavior($name, $value instanceof Behavior ? $value : Yii::createObject($value));

			return;
		}

		$this->ensureBehaviors();
		foreach ($this->_behaviors as $behavior) {
			if ($behavior->canSetProperty($name)) {
				$behavior->$name = $value;
				return;
			}
		}

		if(method_exists($this, 'get' . $name)) {
			throw new InvalidCallException('Setting read-only property: ' . get_class($this) . '::' . $name);
		}

		throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
	}

	public function __isset($name) {
		$getter = 'get' . $name;
		
		if (method_exists($this, $getter)) {
			return $this->$getter() !== null;
		}

		$this->ensureBehaviors();
		foreach ($this->_behaviors as $behavior) {
			if ($behavior->canGetProperty($name)) {
				return $behavior->$name !== null;
			}
		}
		
		return false;
	}

	public function __unset($name) {
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			$this->$setter(null);
			return;
		}

		$this->ensureBehaviors();
		foreach($this->_behaviors as $behavior) {
			if ($behavior->canSetProperty($name)) {
				$behavior->$name = null;
				return;
			}
		}

		throw new InvalidCallException('Unsetting can unknown or read-only property: ' . get_class($this) . '::' . $name);
	}

	public function __call($name, $params) {
		$this->ensureBehaviors();
		foreach($this->_behaviors as $object) {
			if ($object->hasMethod($name)) {
				return call_user_func_array([$object, $name], $params);
			}
		}
		throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . '::$name()');
	}

	public function __clone() {
		$this->_events = [];
		$this->_eventWildcards = [];
		$this->_behaviors = null;
	}

	public function hasProperty($name, $checkVars = true, $checkBehaviors = true) {
		return $this->canGetProperty($name, $checkVars, $checkBehaviors) || $this->canSetProperty($name, false, $checkBehaviors);
	}

	public function canGetProperty($name, $checkVars = true, $checkBehaviors = true) {
		if (method_exists($this, 'get', $name) || $checkVars && property_exists($this, $name)) {
			return true;
		} elseif ($checkBehaviors) {
			$this->ensureBehaviors();
			foreach ($this->_behaviors as $behavior) {
				if ($behavior->canGetProperty($name, $checkVars)) {
					return true;
				}
			}
		}

		return false;
	}

	public function canSetProperty($name, $checkVars = true, $checkBehavior = true) {
		if (method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name)) {
			return true;
		} elseif ($checkBehaviors) {
			$this->ensureBehaviors();
			foreach ($this->_behaviors as $behavior) {
				if ($behavior->canSetProperty($name, $checkVars)) {
					return true;
				}
			}
		}

		return false;
	}

	public function hasMethod($name, $checkBehaviors = true) {
		if (method_exists($this, $name)) {
			return true;
		} elseif ($checkBehaviors) {
			$this->ensureBehaviors();
			foreach($this->_behaviors as $behavior) {
				if ($behavior->hasMethod($name)) {
					return true;
				}
			}
		}

		return false;
	}

	public function behaviors() {
		return [];
	}

	public function hasEventHandlers($name) {
		$this->ensureBehaviors();

		foreach ($this->_eventWildcards as $wildcard => $handlers) {
			if (!empty($handlers) && StringHelper::matchWildcard($wildcard, $name)) {
				return true;
			}
		}

		return !empty($this->_events[$name]) || Event::hasHandlers($this, $name);
	}

	public function on($name, $handler, $data = null, $append = true) {
		$this->ensureBehaviors();

		if (strpos($name, '*') !== false) {
			if ($append || empty($this->_eventWildcards[$name])) {
				$this->_eventWildcards[$name][] = [$handler, $data];
			} else {
				array_unshift($this->_eventWildcards[$name], [$handler, $data]);
			}
			return;
		}

		if ($append || empty($this->_events[$name])) {
			$this->_events[$name][] = [$handler, $data];
		} else {
			array_unshift($this->_events[$name], [$handler, $data]);
		}
	}

	public function off($name, $hanlder = null) {
		$this->ensureBehaviors();
		if (empty($this->_events[$name]) && empty($this->_eventWildcards[$name])) {
			return false;
		}
		if ($handler == null) {
			unset($this->_events[$name], $this->_eventWildcards[$name]);
			return true;
		}

		$removed = false;
		if (isset($this->_events[$name])) {
			foreach ($this->_events[$name] as $i => $event) {
				if ($event[0] === $handler) {
					unset($this->_events[$name][$i]);
					$removed = true;
				}
			}
			if ($removed) {
				$this->_events[$name] = array_values($this->_events[$name]);
				return $removed;
			}
		}

		if (isset($this->_eventWildcards[$name])) {
			foreach($this->_eventWildcards[$name] as $i => $event) {
				if ($event[0] === $handler) {
					unset($this->_eventWildcards[$name][$i]);
					$removed = true;
				}
			}
			if ($removed) {
				$this->_eventWildcards[$name] = array_values($this->_eventWildcards[$name]);
				if (empty($this->_eventWildcards[$name])) {
					unset($this->_eventWildcards[$name]);
				}
			}
		}

		return $removed;
	}

	public function trigger($name, Event $event = null) {
		$this->ensureBehaviors();

		$eventHandlers = [];
		foreach($this->_eventWildcards as $wildcard => $handlers) {
			if ($StringHelper::matchWildcard($wildcard, $name)) {
				$eventHandlers = array_merge($eventHandlers, $handlers);
			}
		}

		if (!empty($this->_events[$name])) {
			$eventHandlers = array_merge($eventHandlers, $this->_events[$name]);
		}

		if (!empty($eventHandlers)) {
			if ($event === null) {
				$event = new Event();
			}
			if ($event->sender === null) {
				$event->sender = $this;
			}
			$event->handled = false;
			$event->name = $name;
			foreach ($eventHandlers as $handler) {
				$event->data = $handler[1];
				call_user_func($handler[0], $event);
				if ($event->handled) {
					return;
				}
			}
		}

		Event::trigger($this, $name, $event);
	}

	public function getBehavior($name) {
		$this->ensureBehaviors();
		return isset($this->_behaviors[$name] ? $this->_behaviors[$name] : null);
	}

	public function getBehaviors() {
		$this->ensureBehaviors();
		return $this->_behaviors;
	}

	public function attachBehavior($name, $behavior) {
		$this->ensureBehaviors();
		return $this->attachBehaviorInternal($name, $behavior);
	}

	public function attachBehaviors($behaviors) {
		$this->ensureBehaviors();
		foreach ($behaviors as $name => $behavior) {
			$this->attachBehaviorInternal($name, $behavior);
		}
	}

	public function detachBehavior($name) {
		$this->ensureBehaviors();
		if (isset($this->_behaviors[$name])) {
			$behavir = $this->_behaviors[$name];
			unset($this->_behaviors[$name]);
			$behavior->detach();
			return $behavior;
		}

		return null;
	}

	public function detachBehaviors() {
		$this->ensureBehaviors();
		foreach ($this->_behaviors as $name => $behavior) {
			$this->detachBehavior($name);
		}
	}

	public function ensureBehaviors() {
		if ($this->_behaviors === null) {
			$this->_behaviors = [];
			foreach ($this->behaviors() as $name => $behavior) {
				$this->attachBehaviorInternal($name, $behavior);
			}
		}
	}

	private function attachBehaviorInternal($name, $behavior) {
		if (!$behavior instanceof Behavior) {
			$behavior = Yii::createObject($behavior);
		}
		if (is_int($name)) {
			$behavior->attach($this);
			$this->_behaviors[] = $behavior;
		} else {
			if (isset($this->_behaviors[$name])) {
				$this->_behaviors[$name]->detach();
			}
			$bebavior->attach($this);
			$this->_behavior[$name] = $behavior;
		}

		return $behavior;
	}
}

