<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Appp\Validator;

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $messages = $this->get('flash')->getMessages();
    $users = json_decode(file_get_contents('user.txt'), true);
    if (isset($term)) {
        $filteredUsers = array_filter($users, function ($user) use ($term) {
            return str_contains($user['userId'], $term);
        });
        $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $messages];
    } else {
        $params = ['users' => $users, 'flash' => $messages];
    }
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'userData' => [],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
})->setName('newUser');

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode(file_get_contents('user.txt'), true);

    $isFinded = false;
    $findedUser = [];
    foreach ($users as $user) {
        if ($user['userId'] === $id) {
            $isFinded = true;
            $findedUser = $user;
            break;
        }
    }
    if ($isFinded === false) {
        return $response->write('Page not found')
            ->withStatus(404);
    }

    $params = ['id' => $findedUser['userId'], 'nickname' => $findedUser['nickname'], 'email' => $findedUser['email']];
    return $this->get('renderer')->render($response, '/users/show.phtml', $params);
})->setName('user');

$router = $app->getRouteCollector()->getRouteParser();

$app->post('/users', function ($request, $response) use ($router) {
    $userData = $request->getParsedBodyParam('user');
    $id = uniqid();

    $validator = new Validator();
    $errors = $validator->validate($userData);
    if (count($errors) === 0) {
        $data = ['userId' => $id, 'nickname' => $userData['user']['name'], 'email' => $userData['user']['email']];
        $file = 'user.txt';
        $current = json_decode(file_get_contents($file));
        $current[] = $data;
        file_put_contents($file, json_encode($current));

        $this->get('flash')->addMessage('success', 'User has been created');
        $url = $router->urlFor('users');
        return $response->withRedirect($url);
    }

    $params = [
        'userData' => $userData,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $response->get('renderer')->render($response, '/users/new.phtml', $params);
});

$app->run();
