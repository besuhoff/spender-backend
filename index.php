<?php
require __DIR__ . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: http://spender.pereborstudio.dev:8081');

// Your App
$app = new Bullet\App();
$app->path('/payment-methods', function($request) use($app) {
    $app->get(function($request) {
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
            ],
        ];
    });
});

$app->path('/categories', function($request) use($app) {
    $app->get(function($request) {
        return [
            [
                'id' => 1,
                'name' => "Продукты"
            ],
            [
                'id' => 2,
                'name' => "Спорт и развлечения",
            ],
            [
                'id' => 3,
                'name' => "Ребёнок",
            ],
            [
                'id' => 4,
                'name' => "Доставка еды",
            ],
            [
                'id' => 5,
                'name' => "Развлечения",
            ],
            [
                'id' => 6,
                'name' => "Здоровье и красота",
            ],
            [
                'id' => 7,
                'name' => "Кафе",
            ],
            [
                'id' => 8,
                'name' => "Проезд",
            ],
            [
                'id' => 9,
                'name' => "Отпуск",
            ],
            [
                'id' => 10,
                'name' => "Одежда и обувь",
            ],
            [
                'id' => 11,
                'name' => "Благотворительность",
            ],
            [
                'id' => 12,
                'name' => "Платежи",
            ],
            [
                'id' => 13,
                'name' => "Отложено",
            ],
            [
                'id' => 14,
                'name' => "Что-то",
            ],
        ];
    });
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
