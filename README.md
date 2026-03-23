# MundoPHPBB Support Triage

Extensão para **phpBB 3.3** voltada a fóruns de **suporte técnico**, com abertura guiada de tópicos, fluxo de triagem, prioridade, fila no MCP, base de conhecimento, métricas e exportação de relatórios.

## Versão atual

**3.4.1**

Esta revisão é um **release candidate** focado em preparação para envio/publicação:
- documentação reorganizada e limpa;
- changelog consolidado;
- checklist de testes revisado;
- observações mais claras sobre permissões e aprovação nativa do phpBB;
- ajuste de metadados da versão.

## Recursos principais

- assistente guiado no formulário de novo tópico;
- campos condicionais por tipo de problema;
- sugestão de tópicos parecidos durante a digitação do título;
- status de triagem por tópico;
- prioridade manual e automática;
- snippets da equipe e sugestões por contexto;
- automações de fluxo e SLA;
- rascunho de base de conhecimento com sincronização;
- ACL próprias;
- histórico de ações;
- fila refinada e inteligente no MCP;
- ações em lote no MCP;
- dashboard, métricas, saúde e exportação CSV no ACP;
- alertas visuais e caixa interna da equipe.

## Requisitos

- phpBB **3.3.x**
- PHP **7.1.0 ou superior**

## Instalação

1. Envie a pasta `mundophpbb/supporttriage` para `ext/mundophpbb/supporttriage`.
2. No ACP, vá em **Personalizar -> Gerenciar extensões**.
3. Ative **MundoPHPBB Support Triage**.
4. Configure em **Extensões -> Support Triage**.
5. Revise as permissões em **Permissões do fórum -> Permissões de moderador**, principalmente se você usa roles personalizadas.

## Atualização

1. Substitua os arquivos da extensão em `ext/mundophpbb/supporttriage`.
2. Entre no ACP para aplicar eventuais migrations pendentes.
3. Limpe o cache do phpBB.
4. Revise as opções do ACP e o comportamento da fila no MCP.

## Desinstalação

1. Desative a extensão no ACP.
2. Se desejar remoção completa, escolha **Apagar dados**.
3. Remova a pasta `ext/mundophpbb/supporttriage`.

## Permissões e aprovação

### Permissões da extensão
As permissões do Support Triage aparecem principalmente em **Permissões do fórum -> Permissões de moderador**.

Exemplos:
- alteração de status;
- alteração de prioridade;
- criação/sincronização de KB;
- uso de recursos de equipe no tópico e no MCP.

### Aprovação de tópicos e respostas
A extensão **não controla a aprovação nativa** de tópicos ou respostas.

Se um tópico ainda exige aprovação, revise o phpBB nativo em:
- permissões do fórum do grupo/usuário;
- roles aplicadas ao grupo;
- grupo **Newly Registered Users**, quando existir;
- permissões como postagem sem aprovação e moderação do fórum.

## Áreas da extensão

### ACP
- visão geral e dashboard inicial;
- configurações do assistente de triagem;
- snippets da equipe;
- métricas e exportação CSV;
- saúde, manutenção e diagnóstico;
- limpeza de logs e caixa interna.

### MCP
- fila de suporte com filtros, workflow e ordenação inteligente;
- ações rápidas por tópico;
- ações em lote para status e prioridade;
- destaque por urgência, SLA e alertas.

### Viewtopic / Posting
- status e prioridade do chamado;
- ações de moderação no próprio tópico;
- relatórios técnicos automáticos;
- criação e sincronização de rascunho de KB;
- sugestões de tópicos parecidos;
- snippets sugeridos por contexto.

## Arquivos de apoio

- `CHANGELOG.md` — histórico resumido das versões;
- `TESTING.md` — checklist sugerido para validação antes de publicar.

## Observações

- O objetivo da extensão é melhorar o fluxo de suporte técnico sem alterar o core do phpBB.
- Sempre teste atualizações primeiro em ambiente de homologação.
- Para envio à base do phpBB, recomenda-se revisar o comportamento visual no **prosilver** e, se possível, em um segundo estilo.

## Licença

GPL-2.0-or-later
