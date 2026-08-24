<?php
/**
 * Disparador de e-mails - configuração
 *
 * Copie este arquivo para config.php e ajuste os valores.
 *   cp config.exemplo.php config.php && chmod 600 config.php
 */

return [

    // ---------------------------------------------------------------
    // Banco de dados
    // ---------------------------------------------------------------
    'banco' => [
        'host'   => '192.168.0.91',
        'porta'  => 3306,
        'nome'   => 'sendmail',
        'usuario'=> 'sendmail',
        'senha'  => 'TROQUE_ESTA_SENHA',
    ],

    // ---------------------------------------------------------------
    // Servidor de saída (Zimbra da Prefeitura, com autenticação)
    // ---------------------------------------------------------------
    'smtp' => [
        // O host pode ser trocado pela interface (Ajustes → Servidor de saída);
        // este valor vale enquanto o parâmetro smtp_host estiver vazio.
        'host'      => 'zldapmta.santahelena.pr.gov.br',
        'porta'     => 587,
        'seguranca' => 'tls',   // 'tls' (STARTTLS, porta 587) | 'ssl' (porta 465) | '' (sem criptografia)
        'usuario'   => 'naoresponda@santahelena.pr.gov.br',
        'senha'     => 'TROQUE_ESTA_SENHA',

        // Verificação do certificado do servidor. Deixe true.
        // Só use false se o Zimbra estiver com certificado interno não confiável.
        'verificar_certificado' => true,

        // Remetente exibido ao destinatário
        'remetente_email' => 'naoresponda@santahelena.pr.gov.br',
        'remetente_nome'  => 'Prefeitura de Santa Helena - TIC',

        // Para onde vão as respostas (não pode ser a conta de disparo)
        'responder_para_email' => 'ti@santahelena.pr.gov.br',
        'responder_para_nome'  => 'Departamento de TIC',
    ],

    // ---------------------------------------------------------------
    // Aplicação
    // ---------------------------------------------------------------
    'app' => [
        'nome'      => 'Disparador de e-mails',
        'orgao'     => 'Prefeitura Municipal de Santa Helena',
        'url_base'  => 'https://sendmail.santahelena.pr.gov.br',

        // Endereço PÚBLICO da página de descadastro. É ele que vai no rodapé
        // das mensagens e no cabeçalho List-Unsubscribe.
        //
        // O painel roda na rede interna, mas o cidadão abre o e-mail em casa,
        // no celular. Se o link não resolver de fora, a única saída que resta
        // a ele é o botão "denunciar spam" — que é justamente o que derruba a
        // reputação do domínio.
        //
        // Deixe vazio APENAS se o painel já for alcançável pela internet.
        'url_descadastro' => 'https://comunica.santahelena.pr.gov.br',

        // Usada para assinar os links de descadastro. Gere uma vez e NUNCA mude:
        //   php -r "echo bin2hex(random_bytes(32));"
        'chave'     => 'GERE_UMA_CHAVE_ALEATORIA_AQUI',

        'timeout_sessao' => 3600,
        'itens_por_pagina' => 50,

        // Rodapé institucional aplicado a todos os e-mails
        'rodape' => 'Departamento de Tecnologia da Informação e Comunicação (TIC) — '
                  . 'Prefeitura Municipal de Santa Helena / PR — ramal 8207 — ti@santahelena.pr.gov.br',
    ],

    // ---------------------------------------------------------------
    // Fila
    // ---------------------------------------------------------------
    'fila' => [
        // Teto de segurança. O valor efetivo é o menor entre este e o
        // parâmetro 'envios_por_minuto' ajustável pela interface.
        'limite_por_minuto' => 60,

        // Segundos que o worker fica trabalhando por execução (cron de 1 min).
        'duracao_ciclo' => 50,

        // Minutos de espera antes de reprocessar um endereço que falhou.
        'espera_retentativa' => 10,
    ],
];
