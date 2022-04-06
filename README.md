# Simple PHP Autowiring

## Package Installation
```composer require rumur/autowire```

### How to use it?
It's actually pretty simple. A good starting point is to create an instance of `Autowire` class.

```php
// plugin-name.php
<?php

use Rumur\Autowiring\Autowire;

$autowire = Autowire::create();

````

### Register Singletons
`Autowire` allows you to register any instance/variable as a singleton, so you'll be able always get the same outcome, 
when one of your classes or functions would need such dependency.

For an instance, let's imagine that you need to inject `wpdb` class in some repositories of yours.
For this, we would need to register `wbdb` as a singleton and just pass one of your repositories into the `Autowire::make` method and the rest `Autowire` does for you. 

```php
// plugin-name.php
<?php
// ...

$autowire->singleton(wpdb::class, fn() => $GLOBALS['wpdb']);

// plugin/repositories/IOrderRepository.php

interface IOrderRepository {
    public function find(int $order_id): ?Order
    public function findAll(): array
}

// plugin/repositories/OrderRepository.php
use wpdb;

class OrderRepository implements IOrderRepository {
    protected wpdb $connection;
    
    public function __construct(wpdb $connection) {
        $this->connection = $connection;
    }
    
    public function find(int $order_id): ?Order {
        // ...
    } 
    
    public function findAll(): array {
        // ...
    } 
    
    // ...
}

// Somewhere in the code
$order = $autowire->make(OrderRepository::class)->find(2022);
````

### Binding
In case you need to bind a specific interface with its implementation `Autowire` gets you covered.

**NOTE. In some case you don't have to `bind` all available classes to `Autowire` in order to be able to resolve them as a dependency, e.g. `OrderController` 
below never been bound to `Autowire` but it still can `make` it for you automatically.** 
```php
// Somewhere where service providers are getting injected.
$autowire->bind(IOrderRepository::class, OrderRepository::class)

// app/http/api/OrderController.php
class OrderController {
    protected IOrderRepository $repository;
    
    public function __construct(IOrderRepository $repository) {
        $this->repository;
    }
    
    public function index(): array {
        return $this->repository->findAll();
    }
}

// Somewhere in you app.
$ctrl = app()->autowire->make(OrderController::class);

$orders = $ctrl->index();
```

### Autowire callables
In case you need to resolve dependencies either for a method or
any other callable instance, `Autowire::call` comes to rescue.
```php
<?php
$autowire->singleton(IOrderRepository::class, OrderRepository::class)

class OrderController {
    public function index(IOrderRepository $repository): array {
        return $repository->findAll();
    }
}

$ctrl = new OrderController; 

$orders = app()->autowire->call([$ctrl,'index']);

// Or if `index` method is static do the following.
$orders = app()->autowire->call('OrderController::index');
```

## License
This package is licensed under the MIT License - see the [LICENSE.md](https://github.com/rumur/autowire/blob/main/LICENSE) file for details.