<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';

if (in_array($_SERVER['HTTP_ORIGIN'], ['http://spender.pereborstudio.com', 'http://spender.pereborstudio.dev:8081'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    exit(0);
}

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
            ->addAsColumn('expenses', 'expenses.sum')
            ->addAsColumn('incomes', 'incomes.sum');

        $phpFieldNames = \Map\PaymentMethodTableMap::getFieldNames(\Map\PaymentMethodTableMap::TYPE_CAMELNAME);
        $sqlFieldNames = \Map\PaymentMethodTableMap::getFieldNames(\Map\PaymentMethodTableMap::TYPE_FIELDNAME);

        $tableName = \Map\PaymentMethodTableMap::TABLE_NAME;

        foreach($phpFieldNames as $index => $fieldName) {
            $paymentMethods->addAsColumn($fieldName, $tableName . '.' . $sqlFieldNames[$index]);
        }

        $con = \Propel\Runtime\Propel::getWriteConnection(\Map\PaymentMethodTableMap::DATABASE_NAME);
        $params = array();
        $sql = "{$paymentMethods->createSelectSql($params)} $tableName
            LEFT OUTER JOIN ({$expenses->createSelectSql($params)}) AS expenses ON expenses.payment_method_id = {$tableName}.id
            LEFT OUTER JOIN ({$incomes->createSelectSql($params)}) AS incomes ON incomes.payment_method_id = {$tableName}.id
            WHERE payment_method.user_id = :id
            ORDER BY {$tableName}.name";

        $stmt = $con->prepare($sql);
        $stmt->execute(array(':id' => $user->getId()));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

    $app->post(function($request) use($app, $user) {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setName($request->name);
        $paymentMethod->setColor($request->color);
        $paymentMethod->setCurrency($request->currency);
        $user->addPaymentMethod($paymentMethod);
        $user->save();

        return $paymentMethod->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $paymentMethodId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $paymentMethodId) {
            $paymentMethod = PaymentMethodQuery::create()->findOneById($paymentMethodId);
            $paymentMethod->setName($request->name);
            $paymentMethod->setColor($request->color);
            $paymentMethod->setCurrency($request->currency);
            $paymentMethod->save();

            return $paymentMethod->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
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
        return $categories->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->post(function($request) use($app, $user) {
        $category = new Category();
        $category->setName($request->name);
        $category->setColor($request->color);
        $user->addCategory($category);
        $user->save();

        return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $categoryId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $categoryId) {
            $category = CategoryQuery::create()->findOneById($categoryId);
            $category->setName($request->name);
            $category->setColor($request->color);
            $category->save();

            return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
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
        return $incomeCategories->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->post(function($request) use($app, $user) {
        $category = new IncomeCategory();
        $category->setName($request->name);
        $category->setColor($request->color);
        $user->addIncomeCategory($category);
        $user->save();

        return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $categoryId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $categoryId) {
            $category = IncomeCategoryQuery::create()->findOneById($categoryId);
            $category->setName($request->name);
            $category->setColor($request->color);
            $category->save();

            return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $categoryId) {
            $category = IncomeCategoryQuery::create()->findOneById($categoryId);
            $category->delete();

            return '{}';
        });
    });
});

$app->path('/expenses', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $expenses = ExpenseQuery::create()
            ->orderByCreatedAt()
            ->leftJoinPaymentMethod()
            ->withColumn('PaymentMethod.Name', 'paymentMethodName')
            ->withColumn('PaymentMethod.Color', 'paymentMethodColor')
            ->withColumn('PaymentMethod.Currency', 'paymentMethodCurrency')
            ->leftJoinCategory()
            ->withColumn('Category.Name', 'categoryName')
            ->withColumn('Category.Color', 'categoryColor')
            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        foreach($expenses as $index => $expense) {
            if ($expense['paymentMethodName'] === null) {
                $paymentMethod = PaymentMethodArchiveQuery::create()->findOneById($expense['paymentMethodId']);
                if ($paymentMethod) {
                    $expenses[$index]['paymentMethodName'] = $paymentMethod->getName();
                    $expenses[$index]['paymentMethodColor'] = $paymentMethod->getColor();
                    $expenses[$index]['paymentMethodCurrency'] = $paymentMethod->getCurrency();
                }
            }

            if ($expense['categoryName'] === null) {
                $category = CategoryArchiveQuery::create()->findOneById($expense['categoryId']);
                if ($category) {
                    $expenses[$index]['categoryName'] = $category->getName();
                    $expenses[$index]['categoryColor'] = $category->getColor();
                }
            }
        }
        return $expenses;
    });

    $app->post(function($request) use($app, $user) {
        $expense = new Expense();
        $expense->setAmount($request->amount);
        $isValid = false;
        if ($request->categoryId) {
            $expense->setCategoryId($request->categoryId);
            $isValid = true;
        } elseif ($request->targetIncomeId) {
            $targetIncome = IncomeQuery::create()->findOneById($request->targetIncomeId);
            if ($targetIncome && $targetIncome->getUser()->getId() === $user->getId()) {
                $isValid = true;
                $expense->setTargetIncomeId($targetIncome->getId());
            }
        }
        if (!$isValid) {
            return $app->response(422);
        }

        if ($request->createdAt) {
            $expense->setCreatedAt($request->createdAt);
        }
        $expense->setPaymentMethodId($request->paymentMethodId);
        $expense->setComment($request->comment);
        $user->addExpense($expense);
        $user->save();

        return $expense->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });
});

$app->path('/incomes', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomes = IncomeQuery::create()
            ->orderByCreatedAt()
            ->leftJoinPaymentMethod()
            ->withColumn('PaymentMethod.Name', 'paymentMethodName')
            ->withColumn('PaymentMethod.Color', 'paymentMethodColor')
            ->withColumn('PaymentMethod.Currency', 'paymentMethodCurrency')
            ->leftJoinIncomeCategory()
            ->withColumn('IncomeCategory.Name', 'incomeCategoryName')
            ->withColumn('IncomeCategory.Color', 'incomeCategoryColor')
            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        foreach($incomes as $index => $income) {
            if ($income['paymentMethodName'] === null) {
                $paymentMethod = PaymentMethodArchiveQuery::create()->findOneById($income['paymentMethodId']);

                if ($paymentMethod) {
                    $incomes[$index]['paymentMethodName'] = $paymentMethod->getName();
                    $incomes[$index]['paymentMethodColor'] = $paymentMethod->getColor();
                    $incomes[$index]['paymentMethodCurrency'] = $paymentMethod->getCurrency();
                }
            }

            if ($income['incomeCategoryName'] === null) {
                $category = IncomeCategoryArchiveQuery::create()->findOneById($income['incomeCategoryId']);

                if ($category) {
                    $incomes[$index]['incomeCategoryName'] = $category->getName();
                    $incomes[$index]['incomeCategoryColor'] = $category->getColor();
                }
            }
        }
        return $incomes;
    });

    $app->post(function($request) use($app, $user) {
        $income = new Income();
        $income->setAmount($request->amount);
        if ($request->incomeCategoryId) {
            $income->setIncomeCategoryId($request->incomeCategoryId);
        }
        if ($request->createdAt) {
            $income->setCreatedAt($request->createdAt);
        }
        $income->setPaymentMethodId($request->paymentMethodId);
        $income->setComment($request->comment);
        $user->addIncome($income);
        $user->save();

        return $income->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());