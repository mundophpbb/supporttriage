# Checklist de testes

Use esta lista antes de publicar ou enviar a extensão para revisão.

## Instalação e atualização
- [ ] ativar a extensão sem erro;
- [ ] aplicar migrations sem erro;
- [ ] acessar o módulo do ACP sem erro de Twig/PHP;
- [ ] acessar a fila do MCP sem erro;
- [ ] atualizar a partir de uma versão anterior sem regressão aparente.

## Assistente de triagem
- [ ] criar tópico novo com relatório automático;
- [ ] validar campos condicionais por tipo de problema;
- [ ] validar sugestão de tópicos parecidos;
- [ ] validar prioridade padrão e prioridade escolhida.

## Fluxo de triagem
- [ ] alterar status no viewtopic;
- [ ] alterar prioridade no viewtopic;
- [ ] testar snippets da equipe;
- [ ] testar snippets sugeridos por contexto;
- [ ] testar automações de status e SLA.

## MCP
- [ ] fila visível no fórum moderado;
- [ ] filtros funcionando;
- [ ] ações rápidas funcionando;
- [ ] ações em lote funcionando;
- [ ] visões de workflow funcionando.

## KB
- [ ] criar rascunho de KB;
- [ ] sincronizar rascunho de KB;
- [ ] evitar criação duplicada;
- [ ] testar com fórum KB configurado e sem configuração.

## ACP
- [ ] dashboard inicial carrega corretamente;
- [ ] métricas carregam sem erro;
- [ ] exportação CSV funciona;
- [ ] limpar logs funciona;
- [ ] saúde/manutenção funciona;
- [ ] diagnóstico de aprovação nativa mostra dados coerentes.

## Permissões
- [ ] permissões de moderador do Support Triage aparecem no ACP;
- [ ] permissão dedicada de prioridade aparece corretamente;
- [ ] prioridade pode ser alterada no viewtopic e no MCP após a migration;
- [ ] comportamento de aprovação continua seguindo permissões nativas do phpBB.

## Desinstalação
- [ ] desativar a extensão sem erro;
- [ ] apagar dados sem erro;
- [ ] verificar remoção das tabelas da extensão.
