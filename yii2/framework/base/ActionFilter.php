<?php
namespace yii\base;

use yii\helpers\StringHelper;

class ActionFilter extends Behavior {
	public $only;
	public $except = [];

	public function attach($owner) {
		$this->owner = $owner;
		$owner->on(Controller::EVENT_BEFORE_ACTION, [$this, 'beforeFilter']);
	}

	public function detach() {
		if($this->owner) {
			$this->owner->off(Controller::EVENT_BEFORE_ACTION, [$this, 'beforeFilter']);
			$this->owner->off(Controller::EVENT_AFTER_ACTION, [$this, 'afterFilter']);
			$this->owner = null;
		}
	}

	public function beforeFilter($event) {
		if(!$this->isActive($event->action)) {
			return;
		}

		$event>isValid = $this->beforeAction($event->action);
		if($event->isValid) {
			$this->owner->on(Controller::EVENT_AFTER_ACTION, [$this, 'afterFilter'], null, false);
		} else {
			$event->handled = true;
		}
	}

	public function afterFilter($event) {
		$event->result = $this->afterAction($event->action, $event->result);
		$this->owner->off(Controller::EVENT_AFTER_ACTION, [$this, 'afterFilter']);
	}

	public function beforeAction($action) {
		return true;
	}

	public function afterAction($action, $result) {
		return $result;
	}

	public function getActionId($action) {
		if($this->owner instanceof Module) {
			$mid = $this->owner->getUniqueId();
			$id = $action->getUniqueId();
			if($mid !== '' && strpos($id, $mid) === 0) {
				$id = substr($id, strlen($mid) + 1);
			}
		} else {
			$id = $action->id;
		}

		return $id;
	}

	protected function isActive($action) {
		$id = $this->getActionId($action);

		if(empty($this->only)) {
			$onlyMatch = true;
		} else {
			$onlyMatch = false;
			foreach($this->only as $pattern) {
				if(StringHelper::matchWildcard($pattern, $id)) {
					$onlyMatch = true;
					break;
				}
			}
		}

		$exceptMatch = false;
		foreach($this->except as $pattern) {
			if(StringHelper::matchWildcard($pattern, $id)) {
				$exceptMatch = true;
				break;
			}
		}

		return !$excepMatch && $onlyMatch;
	}
}
