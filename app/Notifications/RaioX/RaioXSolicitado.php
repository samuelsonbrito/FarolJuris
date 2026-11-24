<?php

namespace App\Notifications\RaioX;

use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Para os administradores: há um Raio-X esperando geração. */
class RaioXSolicitado extends Notification implements ShouldQueue
{
    use Queueable;

    /** Pela fila: uma falha de e-mail (SMTP) não pode derrubar o pedido nem a liberação. */
    public int $tries = 3;

    public function __construct(public readonly RelatorioRaioX $relatorio) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $r = $this->relatorio;

        return (new MailMessage)
            ->subject("Novo pedido de Raio-X ({$r->descricaoOab()})")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$r->user->name} pediu um Raio-X da {$r->descricaoOab()}.")
            ->line('Confira se a OAB é mesmo de quem pediu antes de gerar.')
            ->action('Abrir pedidos de Raio-X', route('admin.raiox.index'));
    }
}
