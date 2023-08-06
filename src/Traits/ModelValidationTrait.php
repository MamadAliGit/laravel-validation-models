<?php

namespace Mamadali\LaravelValidationModels\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Validation Trait that provides methods to create and update model easily
 *
 */
trait ModelValidationTrait
{
    /**
     * message bag
     *
     * @var MessageBag|null
     */
    public ?MessageBag $errors = null;

    /**
     * @var ?string current scenario
     */
    public ?string $_scenario = null;

    /**
     * create new model by request data
     *
     * @param Request|array $request
     * @param string|null $scenario
     * @return self
     */
    public static function newModel(Request|array $request, ?string $scenario = null): static
    {
        $model = new static();

        $model->setScenario($scenario);

        $model->loadModel($request);

        return $model;
    }

    /**
     * @param Request|array $request
     * @return void
     */
    public function loadModel(Request|array $request): void
    {
        $scenarioAttributes = [];
        if ($this->getScenario()) {
            $scenarios = $this->scenarios();
            $scenarioAttributes = $scenarios[$this->getScenario()] ?? [];
        }

        foreach (($request instanceof Request ? $request->all() : $request) as $key => $value) {
            if ($this->getScenario() === null || in_array($key, $scenarioAttributes)) {
                $this->setModelAttribute($key, $value);
            }
        }
    }

    /**
     * Populates a set of models with the data from request.
     *
     * @param Request|array{0: array} $request
     * @param string|null $scenario
     * @return array
     */
    public static function newMultipleModel(Request|array $request, ?string $scenario = null): array
    {
        $models = [];

        foreach (($request instanceof Request ? $request->all() : $request) as $data) {
            $models[] = self::newModel($data, $scenario);
        }

        return $models;
    }

    /**
     * Returns the scenario that this model is used in.
     *
     * @return string|null the scenario that this model is in.
     */
    public function getScenario(): ?string
    {
        return $this->_scenario;
    }

