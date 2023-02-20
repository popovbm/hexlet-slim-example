<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use App\Validator;
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;

const USERS = 'users.txt';

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
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $messages = $this->get('flash')->getMessages();
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $findedUser = collect($users)->filter(
        fn($user) => empty($term) ? true : str_contains(strtolower($user['name']), strtolower($term))
    );
    $params = [
        'users' => $findedUser,
        'flash' => $messages,
        'term' => $term
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'userData' => [],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
})->setName('newUser');

$app->get('/users/login', function ($request, $response) {
    return $this->get('renderer')->render($response, 'login.phtml');
})->setName('login');

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $hasUser = collect($users)->has($id);
    if ($hasUser !== true) {
        return $response->write('Page not found')->withStatus(404);
    }
    $user = $users[$id];
    $messages = $this->get('flash')->getMessages();

    $button = $request->getQueryParam('button') ?? 'hidden';
    $params = [
        'id' => $id,
        'name' => $user['name'],
        'email' => $user['email'],
        'flash' => $messages,
        'button' => $button
    ];
    return $this->get('renderer')->render($response, '/users/show.phtml', $params);
})->setName('user');

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $messages = $this->get('flash')->getMessages();
    $id = $args['id'];
    $cook = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = $cook[$id];
    $params = [
        'user' => $user,
        'id' => $id,
        'flash' => $messages,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');


$router = $app->getRouteCollector()->getRouteParser();

$app->post('/users', function ($request, $response) use ($router) {
    $userData = $request->getParsedBodyParam('user');
    $id = uniqid();
    $cook = json_decode($request->getCookieParam('users', json_encode([])), true);

    $validator = new Validator();
    $errors = $validator->validate($userData);
    if (count($errors) === 0) {
        $cook[$id] = ['name' => $userData['name'], 'email' => $userData['email']];
        $encodedUsers = json_encode($cook);
        $this->get('flash')->addMessage('success', 'User has been created');
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($router->urlFor('users'));
    }

    $params = [
        'userData' => $userData,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, '/users/new.phtml', $params);
});

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $cook = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = $cook[$id];
    $data = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        $cook[$id]['name'] = $data['name'];
        $encodedUsers = json_encode($cook);
        $this->get('flash')->addMessage('success', 'User has been updated');
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect(
            $router->urlFor('editUser', ['id' => $id])
        );
    }

    $params = [
        'user' => $user,
        'id' => $id,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $cook = json_decode($request->getCookieParam('users', json_encode([])), true);
    $deletedUser = collect($cook)->except($id);
    $encodedUsers = json_encode($deletedUser);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($router->urlFor('users'));
});

$app->delete('/users', function ($request, $response) use ($router, $app) {
    $encodedUsers = json_encode([]);
    return $response->withHeader('Set-Cookie', "users=$encodedUsers")->withRedirect($router->urlFor('users'));
});

$app->post('/users/login', function ($request, $response) use ($router) {
    $data = $request->getParsedBodyParam('user');
    $cook = json_decode($request->getCookieParam('users', json_encode([])), true);
    $hasUser = collect($cook)->firstWhere('email', $data['email']);
    foreach ($cook as $key => $user) {
        if ($user['email'] === $data['email']) {
            $hasUser[$key] = true;
        }
    }

    if (count($hasUser) !== 0) {
        $this->get('flash')->addMessage('success', 'Access has granted');
        return $response->withRedirect($router->urlFor('user', ['id' => key($hasUser)], ['button' => 'visibility']));
    }

    $error['email'] = 'Email is incorrect';
    $params = [
        'errors' => $error,
        'userData' => $data
    ];
    return $this->get('renderer')->render($response, 'login.phtml', $params);
});

$app->run();
