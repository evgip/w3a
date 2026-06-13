<?php

namespace App\Core;

class Validator
{
    protected array $errors = [];

    /**
     * Валидация входных данных по заданным правилам
     */
    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $fieldRules) {
            $rulesArray = explode('|', $fieldRules);
            $value = trim($data[$field] ?? '');

            foreach ($rulesArray as $rule) {
                $colonPos = strpos($rule, ':');

                if ($colonPos !== false) {
                    $ruleName = substr($rule, 0, $colonPos);
                    $param = substr($rule, $colonPos + 1);
                } else {
                    $ruleName = $rule;
                    $param = null;
                }

                $this->checkRule($field, $value, $ruleName, $param);
            }
        }

        return empty($this->errors);
    }

    /**
     * Внутренняя проверка конкретного правила
     */
    protected function checkRule(string $field, $value, string $rule, $param = null): void
    {
        switch ($rule) {
            case 'required':
                if ($value === '') {
                    $this->errors[$field][] = "Поле {$field} обязательно для заполнения.";
                }
                break;

            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "Поле {$field} должно быть корректным email-адресом.";
                }
                break;

            case 'min':
                if ($value !== '' && mb_strlen($value) < (int)$param) {
                    $this->errors[$field][] = "Поле {$field} должно быть не менее {$param} символов.";
                }
                break;

            case 'unique':
                if ($value !== '' && $param !== null) {
                    $commaPos = strpos($param, ',');
                    if ($commaPos !== false) {
                        $table = substr($param, 0, $commaPos);
                        $column = substr($param, $commaPos + 1);

                        $db = Database::getConnection();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val LIMIT 1");
                        $stmt->execute(['val' => $value]);

                        if ((int)$stmt->fetchColumn() > 0) {
                            $this->errors[$field][] = "Такой {$value} уже зарегистрирован в системе.";
                        }
                    }
                }
                break;
        }
    }

    /**
     * Получить все ошибки валидации
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Получить первую ошибку для конкретного поля
     */
    public function getFirstError(string $field): string
    {
        return isset($this->errors[$field]) ? $this->errors[$field] : '';
    }
}
