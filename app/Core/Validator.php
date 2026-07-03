<?php

namespace App\Core;

class Validator
{
    protected array $errors = [];
    protected array $data = [];

    /**
     * Валидация входных данных по заданным правилам
     */
    public function validate(array $data, array $rules): bool
    {
        $this->data = $data;

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

            case 'max':
                if ($value !== '' && mb_strlen($value) > (int)$param) {
                    $this->errors[$field][] = "Поле {$field} должно быть не более {$param} символов.";
                }
                break;

            case 'match':
                if ($value !== '' && $param !== null) {
                    $matchValue = $this->data[$param] ?? '';
                    if ($value !== $matchValue) {
                        $this->errors[$field][] = "Поле {$field} должно совпадать с полем {$param}.";
                    }
                }
                break;

			case 'regex':
				if ($value !== '' && $param !== null) {
					if (!preg_match($param, $value)) {
						$this->errors[$field][] = "Поле {$field} имеет недопустимый формат.";
					}
				}
				break;

            case 'unique':
                if ($value !== '' && $param !== null) {
                    $commaPos = strpos($param, ',');
                    if ($commaPos !== false) {
                        $table = substr($param, 0, $commaPos);
                        $column = substr($param, $commaPos + 1);

                        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) || 
                            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                            throw new \InvalidArgumentException("Недопустимое имя таблицы или колонки в правиле unique");
                        }

                        $db = Database::getConnection();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val LIMIT 1");
                        $stmt->execute(['val' => $value]);

                        if ((int)$stmt->fetchColumn() > 0) {
                            $this->errors[$field][] = "Такой {$value} уже зарегистрирован в системе.";
                        }
                    }
                }
                break;

            default:
                throw new \InvalidArgumentException("Неизвестное правило валидации: {$rule}");
        }
    }

    /**
     * Проверка, прошла ли валидация успешно
     */
    public function isValid(): bool
    {
        return empty($this->errors);
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
    public function getFirstError(string $field): array
    {
        return isset($this->errors[$field]) ? $this->errors[$field] : '';
    }
}