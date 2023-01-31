<?php

namespace App;

class Validator
{
    public function validate($user)
    {
        $errors = [];
        if (strlen($user['name']) < 4) {
            $errors['name'] = 'Nickname must be greather than 4 characters';
        }
        if (isset($user['email'])) {
            if (strlen($user['email']) < 4) {
                $errors['email'] = 'Email must be greather then 4 characters';
            }
        }

        return $errors;
    }
}
