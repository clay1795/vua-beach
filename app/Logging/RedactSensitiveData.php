<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use JsonSerializable;
use Monolog\LogRecord;
use Stringable;
use Throwable;

class RedactSensitiveData
{
    private const REDACTED = '[REDACTED]';

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        ));
    }

    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            $values[$key] = $this->sanitizeValue($value);
        }

        return $values;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }
        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $this->sanitizeString($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }
        if ($value instanceof JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize());
        }
        if ($value instanceof Stringable) {
            return $this->sanitizeString((string) $value);
        }
        if (is_object($value)) {
            return '[OBJECT '.str_replace("\0", '', $value::class).']';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower(str_replace(['-', '.'], '_', $key));

        return preg_match('/(?:password|passwd|passphrase|secret|authorization|cookie|csrf|secure_?hash|signature|token|api_?key|private_?key|card_?number|pan|cvv|cvc|transaction_?(?:id|no)|trans_?id|request_?id)/', $key) === 1;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=\-]+/i', '$1'.self::REDACTED, $value) ?? $value;
        $value = preg_replace(
            '/([?&](?:token|secret|signature|secure_hash|api_key|access_token|refresh_token)=)[^&\s]+/i',
            '$1'.self::REDACTED,
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/((?:password|passwd|passphrase|secret|authorization|api_key|token|access_token|refresh_token)\s*[:=]\s*)[^\s,;}]+/i',
            '$1'.self::REDACTED,
            $value,
        ) ?? $value;

        return preg_replace_callback('/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/', function (array $match): string {
            $digits = preg_replace('/\D/', '', $match[0]);

            return $digits !== null && $this->passesLuhn($digits) ? self::REDACTED : $match[0];
        }, $value) ?? $value;
    }

    private function passesLuhn(string $digits): bool
    {
        if (strlen($digits) < 13 || strlen($digits) > 19 || preg_match('/^(\d)\1+$/', $digits)) {
            return false;
        }

        $sum = 0;
        $parity = strlen($digits) % 2;
        foreach (str_split($digits) as $index => $digit) {
            $number = (int) $digit;
            if ($index % 2 === $parity) {
                $number *= 2;
                $number = $number > 9 ? $number - 9 : $number;
            }
            $sum += $number;
        }

        return $sum % 10 === 0;
    }
}
