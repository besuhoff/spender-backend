<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';

header('Access-Control-Allow-Origin: http://spender.pereborstudio.com');
header('Access-Control-Allow-Headers: X-Auth-Token, Content-Type');
header('Access-Control-Allow-Methods: POST,GET,HEAD,OPTIONS,DELETE,PATCH,PUT');
define('GAPI_CLIENT_ID', '843225840486-ilkj47kggue9tvh6ajfvvog45mertgfg.apps.googleusercontent.com');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$gapiUserId = false;
$token = isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? $_SERVER['HTTP_X_AUTH_TOKEN'] : '';

$app = new Bullet\App();
$user = false;

if ($token) {
    $gapiResponse = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token)));

    if ($gapiResponse->aud === GAPI_CLIENT_ID) {
        $gapiUserId = $gapiResponse->sub;
        $user = UserQuery::create()->findOneByGapiUserId($gapiUserId);

        if (!$user) {
            $user = new User();
            $user->setGapiUserId($gapiUserId);
            $user->save();
        }
    }
}

if (!$gapiUserId) {
    echo $app->response(403);
    exit(403);
};

$app->path('/payment-methods', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomes = IncomeQuery::create()
            ->groupByPaymentMethodId()
            ->withColumn('SUM(amount)', 'sum');

        $expenses = ExpenseQuery::create()
            ->groupByPaymentMethodId()
            ->withColumn('SUM(amount)', 'sum');

        $paymentMethods = PaymentMethodQuery::create()
            ->clearSelectColumns()
            ->addAsColumn('Expenses', 'expenses.sum')
            ->addAsColumn('Incomes', 'incomes.sum');

        $phpFieldNames = \Map\PaymentMethodTableMap::getFieldNames(\Map\PaymentMethodTableMap::TYPE_PHPNAME);
        $sqlFieldNames = \Map\PaymentMethodTableMap::getFieldNames(\Map\PaymentMethodTableMap::TYPE_FIELDNAME);

        $tableName = \Map\PaymentMethodTableMap::TABLE_NAME;

        foreach($phpFieldNames as $index => $fieldName) {
            $paymentMethods->addAsColumn($fieldName, $tableName . '.' . $sqlFieldNames[$index]);
        }

        $con = \Propel\Runtime\Propel::getWriteConnection(\Map\PaymentMethodTableMap::DATABASE_NAME);
        $sql = "{$paymentMethods->createSelectSql()} $tableName
            LEFT OUTER JOIN ({$expenses->createSelectSql()}) AS expenses ON expenses.payment_method_id = {$tableName}.id
            LEFT OUTER JOIN ({$incomes->createSelectSql()}) AS incomes ON incomes.payment_method_id = {$tableName}.id
            WHERE payment_method.user_id = :id
            ORDER BY {$tableName}.name";

        $stmt = $con->prepare($sql);
        $stmt->execute(array(':id' => $user->getId()));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

    $app->post(function($request) use($app, $user) {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setName($request->Name);
        $paymentMethod->setCurrency($request->Currency);
        $user->addPaymentMethod($paymentMethod);
        $user->save();

        return $paymentMethod->toArray();
    });

    $app->param('int', function($request, $paymentMethodId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $paymentMethodId) {
            $paymentMethod = PaymentMethodQuery::create()->findOneById($paymentMethodId);
            $paymentMethod->setName($request->Name);
            $paymentMethod->setCurrency($request->Currency);
            $paymentMethod->save();

            return $paymentMethod->toArray();
        });

        $app->delete(function ($request) use ($app, $user, $paymentMethodId) {
            $paymentMethod = PaymentMethodQuery::create()->findOneById($paymentMethodId);
            $paymentMethod->delete();

            return '{}';
        });
    });
});

$app->path('/categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $categories = CategoryQuery::create()->orderByName()->findByUserId($user->getId());
        return $categories->toArray();
    });

    $app->post(function($request) use($app, $user) {
        $category = new Category();
        $category->setName($request->Name);
        $user->addCategory($category);
        $user->save();

        return $category->toArray();
    });

    $app->param('int', function($request, $categoryId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $categoryId) {
            $category = CategoryQuery::create()->findOneById($categoryId);
            $category->setName($request->Name);
            $category->save();

            return $category->toArray();
        });

        $app->delete(function ($request) use ($app, $user, $categoryId) {
            $category = CategoryQuery::create()->findOneById($categoryId);
            $category->delete();

            return '{}';
        });
    });
});

$app->path('/income-categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomeCategories = IncomeCategoryQuery::create()->orderByName()->findByUserId($user->getId());
        return $incomeCategories->toArray();
    });

    $app->post(function($request) use($app, $user) {
        $category = new IncomeCategory();
        $category->setName($request->Name);
        $user->addIncomeCategory($category);
        $user->save();

        return $category->toArray();
    });

    $app->param('int', function($request, $categoryId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $categoryId) {
            $category = IncomeCategoryQuery::create()->findOneById($categoryId);
            $category->setName($request->Name);
            $category->save();

            return $category->toArray();
        });

        $app->delete(function ($request) use ($app, $user, $categoryId) {
            $category = IncomeCategoryQuery::create()->findOneById($categoryId);
            $category->delete();

            return '{}';
        });
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