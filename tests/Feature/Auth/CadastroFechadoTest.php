<?php

use App\Models\Atividade;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Support\Rastreio\TipoEvento;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.cadastro_aberto' => false]);
    $this->app->instance(ComunicacaoGateway::class, new FakeComunicacaoGateway);
});

it('não mostra a tela de cadastro', function () {
    $this->get(route('register'))->assertNotFound();
});

it('recusa o envio do formulário de cadastro', function () {
    $this->post(route('register.store'), [
        'name' => 'Intrusa', 'email' => 'intrusa@exemplo.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertForbidden();

    $this->assertGuest();
    expect(User::where('email', 'intrusa@exemplo.com')->exists())->toBeFalse();
});

it('avisa o front para esconder "Criar conta"', function () {
    $this->get(route('consulta.index'))->assertInertia(fn (Assert $p) => $p->where('cadastroAberto', false));
    $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('cadastroAberto', false));

    config(['app.cadastro_aberto' => true]);
    $this->get(route('consulta.index'))->assertInertia(fn (Assert $p) => $p->where('cadastroAberto', true));
});

it('continua deixando entrar quem já tem conta', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('monitoramento.index', absolute: false));
    $this->assertAuthenticatedAs($user);
});

it('cria contas pelo comando, já confirmadas e opcionalmente admin', function () {
    $this->artisan('usuario:criar', ['email' => 'Ana@Exemplo.com', '--nome' => 'Ana Lima', '--senha' => 'segredo-forte-123', '--admin' => true])
        ->assertSuccessful();

    $ana = User::where('email', 'ana@exemplo.com')->sole();

    expect($ana->name)->toBe('Ana Lima')
        ->and($ana->is_admin)->toBeTrue()
        ->and($ana->email_verified_at)->not->toBeNull()
        ->and(Atividade::where('user_id', $ana->id)->pluck('tipo')->all())
        ->toBe([TipoEvento::USUARIO_CADASTRADO, TipoEvento::ADMIN_PROMOVIDO]);

    $this->post(route('login.store'), ['email' => 'ana@exemplo.com', 'password' => 'segredo-forte-123']);
    $this->assertAuthenticatedAs($ana);
});

it('pede a senha sem mostrar e usa o e-mail como nome padrão', function () {
    $this->artisan('usuario:criar', ['email' => 'bruno@exemplo.com'])
        ->expectsQuestion('Senha da nova conta', 'outra-senha-123')
        ->assertSuccessful();

    expect(User::where('email', 'bruno@exemplo.com')->sole()->only(['name', 'is_admin']))
        ->toBe(['name' => 'bruno', 'is_admin' => false]);
});

it('recusa e-mail repetido ou inválido', function () {
    User::factory()->create(['email' => 'ja@exemplo.com']);

    $this->artisan('usuario:criar', ['email' => 'ja@exemplo.com', '--senha' => 'qualquer-123'])->assertFailed();
    $this->artisan('usuario:criar', ['email' => 'nao-e-email', '--senha' => 'qualquer-123'])->assertFailed();

    expect(User::count())->toBe(1);
});
