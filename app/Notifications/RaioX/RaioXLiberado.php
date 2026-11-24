<?php

namespace App\Notifications\RaioX;

use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Para o dono: o Raio-X foi conferido e está disponível. */
class RaioXLiberado extends Notification
{
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
            ->subject("Seu Raio-X está pronto ({$r->descricaoOab()})")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O Raio-X de desempenho da {$r->descricaoOab()} está disponível.")
            ->action('Ver o Raio-X', route('raiox.show', $r))
            ->line('Os números descrevem o passado e orientam decisões; não garantem resultado em processos futuros.');
    }
}
