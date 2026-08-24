# Histórico de versões

## 1.0.0 — 24/08/2026

Primeira versão.

- Cadastro de contatos com bairro, importação de CSV com normalização de bairro
  e reconhecimento de duplicidade por e-mail.
- Envio para um contato, para um bairro ou para toda a lista.
- Modelos de mensagem com variáveis substituídas por destinatário.
- Fila persistente com worker por cron, conexão SMTP reaproveitada, cadência
  configurável, pausa global e retentativa com desistência.
- Acompanhamento do envio em tempo real, com reenvio seletivo das falhas.
- Descadastro assinado por HMAC, no rodapé e via `List-Unsubscribe` (RFC 8058).
- Supressão de contatos descadastrados no momento do envio, mesmo que a
  campanha já estivesse liberada.
- Trilha de auditoria e controle de acesso por operador e administrador.
