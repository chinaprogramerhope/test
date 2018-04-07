<?php
namespace yii\base;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Link;
use yii\web\Linkable;

trait ArrayableTrait {
	public function fields() {
		$fields = array_keys(Yii::getObjectVars($this));
		return array_combine($fields, $fields);
	}

	public function extraFields() {
		return [];
	}

	public function toArray(array $fields = [], array $expand = [], $recursive = true) {
		$data = [];
		foreach ($this->resolveFields($fields, $expand) as $field => $definition) {
			$attribute = is_string($definition) ? $this->$definition : $definition($this, $field);

			if ($recursive) {
				$nestedFields = $this->extractFieldsFor($fields, $field);
				$nestedExpand = $this->extractFieldsFor($expand, $field);
				if ($attribute instanceof Arrayable) {
					$attribute = $attribute->toArray($nestedFields, $nestedExpand);
				} elseif (is_array($attribute)) {
					$attribute = array_map(
						function ($item) use ($nestedFields, $nestedExpand) {
							if ($item instanceof Arrayable) {
								return $item->toArray($nestedFields, $nestedExpand);
							}
							return $item;
						},
						$attribute
					);
				}
			}
			$data[$field] = $attribute;
		}

		if ($this instanceof Linkable) {
			$data['_links'] = Link::serialize($this->getLinks());
		}

		return $recursize ? ArrayHelper::toArray($data) : $data;
	}

	protected function extractRootFields(array $fields) {
		$result = [];

		foreach($fields as $field) {
			$result[] = current(explode('.', $field, 2));
		}

		if(in_array('*', $result, true)) {
			$result = [];
		}

		return array_unique($result);
	}

	protected function extractFieldsFor(array $fields, $rootField) {
		$result = [];

		foreach($fields as $field) {
			if (0 === strpos($field, "{$rootField}.")) {
				$result[] = preg_replace('/^' . preg_quote($rootField, '/') . '\./i', '', $field);
			}
		}

		return array_unique($result);
	}

	protected function resolveFields(array $fields, array $expand) {
		$fields = $this->extractRootFields($fields);
		$expand = $this->extractRootFields($expand);
		$result = [];

		foreach ($this->fields() as $field => $definition) {
			if (is_int($field)) {
				$field = $definition;
			}
			if (empty($fields) || in_array($field, $fields, true)) {
				$result[$field] = $definition;
			}
		}

		if (empty($expand)) {
			return $result;
		}

		foreach ($this->extraFields() as $field => $definition) {
			if (is_int($field)) {
				$field = $definition;
			}
			if (in_array($field, $expand, true)) {
				$result[$field] = $definition;
			}
		}

		return $result;
	}
}
