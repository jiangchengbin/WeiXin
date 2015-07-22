# Laravel 5 微信接口封装包(增加支付宝封装)

=================
Laravel 5 wrapper for the [WeiXin](http://www.lixijing520.com) API.


## Installation
Require this package, with [Composer](https://getcomposer.org/), in the root directory of your project.

```bash
composer require jiangchengbin/weixin:~1.0
```

Add the service provider to ```config/app.php``` in the `providers` array.

```php
'Jiangchengbin\weixin\WeiXinServiceProvider'
```

If you want you can use the [facade](http://laravel.com/docs/facades). Add the reference in ```config/app.php``` to your aliases array.

```php
'Dev' => 'Jiangchengbin\weixin\Facades\Dev'
```

## Configuration

Laravel Debug requires connection configuration. To get started, you'll need to publish all vendor assets:

```bash
php artisan vendor:publish


