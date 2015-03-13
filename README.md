Laravel 4.2 - Readability Library - Larability
===============

[![Join the chat at https://gitter.im/jonwhittlestone/larability](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/jonwhittlestone/larability?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Larability is a fork of [PHP Readability Library](https://github.com/feelinglucky/php-readability) for use with Laravel 4.

#### Quick Start

In your `config/app.php` add `'Jonwhittlestone\Larability\LarabilityServiceProvider'` to the end of the `$providers` array

```php
'providers' => array(

    'Illuminate\Foundation\Providers\ArtisanServiceProvider',
    'Illuminate\Auth\AuthServiceProvider',
    ...
    'Jonwhittlestone\Larability\LarabilityServiceProvider',

),
```

Publish the larability image directory

    php artisan asset:publish jonwhittlestone/larability


####How to Use

    $results = Larability::read('http://www.bbc.co.uk/news/uk-31792238');
    print '<pre>' . print_r($results, true) . '</pre>';