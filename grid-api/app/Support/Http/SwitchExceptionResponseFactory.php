<?php

namespace App\Support\Http;

use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SwitchExceptionResponseFactory
{
    /** @var list<string> */
    private const VALIDATION_RULES = [
        'enum',
        'format',
        'invalid',
        'maxItems',
        'maxLength',
        'maximum',
        'minItems',
        'minLength',
        'minimum',
        'pattern',
        'required',
        'type',
        'unique',
    ];

    public function make(SwitchRequestException $exception, Request $request): JsonResponse
    {
        if (in_array($exception->statusCode, [400, 409, 422], true)) {
            $validation = $this->validationResponse($exception->payload, $request);

            if ($validation !== null) {
                return ApiResponse::error(
                    $validation['message'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    [
                        'code' => $validation['code'],
                        'errors' => $validation['errors'],
                    ],
                );
            }

            return ApiResponse::error(
                'Switch rejected the submitted configuration.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'switch_configuration_rejected'],
            );
        }

        if ($exception->statusCode === Response::HTTP_NOT_FOUND) {
            return ApiResponse::error(
                'The Switch resource is no longer available. Synchronize and try again.',
                Response::HTTP_CONFLICT,
            );
        }

        return ApiResponse::error(
            'Switch is unavailable. Try again later.',
            Response::HTTP_BAD_GATEWAY,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, code: string, errors: array<string, list<string>>}|null
     */
    private function validationResponse(array $payload, Request $request): ?array
    {
        $details = $this->validationDetails($payload['data'] ?? null);

        if ($details === []) {
            return null;
        }

        $errors = [];
        $code = 'switch_validation_failed';

        foreach ($details as $detail) {
            $field = $this->publicField($detail['field'], $request);
            $message = $this->safeMessage($detail, $field, $request);

            if ($detail['field'] === 'numbers' && $detail['rule'] === 'unique') {
                $code = 'callflow_number_conflict';
            }

            if (! in_array($message, $errors[$field] ?? [], true)) {
                $errors[$field][] = $message;
            }
        }

        $messages = array_values(array_unique(array_merge(...array_values($errors))));

        return [
            'message' => count($messages) === 1
                ? $messages[0]
                : 'Switch rejected one or more submitted values.',
            'code' => $code,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array{field: string, rule: string, upstream_message: string|null}>
     */
    private function validationDetails(mixed $value, ?string $field = null): array
    {
        if (! is_array($value)) {
            return [];
        }

        $details = [];

        foreach ($value as $key => $child) {
            if (! is_string($key)) {
                continue;
            }

            if ($field !== null && in_array($key, self::VALIDATION_RULES, true)) {
                $details[] = [
                    'field' => $field,
                    'rule' => $key,
                    'upstream_message' => is_array($child) && is_string($child['message'] ?? null)
                        ? $child['message']
                        : null,
                ];

                continue;
            }

            $details = array_merge(
                $details,
                $this->validationDetails($child, $field ?? $key),
            );
        }

        return $details;
    }

    private function publicField(string $switchField, Request $request): string
    {
        if ($switchField === 'numbers' && $request->is('api/*/callflows*')) {
            return $this->callflowNumberField($request);
        }

        if ($request->exists($switchField)) {
            return $switchField;
        }

        if ($switchField === 'enabled' && $request->exists('is_enabled')) {
            return 'is_enabled';
        }

        return 'configuration';
    }

    private function callflowNumberField(Request $request): string
    {
        if ($request->filled('extension_numbers')) {
            return 'extension_numbers';
        }

        if ($request->filled('phone_number_ids')) {
            return 'phone_number_ids';
        }

        return 'entry_points';
    }

    /**
     * @param  array{field: string, rule: string, upstream_message: string|null}  $detail
     */
    private function safeMessage(array $detail, string $publicField, Request $request): string
    {
        if ($detail['field'] === 'numbers' && $detail['rule'] === 'unique') {
            $number = $this->conflictingCallflowNumber($detail['upstream_message'], $request);

            return $number === null
                ? 'One or more selected numbers are already assigned to another callflow.'
                : "Extension {$number} is already assigned to another callflow.";
        }

        $label = $this->fieldLabel($publicField);

        return match ($detail['rule']) {
            'required' => "Switch requires a value for {$label}.",
            'unique' => "The selected {$label} is already in use.",
            'enum' => "The selected {$label} is not supported by Switch.",
            'format', 'pattern' => "The {$label} has an invalid format.",
            'minItems', 'maxItems', 'minLength', 'maxLength', 'minimum', 'maximum' => "The {$label} is outside the range accepted by Switch.",
            default => "Switch rejected the submitted {$label}.",
        };
    }

    private function conflictingCallflowNumber(?string $message, Request $request): ?string
    {
        if ($message === null
            || preg_match('/^([+*#0-9]{2,32})\s+exists in callflow\b/i', $message, $matches) !== 1) {
            return null;
        }

        $number = $matches[1];
        $extensionNumbers = $request->input('extension_numbers', []);

        if (! is_array($extensionNumbers)) {
            return null;
        }

        $submittedNumbers = array_filter(
            $extensionNumbers,
            fn (mixed $value): bool => is_string($value),
        );

        return in_array($number, $submittedNumbers, true) ? $number : null;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'extension_numbers' => 'extension number',
            'phone_number_ids' => 'phone number',
            'is_enabled' => 'enabled setting',
            'mac_address' => 'MAC address',
            default => str_replace('_', ' ', $field),
        };
    }
}
