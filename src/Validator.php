<?php

namespace Appp;

class Validator
{
    public function validate($user)
    {
        $errors = [];
        if (count($user['name']) < 4) {
            $errors['name'] = 'Nickname must be greather than 4 characters';
        }
        if (count($user['email']) < 4) {
            $errors['email'] = 'Email must be greather then 4 characters';
        }
        return $errors;
    }
}
