# chargebee-php-sdk-wp

[![Packagist](https://img.shields.io/packagist/dt/globalis/chargebee-php-sdk-wp.svg?style=flat-square)](https://packagist.org/packages/globalis/chargebee-php-sdk-wp)
[![Latest Stable Version](https://poser.pugx.org/globalis/chargebee-php-sdk-wp/v/stable)](https://packagist.org/packages/globalis/chargebee-php-sdk-wp)
[![License](https://poser.pugx.org/globalis/chargebee-php-sdk-wp/license)](https://github.com/globalis-ms/chargebee-php-sdk-wp/blob/master/LICENSE)

Overview
------------

WordPress integration for [globalis/chargebee-php-sdk](https://github.com/globalis-ms/chargebee-php-sdk)

Features
------------
- Convert PSR-14 events into WordPress hooks
- Add [query-monitor](https://github.com/johnbillion/query-monitor) integration

Installation
------------

```bash
composer require globalis/chargebee-php-sdk-wp
```

Basic usage
------------

```php
<?php

add_action('globalis/chargebee_api_response', function($event) {
    // $event contains data about the API request and response
    // do something
}, 10, 1);

add_action('globalis/chargebee_api_error', function($event) {
    // $event contains data about the API request and response
    // do something
}, 10, 1);
```

Screenshots
------------

[![chargebee-php-sdk-wp query-monitor](https://github.com/globalis-ms/chargebee-php-sdk-wp/blob/master/.resources/screenshot-query-monitor-1.jpg)](https://raw.githubusercontent.com/globalis-ms/chargebee-php-sdk-wp/master/.resources/screenshot-query-monitor-1.jpg)
