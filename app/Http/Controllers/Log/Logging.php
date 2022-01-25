<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 12/11/2018
 * Time: 14:56
 */

namespace App\Http\Controllers\Log;


use App\Http\Controllers\Controller;

class Logging extends Controller
{
    public $pathTo = "/var/www/wordpress/bca/myApis/logs/";
    public $timestamp;

    public function __construct(){
        date_default_timezone_set('Asia/Jakarta');

        $this->timestamp = date('Y-m-d H:i:s.B',time());
    }

    public function write($header_request,$header_response,$request,$response,$filename,$response_timestamp){

        $custom = "[".$this->timestamp."] ".$header_request." => ";
        $custom.= $request."\n";
        $custom.= "[".$response_timestamp."] ".$header_response." => ";
        $custom.= $response."\n";

        return file_put_contents($this->pathTo.$filename,$custom,FILE_APPEND);

    }

    public function writeln($timestamp,$header,$content,$filename){
        $custom = "[".$timestamp."] ".$header." => ".$content."\n";

        return file_put_contents($this->pathTo.$filename,$custom,FILE_APPEND);
    }

}