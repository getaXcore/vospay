<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 14/11/2018
 * Time: 11:39
 */

namespace App\Http\Utilities;


class MyUtil
{
    public $defaultId;
    public $format;
    public $char;
    public $length;

    public function __construct()
    {
        $this->length = 10;
        $this->char = 0;
        $this->defaultId = 1;
        $this->format = "%08s";
    }

    public function customId(){
        return sprintf($this->format,$this->defaultId);
    }

}