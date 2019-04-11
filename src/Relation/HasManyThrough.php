<?php

declare(strict_types=1);

namespace Irm\Relation;

class HasManyThrough extends HasMany
{
    const CONFIG_THROUGH = 'through';

    /**
     * @var array
     */
    protected $throughIndex = [];

    public function load(RelationshipsAwareInterface $model): \Traversable
    {
        if (!$this->loaded) {
            $through = $this->getThroughRelation($model);
            $through->load($model);
            $childrenGroup = $through->getGroupedChildren();
            $this->buildThroughIndex($childrenGroup);
            $filter = $this->buildOnFilterFromGroup($childrenGroup);
            $children = $this->loadChildrenWithFilter($model, $filter);
            $this->groupChildren($children);
        }

        $key = $this->{$this->relationKeyMethod}($this->childOnKeys, $model);

        return $this->getPropertyValueForModel($key);
    }

    protected function getPropertyValueForModel(string $key): \Traversable
    {
        if (empty($this->throughIndex[$key])) {
            return $this->getEmptyResult();
        } else {
            $class = $this->getResultSetClass();
            $children = [];
            foreach ($this->throughIndex[$key] as $id) {
                $children = array_merge($children, $this->groupedChildren[$id]);
            }
            return new $class($children);
        }
    }

    private function buildThroughIndex(array $children): void
    {
        foreach ($children as $parent => $group) {
            foreach ($group as $child) {
                $key = $this->{$this->relationKeyMethod}($this->parentOnKeys, $child);
                $this->throughIndex[$parent][] = $key;
            }
        };
    }

    private function buildOnFilterFromGroup(array $children): array
    {
        $filter = [];
        foreach ($this->getOn() as $childModelProperty => $parentModelProperty) {
            $filter[$childModelProperty] = $this->getFilterValueFromGroup(
                $children,
                $parentModelProperty
            );
        }

        if (!$filter) {
            trigger_error('No relationship filter specified', E_USER_NOTICE);
        }

        return $filter;
    }

    private function getFilterValueFromGroup(array $children, string $property): array
    {
        $values = [];
        foreach ($children as $group) {
            foreach ($group as $child) {
                $values[$child->{'get' . ucfirst($property)}()] = null;
            }
        }

        return array_keys($values);
    }

    private function getThroughRelation(RelationshipsAwareInterface $model): HasMany
    {
        $relation = $model->getRelationHandler(
            $this->config[self::CONFIG_THROUGH]
        );

        if (!$relation instanceof HasMany) {
            throw new \RuntimeException('HasManyThrough expects HasMany as through relation');
        }

        return $relation;
    }
}
