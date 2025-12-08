<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Enum\NotificationTime;
use App\Exception\ValidationException;
use Valitron\Validator;

class SetNotificationTimeRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $v = new Validator($data);
        $v->rule('required', ['notificationTime']);
        $v->rule(
            'in',
            'notificationTime',
            array_column(NotificationTime::cases(), 'value')
        );

        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
