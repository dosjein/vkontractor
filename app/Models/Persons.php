<?php

namespace App\Model;

use Clusterpoint\Model;

//App\Model\Persons

class Persons extends Model
{
    protected $db = env('CP_PERSONS', 'database.persons'); // set your databse and collection names
    //protected $primaryKey = "custom_id"; // If you want to define specific specific primary key, default = _id
}