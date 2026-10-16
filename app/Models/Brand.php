<?php

namespace App\Models;

use App\Enums\BrandStatus;
use App\Models\Scopes\BrandScope;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'palette',
        'website_url',
        'status',
        'plan_id',
        'requires_client_approval',
        'onboarding_proposal',
    ];

    protected function casts(): array
    {
        return [
            'palette' => 'array',
            'status' => BrandStatus::class,
            'requires_client_approval' => 'boolean',
            'onboarding_proposal' => 'array',
            'onboarded_at' => 'datetime',
        ];
    }

    /**
     * Marca activa del contexto actual (middleware o job).
     */
    public static function current(): static
    {
        if (! app()->bound('brand')) {
            throw new RuntimeException('No hay marca activa en el contexto actual.');
        }

        return app('brand');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(BrandProfile::class);
    }

    public function strategies(): HasMany
    {
        return $this->hasMany(NetworkStrategy::class);
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(ContentPillar::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function monthlyPlans(): HasMany
    {
        return $this->hasMany(MonthlyPlan::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(BrandContact::class);
    }

    public function reviewRequests(): HasMany
    {
        return $this->hasMany(ReviewRequest::class);
    }

    public function guidelines(): HasMany
    {
        return $this->hasMany(BrandGuideline::class);
    }

    public function usage(): HasMany
    {
        // La marca ya viene dada por la relación: el scope global no debe
        // volver a filtrar por la marca activa del contenedor.
        return $this->hasMany(BrandUsage::class)
            ->withoutGlobalScope(BrandScope::class);
    }

    /**
     * Fila de consumo del mes de contenido (se crea si no existe).
     */
    public function usageFor(int $year, int $month): BrandUsage
    {
        return $this->usage()->firstOrCreate(['year' => $year, 'month' => $month]);
    }
}
