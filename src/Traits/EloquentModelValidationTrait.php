<?php

namespace Mamadali\LaravelValidationModels\Traits;

/**
 * Validation Trait that provides methods to create and update model easily
 *
 */
trait EloquentModelValidationTrait
{
    use ModelValidationTrait;

    /**
     * @param array{'validate': int} $options
     * @return bool
     */
    public function save(array $options = []): bool
    {
        if(!$this->beforeSave($options)) {
            return false;
        }

        if(($options['validate'] ?? true) && !$this->validate()){
            return false;
        }

        $save = parent::save($options);

        $this->afterSave($options);

        return $save;
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     *
     * Override this method in your model:
     *
     * ```php
     * public function beforeSave($options) : bool
     * {
     *     // ...custom code here...
     *     return true;
     * }
     * ```
     *
     * @return bool whether the insertion or updating should continue.
     * If `false`, the insertion or updating will be cancelled.
     */
    public function beforeSave(array $options = []): bool
    {
        return true;
    }

    /**
     * This method is called at the end of inserting or updating a record.
     *
     * Override this method in your model:
     *
     * ```php
     * public function afterSave($options) : void
     * {
     *     // ...custom code here...
     * }
     * ```
     *
     */
    public function afterSave(array $options = []): void
    {
    }

}
