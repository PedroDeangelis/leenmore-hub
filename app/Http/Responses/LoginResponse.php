<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Everyone lands on the API portal — it is the only destination here.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return redirect()->intended(route('api.tokens'));
    }
}
