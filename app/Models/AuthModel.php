<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 09/11/2018
 * Time: 8:28
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class AuthModel extends Model
{
    protected $table = 'bear_tokens';
    public $timestamps = false;

}