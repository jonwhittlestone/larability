Laravel 4.2 - Readability Library - Larability
===============

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


Publish the larability image directory

    php artisan asset:publish jonwhittlestone/larability


####How to Use