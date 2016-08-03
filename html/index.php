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
$gapiResponse = false;
$token = isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? $_SERVER['HTTP_X_AUTH_TOKEN'] : '';

$app = new Bullet\App();
$user = false;

if ($token) {
    $gapiResponse = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token)));

    if ($gapiResponse->aud === GAPI_CLIENT_ID) {
        $gapiUserId = $gapiResponse->sub;
        $user = UserQuery::create()->findOneByGapiUserId($gapiUserId);
    }
}

if ($gapiUserId) {
    $app->path('/users', function($request) use($app, $user, $gapiUserId, $gapiResponse) {
        $app->post(function($request) use($app, $user, $gapiUserId, $gapiResponse) {
            if (!$user) {
                $user = new User();
                $user->setGapiUserId($gapiUserId);
                $user->setEmail($gapiResponse->email);
                $user->setName($gapiResponse->name);
                $user->setWizardStep(1);

                file_put_contents(USER_KEYS_DIR . '/' . $gapiUserId, random_str(random_int(90, 128)));
            } else {
                $user->setEmail($gapiResponse->email);
                $user->setName($gapiResponse->name);
            }

            $user->save();

            return $user->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });
   });
}

if (!$gapiUserId || !$user) {
    echo $app->response(403);
    exit(403);
};

if (file_exists(USER_KEYS_DIR . '/' . $gapiUserId)) {
    $encryptionKey = file_get_contents(USER_KEYS_DIR . '/' . $gapiUserId);
}

$app->path('/user', function($request) use($app, $user) {
    $app->patch(function ($request) use ($app, $user) {
        $user->setWizardStep($request->wizardStep);
        $user->save();

        return $user->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });
});

$app->path('/payment-methods', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomes = IncomeQuery::create()
            ->groupByPaymentMethodId()
            ->withColumn('SUM(amount)', 'sum');

        $expenses = ExpenseQuery::create()
            ->groupByPaymentMethodId()
            ->withColumn('SUM(amount)', 'sum');

        $tableName = \Map\PaymentMethodTableMap::TABLE_NAME;
        $archiveTableName = \Map\PaymentMethodArchiveTableMap::TABLE_NAME;

        $con = \Propel\Runtime\Propel::getWriteConnection(\Map\PaymentMethodTableMap::DATABASE_NAME);
        $params = array();
        $sql = "SELECT
                {$tableName}.id as Id,
                {$tableName}.name as `Name`,
                {$tableName}.color as Color,
                {$tableName}.currency_id as CurrencyId,
                {$tableName}.user_id as UserId,
                {$tableName}.initial_amount as InitialAmount,
                {$tableName}.created_at as CreatedAt,
                {$tableName}.updated_at as UpdatedAt,
                expenses.sum as expenses,
                incomes.sum as incomes,
                0 as _isRemoved
            FROM {$tableName}
            LEFT OUTER JOIN ({$expenses->createSelectSql($params)}) AS expenses ON expenses.payment_method_id = {$tableName}.id
            LEFT OUTER JOIN ({$incomes->createSelectSql($params)}) AS incomes ON incomes.payment_method_id = {$tableName}.id
            WHERE {$tableName}.user_id = :id1

            UNION

            SELECT
                {$archiveTableName}.id as Id,
                {$archiveTableName}.name as `Name`,
                {$archiveTableName}.color as Color,
                {$archiveTableName}.currency_id as CurrencyId,
                {$archiveTableName}.user_id as UserId,
                {$archiveTableName}.initial_amount as InitialAmount,
                {$archiveTableName}.created_at as CreatedAt,
                {$archiveTableName}.updated_at as UpdatedAt,
                0 as expenses,
                0 as incomes,
                1 as _isRemoved
            FROM {$archiveTableName}
            WHERE {$archiveTableName}.user_id = :id2
            ";

        $stmt = $con->prepare($sql);
        $stmt->execute(array(
            ':id1' => $user->getId(),
            ':id2' => $user->getId()
        ));

        $formatter = new \Propel\Runtime\Formatter\ObjectFormatter();
        $formatter->setClass('\PaymentMethod');

        $formatter->setAsColumns([
            'expenses' => 'expenses',
            'incomes' => 'incomes',
            '_isRemoved' => '_isRemoved',
        ]);

        $objects = $formatter->format($con->getDataFetcher($stmt));

        return $objects->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->post(function($request) use($app, $user) {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setName($request->name);
        $paymentMethod->setInitialAmount($request->initialAmount);
        $paymentMethod->setColor($request->color);
        $paymentMethod->setCurrencyId($request->get('currency.id'));
        $user->addPaymentMethod($paymentMethod);
        $user->save();

        return $paymentMethod->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $paymentMethodId) use($app, $user) {
        $app->patch(function ($request) use ($app, $user, $paymentMethodId) {
            $paymentMethod = PaymentMethodQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($paymentMethodId);
            $paymentMethod->setName($request->name);
            $paymentMethod->setInitialAmount($request->initialAmount);
            $paymentMethod->setColor($request->color);
            $paymentMethod->setCurrencyId($request->get('currency.id'));
            $paymentMethod->save();

            return $paymentMethod->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $paymentMethodId) {
            $paymentMethod = PaymentMethodQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($paymentMethodId);
            $paymentMethod->delete();

            return '{}';
        });
    });
});

