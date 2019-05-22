<p align="center">
    <a href="https://github.com/illuminatech" target="_blank">
        <img src="https://avatars1.githubusercontent.com/u/47185924" height="100px">
    </a>
    <h1 align="center">Laravel Runtime Configuration Extension</h1>
    <br>
</p>

This extension provides support for application runtime configuration, loading config from database.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/illuminatech/config/v/stable.png)](https://packagist.org/packages/illuminatech/config)
[![Total Downloads](https://poser.pugx.org/illuminatech/config/downloads.png)](https://packagist.org/packages/illuminatech/config)
[![Build Status](https://travis-ci.org/illuminatech/config.svg?branch=master)](https://travis-ci.org/illuminatech/config)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist illuminatech/config
```

or add

```json
"illuminatech/config": "*"
```

to the require section of your composer.json.


Usage
-----

This extension allows reconfiguration of already created config repository using data from the external storage like relational database.
It provides special config repository class [[\Illuminatech\Config\PersistentRepository]], which wraps any given config repository,
adding layer for saving and restoring of data from persistent storage.

```php
<?php

use Illuminate\Config\Repository;
use Illuminatech\Config\StorageDb;
use Illuminate\Support\Facades\App;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'foo' => [
        'name' => 'Foo',
    ],
    'bar' => [
        'enabled' => false,
    ],
    'other' => [
        'value' => 'Some',
    ],
]);

$storage = new StorageDb(App::make('db.connection'));

$persistentConfigRepository = new PersistentRepository($sourceConfigRepository, $storage);
$persistentConfigRepository->setItems([
    'foo.name',
    'bar.enabled',
]);

echo $persistentConfigRepository->get('foo.name'); // returns value from database if present, otherwise the one from source repository, in this case - 'Foo'

echo $persistentConfigRepository->get('other.value'); // keys, which are not specified as "items" always remain intact, in this case - always return 'Some'
```

Config data, which should be saved in persistent storage defined via [[\Illuminatech\Config\PersistentRepository::setItems()]].
Only keys, which are explicitly defined as "items", will be stored or retrieved from the persistent storage. Any other data
present in the source config repository will remain as it is.

PersistentRepository fully decorates any config repository, matching [[\Illuminate\Contracts\Config\Repository]] and can substitute [[\Illuminate\Config\Repository]] instance.
In particular this allows you to substitute regular Laravel config by [[\Illuminatech\Config\PersistentRepository]] instance,
applying configuration from database to the entire application. You can so in your `AppServiceProvider` class. For example:

```php
<?php

namespace App\Providers;

use Illuminatech\Config\StorageDb;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminatech\Config\PersistentRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->extend('config', function (Repository $originConfig) {
            $storage = $this->app->make(StorageDb::class);

            $newConfig = new PersistentRepository($originConfig, $storage);
            $newConfig->setItems([
                'mail.contact.address' => [
                    'label' => 'Email address receiving contact messages',
                    'rules' => ['sometimes', 'required', 'email'],
                ],
                // ...
            ]);

            return $newConfig;
        });

        // ...
    }
}
```

Then anytime you access 'config' service in your application via `config()` function, [[\Illuminate\Support\Facades\Config]] facade
or via service container you will interact with [[\Illuminatech\Config\PersistentRepository]] instance getting values modified
by database data.


## Configuration items specification <span id="configuration-items-specification"></span>

Config parts, which should be saved in the persistent storage are defined by [[\Illuminatech\Config\PersistentRepository::setItems()]],
which accepts a list of [[\Illuminatech\Config\Item]] or configuration array for it.
Each configuration item should define a key, which leads to the target value in source repository.
Configuration item also have several properties, which supports creation of web interface for configuration setup.
These are:

 - 'id' - string, item unique ID in the list, this value will be used in request fields and form inputs.
 - 'label' - string, verbose label for the config value input.
 - 'hint' - string, verbose description for the config value or input hint.
 - 'rules' - array, value validation rules.
 - 'cast' - string, native type for the value to be cast to.

Since only 'key' is mandatory item may be specified by single string defining this key.

Here are some examples of item specifications:

```php
<?php

use Illuminatech\Config\Item;
use Illuminatech\Config\PersistentRepository;

$persistentConfigRepository = new PersistentRepository(...);
$persistentConfigRepository->setItems([
    'some.config.value',
    'another.config.value' => [
        'label' => 'Custom label',
        'rules' => ['required', 'numeric'],
    ],
    [
        'key' => 'array.config.value',
        'rules' => ['required', 'array'],
        'cast' => 'array',
    ],
    new Item(['key' => 'explicit.object']),
]);
```


## Configuration storage <span id="configuration-storage"></span>

Declared configuration items may be saved into persistent storage and then retrieved from it.
The actual item storage can be any class matching [[\Illuminatech\Config\StorageContact]] interface.

Following storages are available within this extension:

 - [[\Illuminatech\Config\StorageDb]] - stores configuration inside relational database;
 - [[\Illuminatech\Config\StorageEloquent]] - stores configuration using Eloquent models;
 - [[\Illuminatech\Config\StorageArray]] - stores configuration in runtime memory;

Please refer to the particular storage class for more details.


## Saving and restoring data <span id="saving-restoring-data"></span>


## Validation <span id="validation"></span>

Each configuration item comes with validation rules, which matches `['sometimes' ,'required']` by default. You can easily
create a validation for the user input before config saving, using these rules, or use [[\Illuminatech\Config\PersistentRepository::validate()]].
For example:

```php
<?php

/* @var $request Illuminate\Http\Request */
/* @var $config Illuminatech\Config\PersistentRepository */

$validatedData = $config->validate($request->all()); // throws \Illuminate\Validation\ValidationException if validation fails.
// ...
```

You can also use [[\Illuminatech\Config\PersistentRepository::makeValidator()]] method to create a validator instance for manual processing.

**Heads up!** Watch for usage dot symbols ('.') inside the input in case you do not use [[\Illuminatech\Config\PersistentRepository::validate()]] method.
By default Laravel considers dots in validation rules as array nested keys separator. You should either replace them
by '->' string or manually define [[\Illuminatech\Config\Item::$id]] in the way it does not contain a dot.


## Creating configuration web interface <span id="creating-configuration-web-interface"></span>

One of the most common use case for this extension is creating a web interface, which allows control of application
configuration in runtime.
[[\Illuminatech\Config\PersistentRepository]] serves not only for applying of the configuration - it also helps to create an
interface for configuration editing.

The web controller for configuration management may look like following:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;

class ConfigController extends Controller
{
    /**
     * @var \Illuminatech\Config\PersistentRepository persistent config repository, which is set at `AppServiceProvider`.
     */
    private $config;
    
    public function __construct(Container $app)
    {
        $this->config = $app->get('config')->restore();
    }
    
    public function index()
    {
        $this->config->restore(); // ensure config values restored from database
        
        return view('config.form', ['items' => $this->config->getItems()]);
    }
    
    public function update(Request $request)
    {
        $validatedData = $this->config->validate($request->all());
    
        $this->config->save($validatedData);
    
        return back()->with('status', 'success');
    }
}
```

You can operate [[\Illuminatech\Config\Item]] interface during HTML form input composition. For example:

```blade
...
<form ...>
...
@foreach($items as $item)
    <input type="text" name="{{$item->id}}" value="{{$item->getValue()}}">
@endforeach
...
</form>
...
```
