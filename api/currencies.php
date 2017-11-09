<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app->path('/currencies', function($request) use($app, $user) {
    $app->get(function ($request) use ($app, $user) {
        $currencies = CurrencyQuery::create()->orderById()->find();
        return $currencies->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });
});
