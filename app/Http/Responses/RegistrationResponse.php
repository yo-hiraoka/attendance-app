<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;

class RegistrationResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        return redirect()->route('verification.notice');
    }
}
