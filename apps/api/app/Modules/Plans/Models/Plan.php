<?php
namespace App\Modules\Plans\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'audience', 'status', 'description'];

    public function prices(): HasMany { return $this->hasMany(PlanPrice::class); }
    public function features(): HasMany { return $this->hasMany(PlanFeature::class); }
}
