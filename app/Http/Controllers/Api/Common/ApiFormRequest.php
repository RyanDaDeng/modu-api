<?php


namespace App\Http\Controllers\Api\Common;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @author Ryan
 * Class ApiFormRequest
 * @package App\Modules\Core
 */
class ApiFormRequest extends FormRequest
{
    use ApiStructureTrait;

    const INVALID = 'INVALID';

    /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

        $firstError = collect($errors)->values()->first()[0];

        throw new HttpResponseException(
            $this->sendError(
                $firstError,
                [
                    'errors' => $errors
                ],
                self::INVALID,
                JsonResponse::HTTP_BAD_REQUEST
            )
        );
    }
}
