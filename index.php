<?php
require __DIR__ . '/vendor/autoload.php';
 
// Your App
$app = new Bullet\App();
$app->path('/payment-methods', function($request) {
    return [
        [
            'id' => 1,
            'name' => "Укрсиб",
            'currency' => 'UAH'
        ],
        [
            'id' => 2,
            'name' => "Наличные",
            'currency' => 'UAH'
        ]
    ];
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
