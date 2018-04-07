<?php
namespace yii\base;

trait ArrayAccessTrait {
	public function getIterator() {
		return new \ArrayIterator($this->data);
	}

	public function count() {
		return count($this->data);
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return isset($this->data[$offset] ? $this->data[$offset] : null);
	}

	public function offsetSet($offset, $item)  {
		$this->data[$offset] = $item;
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}
}
