<?php

namespace App\Policies;

use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;

class RelatorioRaioXPolicy
{
    /** O dono vê o relatório depois de liberado; o admin vê sempre. */
    public function view(User $user, RelatorioRaioX $relatorio): bool
    {
        return (bool) $user->is_admin || ($relatorio->user_id === $user->id && $relatorio->liberado());
    }

    /** Ajustar o custo do escritório (calculadora). */
    public function update(User $user, RelatorioRaioX $relatorio): bool
    {
        return $this->view($user, $relatorio);
    }
}
