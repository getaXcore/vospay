<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class SysCutoff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        date_default_timezone_set('Asia/Jakarta');

        $dataCuttoff = DB::table('omf_vospay_cutoff')
            ->select('cutoff_start','cutoff_end')
            ->orderBy('cutoff_id','desc')
            ->first();

        if (!empty($dataCuttoff)){
            $start = $dataCuttoff->cutoff_start;
            $end = $dataCuttoff->cutoff_end;
            $now = date('Y-m-d H:i:s');

            if ($now < $end || $now == $end || $now == $start){
                return response()->json(['error' => "The system is temporarily unavailable",'statusCode' => 503],503);
            }
        }

        return $next($request);
    }
}
