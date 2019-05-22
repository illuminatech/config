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

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, $storage))
    ->setItems([
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
            $storage = new StorageDb($this->app->make('db.connection'));

            $newConfig = (new PersistentRepository($originConfig, $storage))
                ->setItems([
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

$persistentConfigRepository = (new PersistentRepository(...))
    ->setItems([
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
 - [[\Illuminatech\Config\StoragePhp]] - stores configuration in local PHP files;
 - [[\Illuminatech\Config\StorageArray]] - stores configuration in runtime memory;

Please refer to the particular storage class for more details.


## Saving and restoring data <span id="saving-restoring-data"></span>

[[\Illuminatech\Config\PersistentRepository]] will automatically retrieve config item values from persistent storage on the
first attempt to get config value from it.

```php
<?php

use Illuminatech\Config\PersistentRepository;

$persistentConfigRepository = (new PersistentRepository(...))
    ->setItems([
        'some.config',
    ]);

$value = $persistentConfigRepository->get('some.config'); // loads data from persistent storage automatically.
```

You may also manually fetch data from persistent storage using `restore()` method:

```php
<?php

use Illuminatech\Config\PersistentRepository;

$persistentConfigRepository = (new PersistentRepository(...))
    ->setItems([
        'some.config',
    ]);

$persistentConfigRepository->restore(); // loads/re-loads data from persistent storage
```

**Heads up!** Any error or exception, which appears during values restoration, will be automatically suppressed. This is 
done to avoid application blocking in case storage is not yet ready for usage, for example: database table does not yet exist.
Storage failure error will appear only at the application log. You should manually test value restoration is working at
your application to avoid unexpected behavior.

To save config data into persistent storage use method `save()`:

```php
<?php

use Illuminatech\Config\PersistentRepository;

$persistentConfigRepository = (new PersistentRepository(...))
    ->setItems([
        'some.config',
        'another.config',
    ]);

$persistentConfigRepository->save([
    'some.config' => 'some persistent value',
    'another.config' => 'another persistent value',
]);
```

Changes made via regular config repository interface (e.g. via methods `set()`, `push()` and so on) will not be automatically
saved into the persistent storage. However, you may use `synchronize()` method to save current config item values into it.

```php
<?php

use Illuminatech\Config\PersistentRepository;

$persistentConfigRepository = (new PersistentRepository(...))
    ->setItems([
        'some.config',
        'another.config',
    ]);

$persistentConfigRepository->set('some.config', 'new value'); // no changes at the persistent storage at this point

$persistentConfigRepository->synchronize(); // save values to the persistent storage
```

Method `reset()` clears all data saved to the persistent storage, restoring original (e.g. default) config repository values.

```php
<?php

use Illuminate\Config\Repository;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'some' => [
        'config' => 'original value',
    ],
]);

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, ...))
    ->setItems([
        'some.config',
    ]);

$persistentConfigRepository->save([
    'some.config' => 'new value',
]);

echo $persistentConfigRepository->get('some.config'); // outputs 'new value'

$persistentConfigRepository->reset(); // clears data in the persistent storage

echo $persistentConfigRepository->get('some.config'); // outputs 'original value'
```

You can also use `resetValue()` method to reset particular config key only.


## Caching <span id="caching"></span>

You can use [PSR-16](https://www.php-fig.org/psr/psr-16/) compatible cache storage to improve performance of the config item
retrieval from persistent storage. For example:

```php
<?php

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\App;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'some' => [
        'config' => 'original value',
    ],
]);

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, ...))
    ->setItems([
        'some.config',
    ])
    ->setCache(App::make('cache.store'))
    ->setCacheKey('global-config')
    ->setCacheTtl(3600 * 24);
```


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
        $this->config = $app->get('config');
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
    
    public function restoreDefaults()
    {
        $this->config->reset();
        
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
    <label>{{$item->label}}</label>
    <input type="text" name="{{$item->id}}" value="{{$item->getValue()}}">
    <p>{{$item->hint}}</p>
@endforeach
...
</form>
...
```


## Typecast <span id="typecast"></span>

You may operate complex type values like arrays as a persistent ones. In order to do so, you should specify config item
typecasting via [[\Illuminatech\Config\Item::$cast]]. For example:

```php
<?php

use Illuminate\Config\Repository;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'some' => [
        'array' => ['one', 'two', 'three'],
    ],
]);

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, ...))
    ->setItems([
        'some.array' => [
            'cast' => 'array', // cast value from persistent storage to array
            'rules' => ['sometimes', 'require', 'array'],
        ],
    ]);

$persistentConfigRepository->save([
    'some.array' => ['five', 'six'],
]);

$persistentConfigRepository->restore();

var_dump($persistentConfigRepository->get('some.array') === ['five', 'six']); // outputs 'true'
```


## Encryption <span id="encryption"></span>

In case you are planning to operate sensitive data like passwords, API keys and so on, you may want to store them as an
encrypted strings rather then the plain ones. This can be achieved enabling [[\Illuminatech\Config\Item::$encrypt]].
For example:

```php
<?php

use Illuminate\Config\Repository;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'some' => [
        'apiKey' => 'secret',
    ],
]);

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, ...))
    ->setItems([
        'some.apiKey' => [
            'encrypt' => true, // encrypt value before placing it into the persistent storage
        ],
    ]);
```

Note that data encryption will impact the config repository performance.


## Garbage collection <span id="garbage-collection"></span>

As your project evolves new configuration items may appear as well as some becomes redundant.
[[\Illuminatech\Config\PersistentRepository]] automatically ignores any value in persistent storage in case it has no
matching config item set by `setItems()`. Thus stored obsolete values will not affect config repository anyway, however
they still may consume extra space inside the storage. You may manually remove all obsolete values from the storage,
using `gc()` method:

```php
<?php

use Illuminate\Config\Repository;
use Illuminatech\Config\StorageDb;
use Illuminatech\Config\PersistentRepository;

$sourceConfigRepository = new Repository([
    'some' => [
        'config' => 'original value',
    ],
]);

$storage = new StorageDb(...);
$storage->save([
    'some.config' => 'some value',
    'obsolete.config' => 'obsolete value',
]);

$persistentConfigRepository = (new PersistentRepository($sourceConfigRepository, $storage))
    ->setItems([
        'some.config',
    ]);

$persistentConfigRepository->gc(); // removes 'obsolete.config' from storage
```

In case [[Illuminatech\Config\PersistentRepository::$gcEnabled]] enabled garbage collection will be performed automatically
each time config values are saved via `save()` or `synchronize()` method.
