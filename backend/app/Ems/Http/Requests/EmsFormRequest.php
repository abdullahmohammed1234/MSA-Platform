<?php

namespace App\Ems\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for EMS form requests.
 *
 * Authorization is handled by policies at the controller, so requests return
 * true here and stay focused on validation. The shared helpers below keep
 * boolean and nullable-string handling consistent across every EMS payload.
 */
abstract class EmsFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Turn empty strings into nulls so an untouched optional field clears the
     * column rather than storing "".
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function nullifyBlanks(array $keys): array
    {
        $replacements = [];

        foreach ($keys as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $replacements[$key] = null;
            }
        }

        return $replacements;
    }

    /**
     * Coerce query-string boolean flags into real booleans.
     *
     * Axios and browsers send `?upcoming=true` as the string "true", which
     * Laravel's `boolean` rule rejects (it only accepts true/false/0/1/"0"/"1").
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function coerceBooleanFlags(array $keys): array
    {
        $replacements = [];

        foreach ($keys as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if (is_bool($value) || $value === null || $value === '') {
                continue;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                    $replacements[$key] = true;
                } elseif (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
                    $replacements[$key] = false;
                }
            } elseif (is_int($value) && in_array($value, [0, 1], true)) {
                $replacements[$key] = (bool) $value;
            }
        }

        return $replacements;
    }
}
