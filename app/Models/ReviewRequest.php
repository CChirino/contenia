<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use App\Models\Concerns\BelongsToBrand;
use Database\Factories\ReviewRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReviewRequest extends Model
{
    /** @use HasFactory<ReviewRequestFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'from_date',
        'to_date',
        'token_hash',
        'expires_at',
        'sent_to',
        'submitted_at',
        'revoked_at',
        'general_comment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'expires_at' => 'datetime',
            'sent_to' => 'array',
            'submitted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    public function weekComments(): HasMany
    {
        return $this->hasMany(ReviewWeekComment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Solicitudes abiertas: no enviadas por el cliente, no revocadas,
     * no vencidas.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereNull('submitted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->submitted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * @return array{approved: int, rejected: int, pending: int}
     */
    public function summary(): array
    {
        $counts = $this->items()
            ->selectRaw('decision, count(*) as n')
            ->groupBy('decision')
            ->pluck('n', 'decision');

        return [
            'approved' => (int) ($counts[ReviewDecision::Approved->value] ?? 0),
            'rejected' => (int) ($counts[ReviewDecision::Rejected->value] ?? 0),
            'pending' => (int) ($counts[ReviewDecision::Pending->value] ?? 0),
        ];
    }

    /**
     * Estado derivado para la UI.
     */
    public function state(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }
        if ($this->submitted_at !== null) {
            return 'submitted';
        }
        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        return 'open';
    }

    /**
     * Genera un token en claro (se devuelve solo una vez) y guarda su
     * hash sha256 en la solicitud.
     */
    public function generateToken(): string
    {
        $token = Str::random(40);
        $this->token_hash = hash('sha256', $token);

        return $token;
    }

    /**
     * Busca la solicitud por token en claro. El portal es público, así que
     * se ignora el scope de marca y luego se fija la marca del token.
     */
    public static function findByToken(string $token): ?static
    {
        $request = static::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($request !== null) {
            app()->instance('brand', $request->brand);
        }

        return $request;
    }
}
