<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/spender/generated-conf/config.php';


header('Access-Control-Allow-Origin: http://spender.pereborstudio.dev:8081');
header('Access-Control-Allow-Headers: X-Auth-Token');
define('GAPI_CLIENT_ID', '843225840486-ilkj47kggue9tvh6ajfvvog45mertgfg.apps.googleusercontent.com');

$gapiUserId = false;
$token = isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? $_SERVER['HTTP_X_AUTH_TOKEN'] : '';

$app = new Bullet\App();

if ($token) {
    $gapiResponse = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token)));

    if ($gapiResponse->aud === GAPI_CLIENT_ID) {
        $gapiUserId = $gapiResponse->sub;
    }
}

if (!$gapiUserId && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    echo $app->response(403);
    exit();
};

$app->path('/payment-methods', function($request) use($app) {
    $app->get(function($request) {
        $paymentMethods = \Base\PaymentMethodQuery::create()->orderByName()->find();
        return $paymentMethods->toArray();
    });
});

$app->path('/categories', function($request) use($app) {
    $app->get(function($request) {
        $categories = \Base\CategoryQuery::create()->orderByName()->find();
        return $categories->toArray();
    });
});

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
