<?php
namespace yii\base;

use Yii;

class Controller extends Component implements ViewContextInterface {
	const EVENT_BEFORE_ACTION = 'beforeAction';
	const EVENT_AFTER_ACTION = 'afterAction';
	public $id;
	public $module;
	public $defaultAction = 'index';
	public $layout;
	public $action;
	public $_view;
	public $_viewPath;

	public function __construct($id, $module, $config = []) {
		$this->id = $id;
		$this->module = $module;
		parent::__construct($config);
	}

	public function actions() {
		return [];
	}

	public function runAction($id, $params = []) {
		$action = $this->createAction($id);
	}
}
