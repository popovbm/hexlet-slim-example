<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

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
    $users = json_decode(file_get_contents(USERS), true);
    $result = collect($users)->filter(fn ($user) => empty($term) ? true : str_contains(strtolower($user['name']), strtolower($term)));
    $params = [
        'users' => $result,
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

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode(file_get_contents(USERS), true);
    $user = collect($users)->firstWhere('id', $id);

    if (is_null($user)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $params = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
    return $this->get('renderer')->render($response, '/users/show.phtml', $params);
})->setName('user');

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $messages = $this->get('flash')->getMessages();
    $id = $args['id'];
    $users = json_decode(file_get_contents(USERS), true);
    $user = collect($users)->firstWhere('id', $id);
    $params = [
        'user' => $user,
        'flash' => $messages,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$router = $app->getRouteCollector()->getRouteParser();

$app->post('/users', function ($request, $response) use ($router) {
    $userData = $request->getParsedBodyParam('user');
    $id = uniqid();

    $validator = new Validator();
    $errors = $validator->validate($userData);
    if (count($errors) === 0) {
        $data = ['id' => $id, 'name' => $userData['name'], 'email' => $userData['email']];
        $current = json_decode(file_get_contents(USERS));
        $current[] = $data;
        file_put_contents(USERS, json_encode($current));

        $this->get('flash')->addMessage('success', 'User has been created');
        $url = $router->urlFor('users');
        return $response->withRedirect($url);
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
    $users = json_decode(file_get_contents(USERS), true);
    $user = collect($users)->firstWhere('id', $id);
    $data = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        $updatedUsers = collect($users)->each(function ($user) use ($id, $data) {
            if ($user['id'] === $id) {
                $item['name'] = $data['name'];
            }
        })->toArray();
        // foreach ($users as $user) {
        //     if ($user['id'] === $id) {
        //         $user['name'] = $data['name'];
        //         break;
        //     }
        // }
        file_put_contents(USERS, json_encode($updatedUsers));
        $this->get('flash')->addMessage('success', 'User has been updated');
        $url = $router->urlFor('editUser', ['id' => $user['id']]);
        return $response->withRedirect($url);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->run();