$app->path('/categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $categories = CategoryQuery::create()
            ->orderByName()
            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        return array_merge(
            $categories,
            array_map(
                function($item) {
                    $item['_isRemoved'] = true;
                    return $item;
                },
                CategoryArchiveQuery::create()
                    ->orderByName()

                    ->findByUserId($user->getId())
                    ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME)
            )
        );
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
            $category = CategoryQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($categoryId);
            $category->setName($request->name);
            $category->setColor($request->color);
            $category->save();

            return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $categoryId) {
            $category = CategoryQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($categoryId);
            $category->delete();

            return '{}';
        });
    });
});

$app->path('/income-categories', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomeCategories = IncomeCategoryQuery::create()
            ->orderByName()
            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        return array_merge(
            $incomeCategories,
            array_map(
                function($item) {
                    $item['_isRemoved'] = true;
                    return $item;
                },
                IncomeCategoryArchiveQuery::create()
                    ->orderByName()

                    ->findByUserId($user->getId())
                    ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME)
            )
        );
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
            $category = IncomeCategoryQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($categoryId);
            $category->setName($request->name);
            $category->setColor($request->color);
            $category->save();

            return $category->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $categoryId) {
            $category = IncomeCategoryQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($categoryId);
            $category->delete();

            return '{}';
        });
    });
});

$app->path('/expenses', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $expenses = ExpenseQuery::create()
            ->orderByCreatedAt()

            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        return array_merge(
            $expenses,
            array_map(
                function($item) {
                    $item['_isRemoved'] = true;
                    return $item;
                },
                ExpenseArchiveQuery::create()
                    ->orderByCreatedAt()

                    ->findByUserId($user->getId())
                    ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME)
            )
        );
    });

    $app->post(function($request) use($app, $user) {
        $expense = new Expense();
        $expense->setAmount($request->amount);
        $isValid = false;
        if ($request->category) {
            $expense->setCategoryId($request->get('category.id'));
            $isValid = true;
        } elseif ($request->targetIncome) {
            $targetIncome = IncomeQuery::create()->findOneById($request->get('targetIncome.id'));
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
        $expense->setPaymentMethodId($request->get('paymentMethod.id'));
        $expense->setComment($request->comment);
        $user->addExpense($expense);
        $user->save();

        return $expense->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $expenseId) use($app, $user) {
        $app->patch(function($request) use($app, $user, $expenseId) {
            $expense = ExpenseQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($expenseId);

            $expense->setAmount($request->amount);
            $isValid = false;
            if ($request->category) {
                $expense->setCategoryId($request->get('category.id'));
                $isValid = true;
            } elseif ($request->targetIncome) {
                $targetIncome = IncomeQuery::create()
                    ->filterByUserId($user->getId())
                    ->findOneById($request->get('targetIncome.id'));
                if ($targetIncome) {
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
            $expense->setPaymentMethodId($request->get('paymentMethod.id'));
            $expense->setComment($request->comment);
            $expense->save();

            return $expense->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $expenseId) {
            $expense = ExpenseQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($expenseId);
            $expense->delete();

            return '{}';
        });
    });
});

$app->path('/incomes', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $incomes = IncomeQuery::create()
            ->orderByCreatedAt()

            ->findByUserId($user->getId())
            ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);

        return array_merge(
            $incomes,
            array_map(
                function($item) {
                    $item['_isRemoved'] = true;
                    return $item;
                },
                IncomeArchiveQuery::create()
                    ->orderByCreatedAt()

                    ->findByUserId($user->getId())
                    ->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME)
            )
        );
    });

    $app->post(function($request) use($app, $user) {
        $income = new Income();
        $income->setAmount($request->amount);
        if ($request->incomeCategory) {
            $income->setIncomeCategoryId($request->get('incomeCategory.id'));
        }
        if ($request->createdAt) {
            $d = new DateTime($request->createdAt);
            $income->setCreatedAt($d->getTimestamp());
        }
        $income->setPaymentMethodId($request->get('paymentMethod.id'));
        $income->setComment($request->comment);
        $user->addIncome($income);
        $user->save();

        return $income->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });

    $app->param('int', function($request, $incomeId) use($app, $user) {
        $app->patch(function($request) use($app, $user, $incomeId) {
            $income = IncomeQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($incomeId);

            $income->setAmount($request->amount);
            if ($request->incomeCategory) {
                $income->setIncomeCategoryId($request->get('incomeCategory.id'));
            }
            if ($request->createdAt) {
                $d = new DateTime($request->createdAt);
                $income->setCreatedAt($d->getTimestamp());
            }
            $income->setPaymentMethodId($request->get('paymentMethod.id'));
            $income->setComment($request->comment);
            $income->save();

            return $income->toArray(\Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
        });

        $app->delete(function ($request) use ($app, $user, $incomeId) {
            $income = IncomeQuery::create()
                ->filterByUserId($user->getId())
                ->findOneById($incomeId);
            $income->delete();

            return '{}';
        });
    });
});

$app->path('/currencies', function($request) use($app, $user) {
    $app->get(function ($request) use ($app, $user) {
        $currencies = CurrencyQuery::create()->orderById()->find();
        return $currencies->toArray(null, false, \Propel\Runtime\Map\TableMap::TYPE_CAMELNAME);
    });
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
