<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../spender/generated-conf/config.php';
require_once  __DIR__ . '/../vendor/paragonie/random_compat/lib/random.php';
define('USER_KEYS_DIR', __DIR__ . '/../user-keys');

if (in_array(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '', ['https://spender.pereborstudio.com', 'http://spender.pereborstudio.dev:8081'])) {
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

/**
 * Generate a random string, using a cryptographically secure
 * pseudorandom number generator (random_int)
 *
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 *
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 */
function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
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
            $user->setEmail($gapiResponse->email);
            $user->setName($gapiResponse->name);
            $user->save();

            file_put_contents(USER_KEYS_DIR . '/' . $gapiUserId, random_str(random_int(90, 128)));
        }

        if (file_exists(USER_KEYS_DIR . '/' . $gapiUserId)) {
            $encryptionKey = file_get_contents(USER_KEYS_DIR . '/' . $gapiUserId);
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

        $tableName = \Map\PaymentMethodTableMap::TABLE_NAME;

        $con = \Propel\Runtime\Propel::getWriteConnection(\Map\PaymentMethodTableMap::DATABASE_NAME);
        $params = array();
        $sql = "SELECT {$tableName}.*, expenses.sum as expenses, incomes.sum as incomes FROM $tableName
            LEFT OUTER JOIN ({$expenses->createSelectSql($params)}) AS expenses ON expenses.payment_method_id = {$tableName}.id
            LEFT OUTER JOIN ({$incomes->createSelectSql($params)}) AS incomes ON incomes.payment_method_id = {$tableName}.id
            WHERE payment_method.user_id = :id
            ORDER BY {$tableName}.name";

        $stmt = $con->prepare($sql);
        $stmt->execute(array(':id' => $user->getId()));

        $formatter = new \Propel\Runtime\Formatter\ObjectFormatter();
        $formatter->setClass('\PaymentMethod');

        $formatter->setAsColumns(['expenses' => 'expenses', 'incomes' => 'incomes']);

        $objects = $formatter->format($con->getDataFetcher($stmt));

        return $objects->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
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
            $d = new DateTime($request->createdAt);
            $expense->setCreatedAt($d->getTimestamp());
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
            ->leftJoinExpense()
            ->leftJoin('Expense.PaymentMethod ExpensePaymentMethod')
            ->withColumn('ExpensePaymentMethod.Currency', 'sourceExpensePaymentMethodCurrency')
            ->withColumn('ExpensePaymentMethod.Id', 'sourceExpensePaymentMethodId')
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
            $d = new DateTime($request->createdAt);
            $income->setCreatedAt($d->getTimestamp());
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
