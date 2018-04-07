<?php
namespace yii\base;

use Yii;

class Action extends Component {
	public $id;
	public $controller;

	public function __construct($id, $controller, $config = []) {
		$this->id = $id;
		$this->controller = $controller;
		parent::__construct($config);
	}

	public function getUniqueId() {
		return $this->controller->getUniqueId() . '/' . $this->id;
	}

	public function runWithParams($params) {
		if(!method_exists($this, 'run')) {
			throw new InvalidConfigException(get_class($this) . ' must define a "run()" method.');
		}

		$args = $this->controller->bindActionParams($this, $params);
		Yii::debug('Running action: ' . get_class($this) . '::run()', __METHOD__);
		if(Yii::$app->requestedParams === null) {
			Yii::$app->requestParams = $args;
		}
		if($this->beforRun()) {
			$result = call_user_func_array([$this, 'run'], $args);
			$this->afterRun();
			
			return $result;
		}

		return null;
	}
	
	protected function beforeRun() {
		return true;
	}

	protected function afterRun() {
	}
}


