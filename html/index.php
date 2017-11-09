<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../spender/generated-conf/config.php';
require_once  __DIR__ . '/../vendor/paragonie/random_compat/lib/random.php';
define('USER_KEYS_DIR', __DIR__ . '/../user-keys');

if (in_array(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '', ['https://spender.pereborstudio.com', 'http://spender.pereborstudio.develop:8081'])) {
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
    $app->path('/users', function($request) use($app, &$user, $gapiUserId, $gapiResponse) {
        $app->post(function($request) use($app, &$user, $gapiUserId, $gapiResponse) {
            if (!$user) {
                $user = new User();
                $user->setGapiUserId($gapiUserId);
                $user->setEmail($gapiResponse->email);
                $user->setName($gapiResponse->name);
                $user->setWizardStep(1);

                $sampleCategories = CategorySampleQuery::create()->find();
                foreach ($sampleCategories as $sampleCategory) {
                    $category = new Category();

                    $sampleCategory->setLocale($gapiResponse->locale);
                    $name = $sampleCategory->getName();

                    if (!$name) {
                        $sampleCategory->setLocale('en');
                        $name = $sampleCategory->getName();
                    }

                    $category->setName($name);

                    $category->setColor($sampleCategory->getColor());
                    $user->addCategory($category);
                }

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

if ($gapiUserId && $user) {
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

    require_once '../api/payment-methods.php';

    require_once '../api/limits.php';

    require_once '../api/categories.php';

    require_once '../api/income-categories.php';

    require_once '../api/expenses.php';

    require_once '../api/incomes.php';

    require_once '../api/currencies.php';
}

// Run the app! (takes $method, $url or Bullet\Request object)
echo $app->run(new Bullet\Request());
