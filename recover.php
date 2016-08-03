<?php
/**
 * Created by PhpStorm.
 * User: besuhoff
 * Date: 04.08.16
 * Time: 0:57
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';

$dir = $argv[1];

function load($file) {
    global $dir;
    return file_get_contents(__DIR__ . '/../' . $dir . '/' . $file . '.json');
}

$currencies = json_decode(load('currencies'));

echo "Inserting currencies...\n";

foreach ($currencies as $currency) {
    var_dump($currency);

    $insert = new Currency();
    $insert->setCode($currency->code);
    $insert->setSymbol($currency->symbol);
    $insert->setSymbolNative($currency->symbolNative);
    $insert->setDecimalDigits($currency->decimalDigits);
    $insert->setRounding($currency->rounding);
    $d = new DateTime($currency->createdAt);
    $insert->setCreatedAt($d->getTimestamp());
    $d = new DateTime($currency->updatedAt);
    $insert->setUpdatedAt($d->getTimestamp());
    $insert->setId($currency->id);
    $insert->save();
}

$users = array_filter(glob(__DIR__ . '/../' . $dir . '/*'), 'is_dir');
foreach ($users as $user) {
    $user = basename($user);

    echo "Inserting user data: $user\n";

    $paymentMethods = json_decode(load($user . '/paymentMethods'));

    echo "Inserting paymentMethods...\n";

    foreach ($paymentMethods as $paymentMethod) {
        var_dump($paymentMethod);

        $insert = $paymentMethod->_isRemoved ? new PaymentMethodArchive() : new PaymentMethod();

        $insert->setName($paymentMethod->name);
        $insert->setInitialAmount($paymentMethod->initialAmount);
        $insert->setColor($paymentMethod->color);
        $insert->setCurrencyId($paymentMethod->currencyId);

        $insert->setUserId($paymentMethod->userId);
        $d = new DateTime($paymentMethod->createdAt);
        $insert->setCreatedAt($d->getTimestamp());
        $d = new DateTime($paymentMethod->updatedAt);
        $insert->setUpdatedAt($d->getTimestamp());
        $insert->setId($paymentMethod->id);
        $insert->save();
    }

    $categories = json_decode(load($user . '/categories'));

    echo "Inserting categories...\n";

    foreach ($categories as $category) {
        var_dump($category);

        $insert = $category->_isRemoved ? new CategoryArchive() : new Category();

        $insert->setName($category->name);
        $insert->setColor($category->color);

        $insert->setUserId($category->userId);
        $d = new DateTime($category->createdAt);
        $insert->setCreatedAt($d->getTimestamp());
        $d = new DateTime($category->updatedAt);
        $insert->setUpdatedAt($d->getTimestamp());
        $insert->setId($category->id);
        $insert->save();
    }

    $categories = json_decode(load($user . '/incomeCategories'));

    echo "Inserting incomeCategories...\n";

    foreach ($categories as $category) {
        var_dump($category);

        $insert = $category->_isRemoved ? new IncomeCategoryArchive() : new IncomeCategory();

        $insert->setName($category->name);
        $insert->setColor($category->color);

        $insert->setUserId($category->userId);
        $d = new DateTime($category->createdAt);
        $insert->setCreatedAt($d->getTimestamp());
        $d = new DateTime($category->updatedAt);
        $insert->setUpdatedAt($d->getTimestamp());
        $insert->setId($category->id);
        $insert->save();
    }

    $incomes = json_decode(load($user . '/incomes'));

    echo "Inserting incomes...\n";

    foreach ($incomes as $income) {
        var_dump($income);

        $insert = $income->_isRemoved ? new IncomeArchive() : new Income();

        $insert->setAmount($income->amount);
        if ($income->incomeCategory) {
            $insert->setIncomeCategoryId($income->incomeCategory->id);
        }
        $insert->setPaymentMethodId($income->paymentMethod->id);
        $insert->setComment($income->comment);

        $insert->setUserId($income->userId);
        $d = new DateTime($income->createdAt);
        $insert->setCreatedAt($d->getTimestamp());
        $d = new DateTime($income->updatedAt);
        $insert->setUpdatedAt($d->getTimestamp());
        $insert->setId($income->id);
        $insert->save();
    }

    $expenses = json_decode(load($user . '/expenses'));

    echo "Inserting expenses...\n";

    foreach ($expenses as $expense) {
        var_dump($expense);

        $insert = $expense->_isRemoved ? new ExpenseArchive() : new Expense();

        $insert->setAmount($expense->amount);
        if ($expense->category) {
            $insert->setCategoryId($expense->category->id);
        }
        $insert->setPaymentMethodId($expense->paymentMethod->id);
        if ($expense->targetIncomeId) {
            $insert->setTargetIncomeId($expense->targetIncomeId);
        }
        $insert->setComment($expense->comment);

        $insert->setUserId($expense->userId);
        $d = new DateTime($expense->createdAt);
        $insert->setCreatedAt($d->getTimestamp());
        $d = new DateTime($expense->updatedAt);
        $insert->setUpdatedAt($d->getTimestamp());
        $insert->setId($expense->id);
        $insert->save();
    }
}