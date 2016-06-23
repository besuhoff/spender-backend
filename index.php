<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';


header('Access-Control-Allow-Origin: http://spender.pereborstudio.dev:8081');
header('Access-Control-Allow-Headers: X-Auth-Token, Content-Type');
define('GAPI_CLIENT_ID', '843225840486-ilkj47kggue9tvh6ajfvvog45mertgfg.apps.googleusercontent.com');

$gapiUserId = false;
$token = isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? $_SERVER['HTTP_X_AUTH_TOKEN'] : '';

$app = new Bullet\App();
$user = false;

if ($token) {
    $gapiResponse = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token)));

    if ($gapiResponse->aud === GAPI_CLIENT_ID) {
        $gapiUserId = $gapiResponse->sub;
        $user = \Base\UserQuery::create()->findOneByGapiUserId($gapiUserId);

        if (!$user) {
            $user = new User();
            $user->setGapiUserId($gapiUserId);
            $user->save();
        }
    }
}

if (!$gapiUserId && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    echo $app->response(403);
    exit();
};

$app->path('/payment-methods', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $paymentMethods = \Base\PaymentMethodQuery::create()
            ->orderByName()
            ->addSelfSelectColumns()
            ->useExpenseQuery('expense', 'LEFT JOIN')
            ->withColumn('IFNULL(SUM(expense.amount), 0)', 'Expenses')
            ->endUse()
            ->useIncomeQuery('income', 'LEFT JOIN')
            ->withColumn('IFNULL(SUM(income.amount), 0)', 'Incomes')
            ->endUse()
            ->groupById()
            ->groupByUserId()
            ->filterByUserId($user->getId())
            ->find()
            ->toArray();

        return $paymentMethods;
    });

    $app->post(function($request) use($app, $user) {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setName($request->Name);
        $paymentMethod->setCurrency($request->Currency);
        $user->addPaymentMethod($paymentMethod);
        $user->save();

        return $paymentMethod->toArray();
    });
});

$app->path('/categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $categories = \Base\CategoryQuery::create()->orderByName()->findByUserId($user->getId());
        return $categories->toArray();
    });
});

$app->path('/income-categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomeCategories = \Base\IncomeCategoryQuery::create()->orderByName()->findByUserId($user->getId());
        return $incomeCategories->toArray();
    });
});

$app->path('/expenses', function($request) use($app, $user) {
    $app->post(function($request) use($app, $user) {
        $expense = new Expense();
        $expense->setAmount($request->Amount);
        $expense->setCategoryId($request->CategoryId);
        $expense->setPaymentMethodId($request->PaymentMethodId);
        $expense->setComment($request->Comment);
        $user->addExpense($expense);
        $user->save();

        return $expense->toArray();
    });
});

$app->path('/incomes', function($request) use($app, $user) {
    $app->post(function($request) use($app, $user) {
        $income = new Income();
        $income->setAmount($request->Amount);
        $income->setIncomeCategoryId($request->IncomeCategoryId);
        $income->setPaymentMethodId($request->PaymentMethodId);
        $income->setComment($request->Comment);
        $user->addIncome($income);
        $user->save();

        return $income->toArray();
    });
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());