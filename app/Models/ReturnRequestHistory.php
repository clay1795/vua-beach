<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestHistory extends Model
{
    protected $fillable = ['return_request_id', 'status', 'source', 'changed_by_user_id', 'note'];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
