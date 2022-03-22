<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'st_act_id',
        'promo_id',
        'amount',
        'total_amount',
        'status',
        'payment_proof',
        'payment_method',
        'payment_date'
    ];

    public function student_activities()
    {
        return $this->belongsTo(StudentActivities::class, 'st_act_id', 'id');
    }
}
