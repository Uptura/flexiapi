<?php

namespace FlexiAPI\Utils;

class Validator
{
    private array $errors = [];
    
    public function validate(array $data, array $rules): array
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $this->validateRule($field, $value, $rule, $data);
            }
        }
        
        return $this->errors;
    }
    
    private function validateRule(string $field, $value, string $rule, array $data): void
    {
        if (str_contains($rule, ':')) {
            [$ruleName, $parameter] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} field must be a valid email address.");
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$parameter) {
                    $this->addError($field, "The {$field} field must be at least {$parameter} characters.");
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$parameter) {
                    $this->addError($field, "The {$field} field must not exceed {$parameter} characters.");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "The {$field} field must be numeric.");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$field} field must be an integer.");
                }
                break;
                
            case 'boolean':
                if (!empty($value) && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    $this->addError($field, "The {$field} field must be true or false.");
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} field must be a valid URL.");
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "The {$field} field must be a valid date.");
                }
                break;
                
            case 'in':
                $allowedValues = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $allowedList = implode(', ', $allowedValues);
                    $this->addError($field, "The {$field} field must be one of: {$allowedList}.");
                }
                break;
                
            case 'unique':
                // This would require database access, skip for now
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (isset($data[$confirmField]) && $value !== $data[$confirmField]) {
                    $this->addError($field, "The {$field} field confirmation does not match.");
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, "The {$field} field format is invalid.");
                }
                break;
        }
    }
    
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public static function rules(): array
    {
        return [
            'required' => 'Field is required',
            'email' => 'Field must be a valid email',
            'min:X' => 'Field must be at least X characters',
            'max:X' => 'Field must not exceed X characters',
            'numeric' => 'Field must be numeric',
            'integer' => 'Field must be an integer',
            'boolean' => 'Field must be true or false',
            'url' => 'Field must be a valid URL',
            'date' => 'Field must be a valid date',
            'in:a,b,c' => 'Field must be one of the specified values',
            'confirmed' => 'Field must have a matching confirmation field',
            'regex:/pattern/' => 'Field must match the specified pattern'
        ];
    }
}