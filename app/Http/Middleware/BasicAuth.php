<?php

namespace App\Http\Middleware;

use Closure;

class BasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    private $username = 'taufan';
    private $password = 'septaufani';

    public function handle($request, Closure $next)
    {
        $AUTH_USER = $request->getUser();
        $AUTH_PASS = $request->getPassword();

        header('Cache-Control: no-cache, must-revalidate, max-age=0');

        if (isset($AUTH_USER) && isset($AUTH_PASS)){
            if ($AUTH_USER != $this->username || $AUTH_PASS != $this->password){
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
