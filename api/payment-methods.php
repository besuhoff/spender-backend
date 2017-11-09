<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app->path('/payment-methods', function($request) use($app, $user) {
    $app->get(function($request) use($app, $user) {
        $sqlTotalsTemplate = 'SELECT %1$s.payment_method_id, SUM(amount) AS sum FROM %1$s GROUP BY %1$s.payment_method_id';
        $incomeSql = sprintf($sqlTotalsTemplate, \Map\IncomeTableMap::TABLE_NAME);
        $expenseSql = sprintf($sqlTotalsTemplate, \Map\ExpenseTableMap::TABLE_NAME);

        $tableName = \Map\PaymentMethodTableMap::TABLE_NAME;
        $archiveTableName = \Map\PaymentMethodArchiveTableMap::TABLE_NAME;

        $con = \Propel\Runtime\Propel::getWriteConnection(\Map\PaymentMethodTableMap::DATABASE_NAME);
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
                LEFT OUTER JOIN ({$expenseSql}) AS expenses ON expenses.payment_method_id = {$tableName}.id
                LEFT OUTER JOIN ({$incomeSql}) AS incomes ON incomes.payment_method_id = {$tableName}.id
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
