<?php

declare(strict_types=1);

namespace app\services;

class ImportValidationException extends ImportException
{
    /**
     * @param array<int, array{row: int, message: string}> $rowErrors
     */
    public function __construct(string $message, private readonly array $rowErrors = [])
    {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{row: int, message: string}>
     */
    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }
}
