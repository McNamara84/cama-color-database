<?php

declare(strict_types=1);

namespace Cama\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;

final class FormValidator
{
    /**
     * Values are deliberately not normalized or cast here. Callers choose explicit parsers.
     * @param array<string, Constraint|list<Constraint>> $fields
     * @return list<array{field: string, code: string, message: string}>
     */
    public function errors(mixed $input, array $fields): array
    {
        if (!is_array($input)) {
            return [['field' => '', 'code' => 'invalid_structure', 'message' => 'Die Eingabe muss aus benannten Feldern bestehen.']];
        }
        $violations = Validation::createValidator()->validate($input, new Collection(fields: $fields, allowExtraFields: false, allowMissingFields: false));
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'code' => $violation->getCode() ?? 'invalid', 'message' => (string) $violation->getMessage()];
        }

        return $errors;
    }
}
