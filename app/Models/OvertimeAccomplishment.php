<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeAccomplishment extends Model
{
    protected $table = 'tb_overtime_accomplishment_record';

    protected $fillable = [
        'RequestId',
        'Task',
        'Category',
        'TaskStatus',
        'DateSubmitted'
    ];
}