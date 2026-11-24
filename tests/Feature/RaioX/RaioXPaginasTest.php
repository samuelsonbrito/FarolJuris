<?php

use App\Enums\RaioX\StatusRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Notifications\RaioX\RaioXLiberado;
use App\Notifications\RaioX\RaioXSolicitado;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->app->instance(ComunicacaoGateway::class, new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')));
    config(['raiox.ia.driver' => 'fake', 'raiox.coleta.pausa_ms' => 0]);
});

function relatorioDe(User $user, string $status = 'liberado'): RelatorioRaioX
{
    $r = $user->relatoriosRaioX()->create(['oab' => '54321', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06', 'status' => $status]);
    $r->processos()->create(['numero_processo' => '01234567820268041000', 'candidato' => true, 'resultado' => 'exito', 'instancia' => 'sentenca', 'tema' => 'consignado']);

    return $r;
}

it('exige login', function () {
    $this->get(route('raiox.index'))->assertRedirect(route('login'));
});

it('lista só os relatórios da própria conta', function () {
    relatorioDe($this->user);
    relatorioDe(User::factory()->create());

    $this->actingAs($this->user)->get(route('raiox.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('RaioX/Index')->has('relatorios', 1)->has('ufs', 27));
});

it('o usuário pede um Raio-X e os admins são avisados', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('raiox.store'), ['oab' => ' 54.321 ', 'uf' => 'am'])->assertRedirect();

    $r = $this->user->relatoriosRaioX()->sole();
    expect($r->oab)->toBe('54321')
        ->and($r->uf)->toBe('AM')
        ->and($r->status)->toBe(StatusRaioX::Solicitado)
        ->and($r->inicio->diffInMonths($r->fim))->toEqual(12.0);

    Notification::assertSentTo($this->admin, RaioXSolicitado::class);
    Notification::assertNotSentTo($this->user, RaioXSolicitado::class);
});

it('não aceita pedido duplicado em andamento nem OAB inválida', function () {
    relatorioDe($this->user, 'solicitado');

    $this->actingAs($this->user)->post(route('raiox.store'), ['oab' => '54321', 'uf' => 'AM'])->assertSessionHasErrors('oab');
    $this->actingAs($this->user)->post(route('raiox.store'), ['oab' => 'abc', 'uf' => 'AM'])->assertSessionHasErrors('oab');
    $this->actingAs($this->user)->post(route('raiox.store'), ['oab' => '777', 'uf' => 'XX'])->assertSessionHasErrors('uf');

    expect($this->user->relatoriosRaioX()->count())->toBe(1);
});

it('o dono só vê o relatório depois de liberado; o admin vê antes', function () {
    $r = relatorioDe($this->user, 'pronto');

    $this->actingAs($this->user)->get(route('raiox.show', $r))->assertForbidden();
    $this->actingAs($this->admin)->get(route('raiox.show', $r))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('RaioX/Show')->where('metricas.visaoGeral.decididos', 1));

    $r->update(['status' => StatusRaioX::Liberado]);
    $this->actingAs($this->user)->get(route('raiox.show', $r))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('relatorio.oab', '54321')->missing('relatorio.ia'));
});

it('ninguém vê o relatório de outra conta', function () {
    $r = relatorioDe(User::factory()->create());

    $this->actingAs($this->user)->get(route('raiox.show', $r))->assertForbidden();
    $this->actingAs($this->user)->get(route('raiox.planilha', $r))->assertForbidden();
    $this->actingAs($this->user)->get(route('raiox.pdf', $r))->assertForbidden();
});

it('salva os custos da calculadora', function () {
    $r = relatorioDe($this->user);

    $this->actingAs($this->user)->put(route('raiox.custo', $r), ['custoMensal' => 16000, 'custoVariavel' => null, 'honorarioMedio' => 7000])
        ->assertRedirect();

    expect((float) $r->fresh()->custo_mensal)->toBe(16000.0)
        ->and((float) $r->fresh()->honorario_medio)->toBe(7000.0);

    $this->actingAs($this->user)->put(route('raiox.custo', $r), ['custoMensal' => -5])->assertSessionHasErrors('custoMensal');
});

