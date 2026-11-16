<?php

const ADSENSE = 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1687918466354935';

it('carrega o script do AdSense em produção', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('consulta.index'))->assertOk()->assertSee(ADSENSE, false);
});

it('não carrega o AdSense fora de produção', function () {
    $this->get(route('consulta.index'))->assertOk()->assertDontSee('adsbygoogle', false);
});

it('não carrega o AdSense quando o client está vazio', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['services.adsense.client' => '']);

    $this->get(route('consulta.index'))->assertOk()->assertDontSee('adsbygoogle', false);
});

it('publica o ads.txt com o id do editor', function () {
    expect(file_get_contents(public_path('ads.txt')))->toContain('google.com, pub-1687918466354935, DIRECT');
});
