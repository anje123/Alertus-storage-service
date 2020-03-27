<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = ['Recording_Sid','Recording_Url','Storage_status'];
}
