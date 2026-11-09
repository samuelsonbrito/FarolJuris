<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria uma conta já com e-mail confirmado. É o caminho para criar contas
 * quando o cadastro pelo site está fechado (APP_CADASTRO_ABERTO=false).
 */
#[Signature('usuario:criar
    {email : E-mail da nova conta}
    {--nome= : Nome (padrão: a parte do e-mail antes do @)}
    {--senha= : Senha (sem a opção, é pedida sem aparecer na tela)}
    {--admin : Também dá acesso à área administrativa}')]
#[Description('Cria uma conta (e-mail já confirmado), opcionalmente administradora')]
class CriarUsuario extends Command
{
    public function handle(Rastreador $rastreador): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $nome = trim((string) ($this->option('nome') ?: strstr($email, '@', true)));
        $senha = (string) ($this->option('senha') ?? $this->secret('Senha da nova conta'));

        $validador = Validator::make(
            ['email' => $email, 'nome' => $nome, 'senha' => $senha],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'nome' => ['required', 'string', 'max:255'],
                'senha' => ['required', Password::defaults()],
            ],
            ['email.unique' => 'Já existe uma conta com esse e-mail.'],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->components->error($erro);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $nome, 'email' => $email, 'password' => $senha]);
        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => (bool) $this->option('admin'),
        ])->save();

        $rastreador->evento(TipoEvento::USUARIO_CADASTRADO, ['via' => 'comando'], $user);

        if ($user->is_admin) {
            $rastreador->evento(TipoEvento::ADMIN_PROMOVIDO, ['via' => 'comando'], $user);
        }

        $this->components->info("Conta criada: {$user->email}".($user->is_admin ? ' (administradora)' : '').'. Já pode entrar.');

        return self::SUCCESS;
    }
}
