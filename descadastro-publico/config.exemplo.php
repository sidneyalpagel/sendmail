<?php
/**
 * Configuração do endpoint público de descadastro.
 *
 *   cp config.exemplo.php config.php && chmod 600 config.php
 *
 * Este arquivo fica em um servidor exposto à internet. Por isso o usuário do
 * banco aqui deve ser SEPARADO do usuário da aplicação, com permissão apenas
 * para ler e marcar contatos — nunca com acesso a campanhas ou operadores.
 * O README traz o GRANT exato.
 */

return [

    'banco' => [
        'host'    => '192.168.0.91',
        'porta'   => 3306,
        'nome'    => 'sendmail',
        'usuario' => 'sendmail_optout',
        'senha'   => 'TROQUE_ESTA_SENHA',
    ],

    // Precisa ser EXATAMENTE a mesma chave de app.chave no config.php da
    // aplicação. É ela que valida a assinatura dos links; se as duas
    // divergirem, todo link de descadastro passa a ser recusado.
    'chave' => 'A_MESMA_CHAVE_DA_APLICACAO',

    'orgao'         => 'Prefeitura Municipal de Santa Helena',
    'email_suporte' => 'ti@santahelena.pr.gov.br',
    'fuso'          => 'America/Sao_Paulo',
];
