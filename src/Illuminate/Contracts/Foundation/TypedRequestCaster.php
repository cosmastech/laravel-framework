<?php

namespace Illuminate\Contracts\Foundation;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @template T
 */
interface TypedRequestCaster
{
    /**
     * The validation rule(s) to infer for this type.
     *
     * @param string  $param The request parameter name
     * @return string|array|\Illuminate\Contracts\Validation\Rule|null
     */
    public function rules(string $param);

    /**
     * Cast the validated value into the target type.
     *
     * @param  class-string<T>  $type
     * @return T
     */
    public function cast(string $param, mixed $value, string $type, Request $request): mixed;
}
