<?php

namespace TransMore\Backend;

class Validator
{
    private array $errors = [];

    public function required(string $field, $value): self
    {
        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
            $this->errors[$field] = ucfirst($field) . ' is required.';
        }

        return $this;
    }

    public function email(string $field, $value): self
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid email address.';
        }

        return $this;
    }

    public function minLength(string $field, $value, int $length): self
    {
        if ($value !== null && strlen((string) $value) < $length) {
            $this->errors[$field] = ucfirst($field) . ' must be at least ' . $length . ' characters.';
        }

        return $this;
    }

    public function maxLength(string $field, $value, int $length): self
    {
        if ($value !== null && strlen((string) $value) > $length) {
            $this->errors[$field] = ucfirst($field) . ' must be at most ' . $length . ' characters.';
        }

        return $this;
    }

    public function matches(string $field, $value, $otherValue, string $otherField): self
    {
        if ($value !== null && $value !== $otherValue) {
            $this->errors[$field] = ucfirst($field) . ' must match ' . $otherField . '.';
        }

        return $this;
    }

    public function numeric(string $field, $value): self
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a number.';
        }

        return $this;
    }

    public function integer(string $field, $value): self
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = ucfirst($field) . ' must be an integer.';
        }

        return $this;
    }

    public function in(string $field, $value, array $allowed): self
    {
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = ucfirst($field) . ' must be one of: ' . implode(', ', $allowed) . '.';
        }

        return $this;
    }

    public function uuid(string $field, $value): self
    {
        if ($value !== null && $value !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid UUID.';
        }
        return $this;
    }

    public function phone(string $field, $value): self
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim((string)$value));
        if ($value !== null && $value !== '' && !preg_match('/^\+?[0-9]{8,15}$/', (string)$normalized)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid phone number.';
        }
        return $this;
    }

    public function identifier(string $field, $value): self
    {
        if ($value === null || $value === '') return $this;
        $normalized = preg_replace('/[\s().-]+/', '', trim((string)$value));
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !preg_match('/^\+?[0-9]{8,15}$/', (string)$normalized)) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid email address or phone number.';
        }
        return $this;
    }

    public function date(string $field, $value): self
    {
        if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid date.';
        }
        return $this;
    }

    public function url(string $field, $value): self
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->errors[$field] = ucfirst($field) . ' must be a valid URL.';
        }
        return $this;
    }

    public function add(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }
}
