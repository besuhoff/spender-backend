<?php
/**
 * Created by PhpStorm.
 * User: besuhoff
 * Date: 04.08.16
 * Time: 0:57
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';

$categories = [
    ['name' => ['ru' => 'Благотворительность',  'en' => 'Charity',          'en-GB' => 'Charity'],         'color' => '#73cf76'],
    ['name' => ['ru' => 'Еда',                  'en' => 'Food',             'en-GB' => 'Food'],            'color' => '#ffd9b7'],
    ['name' => ['ru' => 'Одежда',               'en' => 'Clothes',          'en-GB' => 'Clothes'],         'color' => '#ffedae'],
    ['name' => ['ru' => 'Кафе и рестораны',     'en' => 'Restaurants',      'en-GB' => 'Restaurants'],     'color' => '#ffaf6b'],
    ['name' => ['ru' => 'Транспорт',            'en' => 'Transport',        'en-GB' => 'Transport'],       'color' => '#7cbce4'],
    ['name' => ['ru' => 'Платежи',              'en' => 'Payments',         'en-GB' => 'Payments'],        'color' => '#74c9cd'],
    ['name' => ['ru' => 'Развлечения',          'en' => 'Entertainment',    'en-GB' => 'Entertainment'],   'color' => '#a4e28b'],
    ['name' => ['ru' => 'Спорт',                'en' => 'Sports',           'en-GB' => 'Sports'],          'color' => '#d2fb97'],
    ['name' => ['ru' => 'Дети',                 'en' => 'Children',         'en-GB' => 'Children'],        'color' => '#e7a6c9'],
    ['name' => ['ru' => 'Работа',               'en' => 'Work',             'en-GB' => 'Work'],            'color' => '#d29cfc'],
    ['name' => ['ru' => 'Хозяйство',            'en' => 'Household',        'en-GB' => 'Household'],       'color' => '#a5dcf2'],
    ['name' => ['ru' => 'Путешествия',          'en' => 'Travelling',       'en-GB' => 'Travelling'],      'color' => '#9df7f2'],
    ['name' => ['ru' => 'Долг',                 'en' => 'Debts',            'en-GB' => 'Debts'],           'color' => '#ff837c'],
    ['name' => ['ru' => 'Здоровье',             'en' => 'Health',           'en-GB' => 'Health'],          'color' => '#ffb0ae'],
    ['name' => ['ru' => 'Другое',               'en' => 'Uncategorized',    'en-GB' => 'Uncategorized'],   'color' => '#c1c2d1'],

];

foreach ($categories as $category) {

    $insert = new CategorySample();

    foreach ($category['name'] as $locale => $name) {
        $insert->setLocale($locale);
        $insert->setName($name);
    }
    $insert->setColor($category['color']);

    $insert->save();
}