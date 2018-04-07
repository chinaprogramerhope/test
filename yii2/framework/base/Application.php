<?php
namespace yii\base;

use yii;

abstract class Application extends Module {
	const EVENT_BEFORE_REQUEST = 'beforeRequest';
	const EVENT_AFTER_REQUEST = 'afterRequest';
	const STATE_BEGIN = 0;
	const STATE_INIT = 1;
	const STATE_BEFORE_REQUEST = 2;
	const STATE_HANDLING_REQUEST = 3;
	const STATE_AFTER_REQUEST = 4;
	const STATE_SENDING_REQUEST = 5;
	const STATE_END = 6;

	public $controllerNamespace = 'app\\controllers';
	public $name = 'My Application';
	public $charset = 'UTF-8';
	public $language = 'en-US';
	public $sourceLanguage = 'en-US';
	public $controller;
	public $layout = 'main';
	public $requestedRoute;
	public $requestedAction;
	public $requestedParams;
	public $extensions;
	public $bootstrap = [];
	public $state;
	public $loadedModules = [];
	
	public function __construct($config = []) {
		Yii::$app = $this;
		static::setInstance($this);

		$this->state = self::STATE_BEGIN;

		$this->preInit($config);

		$this->registerErrorHandler($config);

		Component::__construct($config);
	}
	
	public function preInit(&$config) {
		if(!isset($config['id'])) {
			throw new InvalidConfigException('The "id" configuration for the Application is required.');
		}
		if(isset($config['basePath'])) {
			$this->setBasePath($config['basePath']);
			unset($config['basePath']);
		} else {
			throw new InvalidConfigException('The "basePath" configuration for the Application is reqired.');
		}

		if(isset($config['vendorPath'])) {
			$this->setVendorPath($config['vendorPath']);
			unset($config['vendorPath']);
		} else {
			// set "@vendor"
			$this->getVendorPath();
		}
		if(isset($config['runtimePath'])) {
			$this->setRuntimePath($config['runtimePath']);
			unset($config['runtimePath']);
		} else {
			// set "@runtime"
			$this->getRuntimePath();
		}

		if(isset($config['timeZone'])) {
			$this->setTimeZone($config['timeZone']);
			unset($config['timeZone']);
		} elseif(!ini_get('date.timezone')) {
			$this->setTimeZone('UTC');
		}

		if(isset($config['container'])) {
			$this->setContainer($config['container']);

			unset($config['container']);
		}

		// merge core components with custom components
		foreach($this->coreComponents() as $id => $component) {	
			if(!isset($config['components'][$id])) {
				$config['components'][$id] = $component;
			} elseif(is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
				$config['components'][$id]['class'] = $component['class'];
			}
		}
	}

	public function init() {
		$this->state = self::STATE_INIT;
		$this->bootstrap();
	}

