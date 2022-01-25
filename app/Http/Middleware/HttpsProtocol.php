<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 21/11/2018
 * Time: 15:30
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class HttpsProtocol
{
    public function handle($request, Closure $next)
    {
        if (!$request->secure() && App::environment() === 'production'){
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}