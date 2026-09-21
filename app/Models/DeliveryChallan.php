<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryChallan extends Model
{
    protected $fillable = ['challan_number', 'invoice_id', 'challan_date'];
    protected function casts(): array { return ['challan_date' => 'date']; }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
