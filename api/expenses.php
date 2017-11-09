<?php
require_once __DIR__ . '/../vendor/autoload.php';

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
