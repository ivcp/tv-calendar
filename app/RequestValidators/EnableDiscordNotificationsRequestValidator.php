<?php

declare(strict_types=1);

namespace App\RequestValidators;

use App\Contracts\RequestValidatorInterface;
use App\Exception\ValidationException;
use App\Services\DiscordWebhookUrlValidator;
use Valitron\Validator;

class EnableDiscordNotificationsRequestValidator implements RequestValidatorInterface
{
    public function validate(array $data): array
    {
        $v = new Validator($data);

        $v->rule('required', ['discordWebhookUrl']);
        $v->rule('url', 'discordWebhookUrl')
            ->message("Url not valid");
        $v->rule('lengthMax', 'discordWebhookUrl', 500);
        $v->rule(
            static fn (string $field, mixed $value): bool => DiscordWebhookUrlValidator::isValid(
                is_string($value) ? $value : null
            ),
            'discordWebhookUrl'
        )->message('Url must be a Discord webhook URL (https://discord.com/api/webhooks/...)');



        if (! $v->validate()) {
            throw new ValidationException($v->errors());
        }

        return $data;
    }
}