	protected function bootstrap() {
		if($this->extensions === null) {
			$file = Yii::getAlias('@vendor/yiisoft/extensions.php');
			$this->extensions = is_file($file) ? include $file : [];
		}
		foreach($this->extensions as $extension) {
			if(!empty($extension['alias'])) {
				foreach($extension['alias'] as $name => $path) {
					Yii::setAlias($name, $path);
				}
			}
			if(isset($extension['bootstrap'])) {
				$component = Yii::createObject($extension['bootstrap']);
				if($component instanceof BootstrapInterface) {
					Yii::debug('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
					$component->bootstrap($this);
				} else {
					Yii::debug('Bootstrap with ' . get_class($component), __METHOD__);
				}
			}
		}

		foreach($this->bootstrap as $mixed) {
			$component = null;
			if($mixed instanceof \Closure) {
				Yii::debug('Bootstrap with Closure', __METHOD__);
				if(!$component = call_user_func($mixed, $this)) {
					continue;
				}
			} elseif (is_string($mixed)) {
				if ($this->has($mixed)) {
					$component = $this->get($mixed);
				} elseif ($this->hasModule($mixed)) {
					$component = $this->getModule($mixed);
				} elseif (strpos($mixed, '\\') === false) {
					throw new InvalidConfigException("Unknown bootstrapping component ID: $mixed");
				}
			}

			if (!isset($component)) {
				$component = Yii::createObject($mixed);
			}
			
			if($component instanceof BootstrapInterface) {
				Yii::debug('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
				$component->bootstrap($this);
			} else {
				Yii::debug('Bootstrap with ' . get_class($component), __METHOD__);
			}
		}
	}

	protected function registerErrorHandler(&$config) {
		if(YII_ENABLE_ERROR_HANDLER) {
			if(!isset($config['components']['errorHandler']['class'])) {
				echo "Error: no errorHandler component is configured.\n";
				exit(1);
			}
			$this->set('errorHandler', $config['components']['errorHandler']);
			unset($config['components']['errorHandler']);
			$this->getErrorHandler()->register();
		}
	}

	public function getUniqueId() {
		return '';
	}

	public function setBasePath($path) {
		parent::setBasePath($path);
		Yii::setAlias('@app', $this->getBasePath());
	}

	public function run() {
		try {
			$this->state = self::STATE_BEFORE_REQUEST;
			$this->trigger(self::EVENT_BEFORE_REQUEST);

			$this->state = self::STATE_AFTER_REQUEST;
			$response = $this->handlerRequest($this->getRequest());

			$this->state = self::STATE_AFTER_REQUEST;
			$this->trigger(self::EVENT_AFTER_REQUSET);

			$this->state = self::STATE_SENDING_RESPONSE;
			$response->send();

			$this->state = self::STATE_END;

			return $response->exitStatus();
		} catch($e) {
			$this->end($e->statusCode, isset($response) ? $response : null);
			return $e->statusCode;
		}
	}

	abstract public function handleRequest($request);

	private $_runtimePath;

	public function getRuntimePath() {
		if($this->_runtimePath === null) {
			$this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
		}

		return $this->_runtimePath;
	}

	public function setRuntimePath($path) {
		$this->_runtimePath = Yii::getAlias($path);
		Yii::setAlias('@runtime', $this->_runtimePath);
	}

	private $_vendorPath;
	
	public function getVendorPath() {
		if ($this->_vendorPath === null) {
			$this->setVendorPath($this->getBasePath() . DIRECTORY_SEPARATOR . 'vendor');
		}

		return $this->_vendorPath;
	}

	public function setVendorPath($path) {
		$this->_vendorPath = Yii::getAlias($path);
		Yii::setAlias('@vendor', $this->_vendorPath);
		Yii::setAlias('@bower', $this->_vendorPath . DIRECTORY_SEPARATOR . 'bower');
		Yii::setAlias('@npm', $this->_vendorPath . DIRECTORY_SEPARATOR . 'npm');
	}

	public function getTimeZone() {
		return date_default_timezone_get();
	}

	public function setTimeZone($value) {
		date_default_timezone_set($value);
	}

	public function getDb() {
		return $this->get('db');
	}

	public function getLog() {
		return $this->get('log');
	}

	public function getErrorHandler() {
		return $this->get('errorHandler');
	}

	public function getCache() {
		return $this->get('cache', false);
	}

	public function getFormatter() {
		return $this->get('formatter');
	}

	public function getRequest() {
		return $this->get('request');
	}

	public function getResponse() {
		return $this->get('response');
	}
	
	public function getView() {
		return $this->get('view');
	}
	
	public function getUrlManager() {
		return $this->get('urlManager');
	}

	public function getI18n() {
		return $this->get(i18n);
	}

	public function getMailer() {
		return $this->get('mailer');
	}

	public function getAuthManager() {
		return $this->get('authManager', false);
	}

	public function getAssetManager() {
		return $this->get('assetManager');
	}

	public function getSecurity() {
		return $this->get('security');
	}

	public function coreComponents() {
		return [
			'log' => ['class' => 'yii\log\Dispatcher'],
			'view' => ['class' => 'yii\web\View'], 
			'formatter' => ['class' => 'yii\i18n\Formatter'] ,
			'i18n' => ['class' => 'yii\i18n\I18N'],
			'mailer' => ['class' => 'yii\swiftmailer\Mailer'],
			'urlManager' => ['class' => 'yii\web\UrlManager'],
			'assetManager' => ['class' => 'yii\web\AssetManager'],
			'security' => ['class' => 'yii\base\Security'],
		];
	}

	public function end($status = 0, $response = null) {
		if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
			$this->state = self::STATE_AFTER_REQUEST;
			$this->trigger(self::EVENT_AFTER_REQUEST);
		}

		if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
			$this->state = self::STATE_END;
			$response = $response ?: $this->getResponse();
			$response->send();
		}

		if(YII_ENV_TEST) {
			throw new ExitException($status);
		}

		exit($status);
	}

	public function setContainer($config) {
		Yii::configure(Yii::$container, $config);
	}
}