    /**
     * Sets the scenario for the model.
     * @param string|null $scenario the scenario that this model is in.
     */
    public function setScenario(?string $scenario): void
    {
        $this->_scenario = $scenario;
    }


    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setModelAttribute(string $key, mixed $value): void
    {
        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($key, $value);
        } elseif (property_exists($this, $key)) {
            $this->$key = $value;
        }
    }

    /**
     * Returns a list of scenarios and the corresponding active attributes.
     *
     * ```php
     * [
     *     'scenario1' => ['attribute11', 'attribute12', ...],
     *     'scenario2' => ['attribute21', 'attribute22', ...],
     *     ...
     * ]
     * ```
     *
     * By default, all attributes is active
     *
     * @return array a list of scenarios and the corresponding active attributes.
     */
    public function scenarios(): array
    {
        return [];
    }


    /**
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function toArray(): array
    {
        $fields = request()->has('fields') && request()->get('fields') ? explode(',', request()->get('fields')) : [];
        $expand = request()->has('expand') && request()->get('expand') ? explode(',', request()->get('expand')) : [];
        return $this->toArrayResponse($fields, $expand);
    }

    /**
     * Returns the validation rules for attributes.
     * @return array
     */
    public function validateRules(): array
    {
        return [];
    }

    /**
     * validate model with rules in validateRules method
     *
     * @param array $options
     * @return boolean
     */
    public function validate(array $options = []): bool
    {
        if (!$this->beforeValidate()) {
            return false;
        }

        $rules = $this->validateRules();
        $ruleMessages = $this->getMessages();
        $attributeLabels = $this->attributeLabels();

        //defined in rules
        if (!empty($rules)) {
            $validation = Validator::make($this->getModelAttributes(), $rules, $ruleMessages, $attributeLabels);
            $this->getErrors()->merge($validation);
        }

        //inline validators
        foreach ($this->getModelAttributes() as $key => $value) {
            $validator = 'validate' . ucfirst($key);
            if (!method_exists($this, $validator)) {
                continue;
            }
            $this->$validator($key, $value, $options, $this->getScenario());
        }

        $this->afterValidate();

        return !$this->hasErrors();
    }

    /**
     * This method is invoked before validation starts.
     * You may override this method to do preliminary checks before validation.
     * @return bool whether the validation should be executed. Defaults to true.
     * If false is returned, the validation will stop and the model is considered invalid.
     */
    public function beforeValidate(): bool
    {
        return true;
    }

    /**
     * This method is invoked after validation ends.
     * You may override this method to do postprocessing after validation.
     */
    public function afterValidate(): void
    {
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    protected function getMessages(): array
    {
        return $this->messages();
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Returns the attribute labels.
     *
     * @return array attribute labels (name => label)
     */
    public function attributeLabels(): array
    {
        return [];
    }

    /**
     * @param string $attribute
     * @return string
     */
    public function attributeLabel(string $attribute): string
    {
        return $this->attributeLabels()[$attribute] ?? $attribute;
    }

    /**
     * @return array|self
     */
    public function getModelAttributes(): array|static
    {
        if (method_exists($this, 'getAttributes')) {
            return $this->getAttributes();
        } else {
            return (array)$this;
        }
    }

    /**
     * add error message to message bag
     *
     * @param string $key
     * @param string $message
     */
    public function addError(string $key, string $message): void
    {
        $this->getErrors()->add($key, $message);
    }

    /**
     * get all messages from message bag
     *
     * @return array|MessageBag
     */
    private function getErrors(): array|MessageBag
    {
        if ($this->errors === null) {
            return $this->errors = new MessageBag();
        }
        return $this->errors;
    }

    public function getErrorMessages(): array
    {
        return $this->getErrors()->messages();
    }

    /**
     * get single error message from message bag
     *
     * @param string|null $key
     * @return string
     */
    public function getError(string $key = null): string
    {
        $errors = $this->getErrors();
        return $errors->first($key);
    }

    /**
     * @return array
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function extraFields(): array
    {
        return [];
    }


    /**
     * if message bag has errors
     *
     * @return boolean
     */
    public function hasErrors(): bool
    {
        $errors = $this->getErrors();
        return !$errors->isEmpty();
    }

    /**
     * @return mixed
     */
    public function getFirstError(): string
    {
        return Arr::first(Arr::first($this->getErrorMessages()));
    }

    /**
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function responseAsJson(): JsonResponse
    {
        if ($this->hasErrors()) {
            return response()->json([
                'message' => $this->getFirstError(),
                'fields' => $this->getErrors(),
            ], 422);
        } else {
            $fields = request()->has('fields') && request()->get('fields') ? explode(',', request()->get('fields')) : [];
            $expand = request()->has('expand') && request()->get('expand') ? explode(',', request()->get('expand')) : [];
            return response()->json($this->toArrayResponse($fields, $expand));
        }
    }


    /**
     * Converts the model into an array.
     *
     * This method will first identify which fields to be included in the resulting array by calling [[resolveFields()]].
     * It will then turn the model into an array with these fields. If `$recursive` is true,
     * any embedded objects will also be converted into arrays.
     * When embedded objects are [[Arrayable]], their respective nested fields will be extracted and passed to [[toArray()]].
     *
     * If the model implements the [[Linkable]] interface, the resulting array will also have a `_link` element
     * which refers to a list of links as specified by the interface.
     *
     * @param array $fields the fields being requested.
     * If empty or if it contains '*', all fields as specified by [[fields()]] will be returned.
     * Fields can be nested, separated with dots (.). e.g.: item.field.sub-field
     * `$recursive` must be true for nested fields to be extracted. If `$recursive` is false, only the root fields will be extracted.
     * @param array $expand the additional fields being requested for exporting. Only fields declared in [[extraFields()]]
     * will be considered.
     * Expand can also be nested, separated with dots (.). e.g.: item.expand1.expand2
     * `$recursive` must be true for nested expands to be extracted. If `$recursive` is false, only the root expands will be extracted.
     * @param bool $recursive whether to recursively return array representation of embedded objects.
     * @return array the array representation of the object
     */
    protected function toArrayResponse(array $fields = [], array $expand = [], bool $recursive = true): array
    {
        $data = [];
        foreach ($this->resolveFields($fields, $expand) as $field => $definition) {

            if (strpos($field, ':') !== false) {
                $explode = explode(':', $field);
                $field = $explode[0];
                if (is_string($definition)) {
                    $definition = $field;
                }
                $castType = ucfirst($explode[1]);
            }

            $attribute = is_string($definition) ? $this->$definition : $definition($this, $field);

            if (isset($castType) && $castType) {
                $castFunc = "castTo$castType";
                $attribute = $this->$castFunc($attribute);
                $castType = null;
            }

            if ($recursive) {
                $nestedFields = $this->extractFieldsFor($fields, $field);
                $nestedExpand = $this->extractFieldsFor($expand, $field);
                if (is_object($attribute) && method_exists($attribute, 'toArrayResponse')) {
                    $attribute = $attribute->toArrayResponse($nestedFields, $nestedExpand);
                } elseif (is_array($attribute)) {
                    $attribute = array_map(
                        function ($item) use ($nestedFields, $nestedExpand) {
                            if (is_object($item) && method_exists($item, 'toArrayResponse')) {
                                return $item->toArrayResponse($nestedFields, $nestedExpand);
                            }
                            return $item;
                        },
                        $attribute
                    );
                }
            }
            $data[$field] = $attribute;
        }

        if (!$data) {
            return $this->getModelAttributes();
        }

        return $data;
    }

    /**
     * @param $data
     * @return int
     */
    private function castToInt($data)
    {
        return (int)$data;
    }

    /**
     * @param $data
     * @return string
     */
    private function castToString($data)
    {
        return (string)$data;
    }

    /**
     * @param $data
     * @return float
     */
    private function castToFloat($data)
    {
        return (float)$data;
    }

    /**
     * @param $data
     * @return bool
     */
    private function castToBool($data)
    {
        return (bool)$data;
    }

    /**
     * Determines which fields can be returned by [[toArray()]].
     * This method will first extract the root fields from the given fields.
     * Then it will check the requested root fields against those declared in [[fields()]] and [[extraFields()]]
     * to determine which fields can be returned.
     * @param array $fields the fields being requested for exporting
     * @param array $expand the additional fields being requested for exporting
     * @return array the list of fields to be exported. The array keys are the field names, and the array values
     * are the corresponding object property names or PHP callables returning the field values.
     */
    protected function resolveFields(array $fields, array $expand): array
    {
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

    /**
     * Extracts the root field names from nested fields.
     * Nested fields are separated with dots (.). e.g: "item.id"
     * The previous example would extract "item".
     *
     * @param array $fields The fields requested for extraction
     * @return array root fields extracted from the given nested fields
     * @since 2.0.14
     */
    protected function extractRootFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[] = current(explode('.', $field, 2));
        }

        if (in_array('*', $result, true)) {
            $result = [];
        }

        return array_unique($result);
    }

    /**
     * Extract nested fields from a fields collection for a given root field
     * Nested fields are separated with dots (.). e.g: "item.id"
     * The previous example would extract "id".
     *
     * @param array $fields The fields requested for extraction
     * @param string $rootField The root field for which we want to extract the nested fields
     * @return array nested fields extracted for the given field
     * @since 2.0.14
     */
    protected function extractFieldsFor(array $fields, string $rootField): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (str_starts_with($field, "{$rootField}.")) {
                $result[] = preg_replace('/^' . preg_quote($rootField, '/') . '\./i', '', $field);
            }
        }

        return array_unique($result);
    }

    /**
     * @return JsonResponse
     */
    public function responseNoContent(): JsonResponse
    {
        return response()->json([], 204);
    }
}
