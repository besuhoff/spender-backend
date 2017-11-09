<?php
require_once __DIR__ . '/../vendor/autoload.php';

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
