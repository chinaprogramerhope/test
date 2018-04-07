<?php
namespace yii\base;

class ActionEvent extends Event {
	public $action;
	public $result;
	public $isValid = true;

	public function __construct($action, $config = []) {
		$this->action = $action;
		parent::__construct($config);
	}
}
