<?php
require_once __DIR__ . '/../vendor/autoload.php';

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
