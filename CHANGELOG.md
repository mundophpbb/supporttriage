# Changelog

# Changelog

## [3.4.2] - 24/03/2026

### Corrigido
- Corrigida a geração do rascunho de KB, que em alguns casos estava saindo genérico demais ao marcar um tópico como resolvido.
- Corrigida a extração da descrição original do problema a partir da primeira mensagem do tópico.
- Corrigida a captura dos sintomas confirmados com base no relatório técnico e em blocos de erro/código.
- Corrigida a montagem da solução aplicada, para aproveitar melhor a resposta resolutiva em vez de manter texto vazio ou genérico.
- Corrigido o caso comum relacionado à pasta `install` ainda presente após a instalação do phpBB.

### Melhorado
- Melhorada a lógica de criação do rascunho de KB no arquivo `event/listener.php`.
- Melhorado o tratamento e a limpeza do conteúdo em BBCode antes da geração do rascunho.
- Melhorada a organização automática das seções:
  - Relato original
  - Sintomas confirmados
  - Causa raiz
  - Solução aplicada
- Melhorado o comportamento de fallback quando a resposta final do tópico é curta, mas válida como solução.
- Melhorados os textos de idioma em `pt_br` e `en` relacionados ao assistente de KB.

## 3.4.1 - 2026-03-22
- revisão final da documentação para release candidate;
- README reorganizado e limpo;
- changelog consolidado e normalizado;
- checklist de testes revisado;
- ajuste de metadados da versão no `composer.json`.

## 3.4.0 - 2026-03-22
- respostas sugeridas por contexto em viewtopic e na tela de resposta/citação.

## 3.3.3 - 2026-03-22
- diagnóstico de aprovação nativa no ACP para fóruns monitorados.

## 3.3.2 - 2026-03-22
- permissão dedicada de moderador para alteração de prioridade;
- separação da checagem de prioridade e status no viewtopic e no MCP;
- esclarecimentos no ACP sobre aprovação nativa do phpBB.

## 3.3.1 - 2026-03-22
- documentação e checklist iniciais de preparação para submissão/publicação.

## 3.3.0 - 2026-03-22
- dashboard inicial no ACP com resumo operacional, saúde e atalhos rápidos.

## 3.2.0 - 2026-03-22
- fila inteligente no MCP com visões de workflow e ordenação por urgência.

## 3.1.0 - 2026-03-22
- ações em lote no MCP para status e prioridade.

## 3.0.0 - 2026-03-22
- painel de saúde e manutenção no ACP.

## 2.8.1 - 2026-03-22
- ajuste de traduções do ACP;
- botão para limpar logs com confirmação.

## 2.8.0 - 2026-03-22
- exportação filtrada de métricas, logs e fila.

## 2.7.1 - 2026-03-22
- correção de inicialização e visibilidade da fila no MCP.

## 2.7.0 - 2026-03-22
- prioridade automática por regras de fórum, tipo e tópicos parados.

## 2.6.0 - 2026-03-22
- caixa interna de notificações da equipe.

## 2.5.x - 2026-03-22
- exportação CSV, correções do ACP, traduções e hardening.

## 2.4.0 - 2026-03-22
- prioridade do chamado.

## 2.3.0 - 2026-03-22
- refinamento da fila do MCP.

## 2.2.x - 2026-03-22
- alertas visuais e correções de estabilidade.

## 2.1.0 - 2026-03-22
- métricas e painel de desempenho no ACP.

## 2.0.x - 2026-03-22
- painel de fila no MCP e correção de template no ACP.

## 1.9.0 - 2026-03-22
- automações e SLA.

## 1.8.x - 2026-03-22
- histórico de ações e correção de token em formulário.

## 1.7.0 - 2026-03-22
- ACL próprias da extensão.

## 1.6.x - 2026-03-22
- sincronização e correções do fluxo de KB.

## 1.5.x - 2026-03-22
- criação de rascunho de base de conhecimento e correções de schema/ACP.

## 1.4.0 - 2026-03-22
- snippets da equipe.

## 1.3.0 - 2026-03-22
- sugestão de tópicos parecidos.

## 1.2.0 - 2026-03-22
- campos condicionais por tipo de problema.

## 1.1.0 - 2026-03-22
- status de triagem.

## 1.0.0 - 2026-03-22
- versão inicial do assistente de triagem.
