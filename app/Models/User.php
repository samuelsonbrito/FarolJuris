<?php

namespace App\Models;

use App\Models\RaioX\RelatorioRaioX;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $whatsapp
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** Igual ao default do banco, para valer também em modelos recém-criados. */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected static function booted(): void
    {
        // A FK apaga as exportações em cascata no banco, sem eventos Eloquent;
        // apagando antes por Eloquent, os arquivos também saem do disco (LGPD).
        static::deleting(function (self $user): void {
            $user->exportacoes()->each(fn (Exportacao $e) => $e->delete());
        });
    }

    /** @return HasMany<MonitoringTarget, $this> */
    public function monitoringTargets(): HasMany
    {
        return $this->hasMany(MonitoringTarget::class);
    }

    /** @return HasMany<Exportacao, $this> */
    public function exportacoes(): HasMany
    {
        return $this->hasMany(Exportacao::class);
    }

    /** @return HasMany<RelatorioRaioX, $this> */
    public function relatoriosRaioX(): HasMany
    {
        return $this->hasMany(RelatorioRaioX::class);
    }

    /** Destino do canal App\Notifications\Channels\WhatsAppChannel. */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }
}