it('baixa a planilha e o PDF', function () {
    $r = relatorioDe($this->user);

    $this->actingAs($this->user)->get(route('raiox.planilha', $r))
        ->assertOk()
        ->assertDownload('raio-x-oab-54321-AM-2026-11-06.xlsx');

    $pdf = $this->actingAs($this->user)->get(route('raiox.pdf', $r))->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

it('a área do admin é só para admin', function () {
    $r = relatorioDe($this->user, 'solicitado');

    $this->actingAs($this->user)->get(route('admin.raiox.index'))->assertForbidden();
    $this->actingAs($this->user)->post(route('admin.raiox.gerar', $r))->assertForbidden();
    $this->actingAs($this->user)->post(route('admin.raiox.liberar', $r))->assertForbidden();
});

it('o admin cria um Raio-X para uma conta pelo e-mail', function () {
    $this->actingAs($this->admin)->post(route('admin.raiox.store'), ['email' => $this->user->email, 'oab' => '54321', 'uf' => 'AM'])
        ->assertRedirect();

    expect($this->user->relatoriosRaioX()->count())->toBe(1);
});

it('o admin gera, confere e libera; o dono é avisado', function () {
    Notification::fake();
    config(['queue.default' => 'sync']);
    $r = relatorioDe($this->user, 'solicitado');

    $this->actingAs($this->admin)->get(route('admin.raiox.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/RaioX/Index')->has('relatorios', 1)->where('relatorios.0.usuario.email', $this->user->email));

    $this->actingAs($this->admin)->post(route('admin.raiox.gerar', $r))->assertRedirect();
    $r->refresh();
    expect($r->status)->toBeIn([StatusRaioX::Conferencia, StatusRaioX::Pronto]);

    // Força um caso ambíguo para conferir.
    $processo = $r->processos()->first();
    $processo->update(['precisa_conferencia' => true]);
    $r->update(['status' => StatusRaioX::Conferencia]);
    $r->processos()->whereKeyNot($processo->id)->update(['precisa_conferencia' => false]);

    $this->actingAs($this->admin)->post(route('admin.raiox.liberar', $r))->assertSessionHasErrors('relatorio');

    $this->actingAs($this->admin)->get(route('admin.raiox.conferencia', $r))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/RaioX/Conferencia')->has('processos', 1)->has('opcoes.temas', 16));

    $this->actingAs($this->admin)->patch(route('admin.raiox.conferir', $processo), [
        'resultado' => 'exito_parcial', 'instancia' => 'sentenca', 'tema' => 'consignado', 'grupoOrigem' => 'civel_capital',
        'varaOrigem' => '22ª Vara Cível', 'parteContraria' => 'Bradesco', 'poloCliente' => 'ativo', 'extincaoSemMerito' => false,
    ])->assertRedirect();

    $processo->refresh();
    expect($processo->precisa_conferencia)->toBeFalse()
        ->and($processo->resultado->value)->toBe('exito_parcial')
        ->and($processo->conferido_por)->toBe($this->admin->id)
        ->and($r->fresh()->status)->toBe(StatusRaioX::Pronto);

    $this->actingAs($this->admin)->post(route('admin.raiox.liberar', $r))->assertRedirect();

    expect($r->fresh()->status)->toBe(StatusRaioX::Liberado)
        ->and($r->fresh()->liberado_por)->toBe($this->admin->id);
    Notification::assertSentTo($this->user, RaioXLiberado::class);
});

it('não gera de novo um Raio-X que está em processamento', function () {
    Queue::fake();
    $r = relatorioDe($this->user, 'processando');

    $this->actingAs($this->admin)->post(route('admin.raiox.gerar', $r))->assertSessionHasErrors('relatorio');
    Queue::assertNothingPushed();
});
