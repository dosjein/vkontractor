<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Processor extends Model
{

    // 0- not responded
    // 1- sent response
    // 2- prepare response
    // 5- banned or privacy issues

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'processor';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    
}
