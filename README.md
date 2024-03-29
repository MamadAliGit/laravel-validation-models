Laravel Validation Models
=====================
Helps you easily validate your models and eloquent models with specific scenarios and use them in api

[![Latest Stable Version](https://img.shields.io/packagist/v/mamadali/laravel-validation-models.svg)](https://packagist.org/packages/mamadali/laravel-validation-models)
[![Total Downloads](https://img.shields.io/packagist/dt/mamadali/laravel-validation-models.svg)](https://packagist.org/packages/mamadali/laravel-validation-models)

# Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require mamadali/laravel-validation-models "*"
```

or add

```)
"mamadali/laravel-validation-models": "*"
```

to the "require" section of your `composer.json` file.

---
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
    - [Multiple Models](#multiple-models)
    - [Inline Validation](#inline-validation)
    - [Scenarios](#scenarios)
    - [Api Response](#api-response)
    - [Get fields in api](#get-fields-in-api)
    - [Events](#events)
---

# Basic Usage

First use trait in your model or eloquent model
-
in your form use like this

```php
class Form
{
    use ModelValidationTrait;
```
in your eloquent models use like this

```php
class User extends Model
{
    use EloquentModelValidationTrait;

```
then write your validation rules in validateRules() function in your model

```php
    public function validateRules(): array
    {
        return [
            'title' => ['required', "string"],
            'number' => ['required', 'integer'],
        ];
    }
```
and you can load data received from end user to your model and validate data \
->validate() function return true or false

```php
Route::post('/test', function (Request $request) {
    // you can pass data from Request or array from user data to newModel function 
    $model = Form::newModel($request);
    $validate = $model->validate();

    // if $validate false $model->hasErrors() return false
    $hasErrors = $model->hasErrors();

    // you can get all error message as array with $model->getErrorMessages()
    $errors = $model->getErrorMessages();

    // you can get first error as string with $model->getFirstError()
    $firstErrorMessage = $model->getFirstError();
```
in eloquent models you can just call $model->save()
```php
Route::post('/create-user', function (Request $request) {
    // you can pass data from Request or array from user data to newModel function 
    $model = User::newModel($request);

    $model->save();
```
in save function call validate function by default \
if you need not call validate function
```php
    $model->save(['validate' => false]);
```
# Advanced Usage

## Multiple Models
you can new multiple model from your request data if your data list of models
```php
    $models = Form::newMultipleModel($request);
    // $models array from your form model with data received in request
```

## Inline Validation
you can write your custom validation in your model \
write function name as validate{Attribute}() in your model
```php
    // your model
    
    public string $title;

    // validate rules
    .......

     /**
     * @param string $attribute in this example 'title'
     * @param mixed $value value of your attribute
     * @param array $options options passed in validate function
     * @param string $scenario model scenario
     */
    public function validateTitle($attribute, $value, $options, $scenario)
    {
        // your custom validation here
        // and you can add error to the model like this
        $this->addError($attribute, 'error message');
    }
```

## Scenarios
you can set multi scenarios for your model like this
```php
    public function scenarios() : array
        [
          'scenario1' => ['attribute11', 'attribute12', ...],
          'scenario2' => ['attribute21', 'attribute22', ...],
          ......
        ]
    }
```
and you can pass scenario when create your model
```php
    $model = Form::newModel($request, 'scenario1');
    $models = Form::newMultipleModel($request, 'scenario2');
```

also you can set scenario on your model manually
```php
    $model = new Form();
    $model->setScenario('scenario1');
    $model->loadModel($request);
```
when load attributes in your model only attributes in scenario set to your model

and you can get scenario currently your model on this
```php
    $model->getScenario();
```

----
you can handle your validation rules with scenarios like this
```php
    public function validateRules(): array
    {
        return [
            'title' => Rule::when($this->getScenario() == 'scenario1', ['required', 'string']),
            'number' => Rule::when($this->getScenario() == 'scenario2', ['required', 'integer']),
        ];
    }
```

---
## Api Response
you can return your model data as response in your api \
in your api controller
```php
    return $model->responseAsJson();
```
in this order if your model has errors your response status code set to 422 and your error message in body \
else return your model attributes with value in body

in some api if you need No Content with 204 status code response  you can use this function
```php
    return $model->responseNoContent();
```

---
you can customize your fields in api response with overwrite fields() function in your model like this
```php
    public function fields(): array
    {
        // list of fields in response
        return [
            // field id cast to integer
            'id:int',
            // field title cast to string
            'title:string',
            // you can use your database relation like this (return relation model. you can custom fields in relation model)
            'dbRelation',
            // you can use specific field from your db relation
            'dbRelation.price',
            // and you can write custom field like this
            'custom:float' => function (self $model) {
                return $this->custom();
            },
        ];
    }
```
now supported cast type: int, string, float, bool

---
## Get fields in api
you can write extra fields in your model like this
```php
    public function extraFields(): array
    {
        // list of fields in response
        return [
            'extraTitle',
            ....
        ];
    }
```
extra fields are not included in the api response by default \
and to get extra fields in the api response you need to add 'expand' parameter to your query params in request

in fields and extraFields method you can return recursively you model fields

```http://127.0.0.1/api/test?expand=extraField1,extraField2``` \
in this request get all fields in fields() function and fields defined in 'expand' parameter

and you can get specific fields in your response by add 'fields' parameter in query params

```http://127.0.0.1/api/test?fields=id,title&expand=extraField1``` \
in this request only get id,title and extraField1

----

## Events
beforeValidate method is invoked before validation starts. \
You may override this method in your model \
If `false` is returned, the validation will stop and the model is considered invalid.
```php
    public function beforeValidate() : bool
    {
        ... your code here ...
        return true;
    }
```

afterValidate method is invoked after validation ends. \
You may override this method in your model
```php
    public function afterValidate(): void
    {
        .... your code here ....
    }
```
beforeSave method is called at the beginning of inserting or updating a record in eloquent models.
Override this method in your eloquent model \
If `false`, the insertion or updating will be cancelled.
```php
    public function beforeSave(array $options = []): bool
    {
        ... your code here ...
        return true;
    }
```
This method is called at the end of inserting or updating a record in eloquent models. \
Override this method in your eloquent model:
```php
    public function afterSave(array $options = []): void
    {
        ... your code here ...
    }
```
