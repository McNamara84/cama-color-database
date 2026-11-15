<?php

declare(strict_types=1);

namespace Cama\Auth;

use Cama\Validation\FormValidator;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountInput
{
    public static function email(string $email): string
    {
        return strtolower(trim($email));
    }

    /** @return list<array{field: string, code: string, message: string}> */
    public function errors(mixed $input, string $form): array
    {
        if (is_array($input)) {
            unset($input['_csrf']);
            foreach (['email', 'name'] as $key) {
                if (isset($input[$key]) && is_string($input[$key])) {
                    $input[$key] = trim($input[$key]);
                }
            }
        }
        $text = static fn (int $min, int $max): Assert\Sequentially => new Assert\Sequentially([
            new Assert\Type('string'), new Assert\NotBlank(message: 'Bitte fülle dieses Feld aus.'),
            new Assert\Length(min: max(0, $min), max: max(1, $max), minMessage: 'Bitte verwende mindestens {{ limit }} Zeichen.', maxMessage: 'Bitte verwende höchstens {{ limit }} Zeichen.'),
            new Assert\Regex(pattern: '/\x00/', match: false, message: 'Dieses Zeichen ist nicht erlaubt.'),
        ]);
        $fields = $form === 'password' ? ['current_password' => $text(1, 128)] : [
            'email' => new Assert\Sequentially([$text(3, 254), new Assert\Regex(pattern: '/^[\x21-\x7e]+$/D', message: 'Bitte verwende eine gültige E-Mail-Adresse.'), new Assert\Email(message: 'Bitte verwende eine gültige E-Mail-Adresse.')]),
        ];
        $fields['password'] = $text($form === 'login' ? 1 : 15, 128);
        if ($form !== 'login') {
            $fields['password_confirmation'] = $text(1, 128);
        }
        if ($form === 'create') {
            $fields['name'] = $text(1, 100);
        }
        $errors = (new FormValidator())->errors($input, $fields);
        if ($errors === [] && $form !== 'login' && $input['password'] !== $input['password_confirmation']) {
            $errors[] = ['field' => '[password_confirmation]', 'code' => 'mismatch', 'message' => 'Die Passwörter stimmen nicht überein.'];
        }
        return $errors;
    }
}
