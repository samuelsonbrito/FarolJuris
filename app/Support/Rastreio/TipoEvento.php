<?php

namespace App\Support\Rastreio;

/** Tipos de evento da trilha de atividade (activity_logs.tipo) e seus rótulos. */
final class TipoEvento
{
    public const PAGINA_VISTA = 'pagina.vista';

    public const USUARIO_CADASTRADO = 'usuario.cadastrado';

    public const USUARIO_EMAIL_VERIFICADO = 'usuario.email_verificado';

    public const USUARIO_LOGIN = 'usuario.login';

    public const USUARIO_LOGIN_FALHOU = 'usuario.login_falhou';

    public const USUARIO_LOGOUT = 'usuario.logout';

    public const USUARIO_SENHA_REDEFINIDA = 'usuario.senha_redefinida';

    public const CONTA_EXCLUIDA = 'conta.excluida';

    public const RAIOX_SOLICITADO = 'raiox.solicitado';

    public const RAIOX_GERADO = 'raiox.gerado';

    public const RAIOX_LIBERADO = 'raiox.liberado';

    public const ALVO_CRIADO = 'alvo.criado';

    public const ALVO_PAUSADO = 'alvo.pausado';

    public const ALVO_RETOMADO = 'alvo.retomado';

    public const ALVO_EXCLUIDO = 'alvo.excluido';

    public const ALVO_VERIFICACAO_MANUAL = 'alvo.verificacao_manual';

    public const MONITORAMENTO_EXECUTADO = 'monitoramento.executado';

    public const MONITORAMENTO_FALHOU = 'monitoramento.falhou';

    public const NOTIFICACAO_ENVIADA = 'notificacao.enviada';

    public const ALERTA_LIDO = 'alerta.lido';

    public const ALERTA_NAO_LIDO = 'alerta.nao_lido';

    public const ALERTAS_TODOS_LIDOS = 'alertas.todos_lidos';

    public const NOTIFICACOES_ATUALIZADAS = 'notificacoes.atualizadas';

    public const DADOS_EXCLUIDOS = 'dados.excluidos';

    public const EXPORTACAO_CRIADA = 'exportacao.criada';

    public const EXPORTACAO_CONCLUIDA = 'exportacao.concluida';

    public const EXPORTACAO_FALHOU = 'exportacao.falhou';

    public const EXPORTACAO_BAIXADA = 'exportacao.baixada';

    public const EXPORTACAO_EXCLUIDA = 'exportacao.excluida';

    public const ADMIN_PROMOVIDO = 'admin.promovido';

    public const ADMIN_REMOVIDO = 'admin.removido';

    /** @var array<string, string> */
    public const ROTULOS = [
        self::PAGINA_VISTA => 'Página vista',
        self::USUARIO_CADASTRADO => 'Cadastro',
        self::USUARIO_EMAIL_VERIFICADO => 'E-mail confirmado',
        self::USUARIO_LOGIN => 'Login',
        self::USUARIO_LOGIN_FALHOU => 'Login falhou',
        self::USUARIO_LOGOUT => 'Logout',
        self::USUARIO_SENHA_REDEFINIDA => 'Senha redefinida',
        self::CONTA_EXCLUIDA => 'Conta excluída',
        self::ALVO_CRIADO => 'Alvo criado',
        self::ALVO_PAUSADO => 'Alvo pausado',
        self::ALVO_RETOMADO => 'Alvo retomado',
        self::ALVO_EXCLUIDO => 'Alvo excluído',
        self::ALVO_VERIFICACAO_MANUAL => 'Verificação manual',
        self::MONITORAMENTO_EXECUTADO => 'Monitoramento executado',
        self::MONITORAMENTO_FALHOU => 'Monitoramento falhou',
        self::NOTIFICACAO_ENVIADA => 'Notificação enviada',
        self::ALERTA_LIDO => 'Alerta lido',
        self::ALERTA_NAO_LIDO => 'Alerta marcado como não lido',
        self::ALERTAS_TODOS_LIDOS => 'Todos os alertas lidos',
        self::NOTIFICACOES_ATUALIZADAS => 'Preferências de notificação',
        self::DADOS_EXCLUIDOS => 'Dados de monitoramento excluídos',
        self::EXPORTACAO_CRIADA => 'Exportação criada',
        self::EXPORTACAO_CONCLUIDA => 'Exportação concluída',
        self::EXPORTACAO_FALHOU => 'Exportação falhou',
        self::EXPORTACAO_BAIXADA => 'Exportação baixada',
        self::EXPORTACAO_EXCLUIDA => 'Exportação excluída',
        self::RAIOX_SOLICITADO => 'Raio-X solicitado',
        self::RAIOX_GERADO => 'Raio-X gerado',
        self::RAIOX_LIBERADO => 'Raio-X liberado',
        self::ADMIN_PROMOVIDO => 'Acesso de administrador concedido',
        self::ADMIN_REMOVIDO => 'Acesso de administrador removido',
    ];

    public static function rotulo(string $tipo): string
    {
        return self::ROTULOS[$tipo] ?? $tipo;
    }
}
