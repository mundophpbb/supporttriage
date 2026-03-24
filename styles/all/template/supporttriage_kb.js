(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function textOf(el) {
        return el ? String(el.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function htmlToText(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return textOf(tmp);
    }

    function normalize(text) {
        return String(text || '')
            .replace(/\u00a0/g, ' ')
            .replace(/[ \t]+/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function extractErrorBlock(raw) {
        var match = raw.match(/\[b\]Erro exibido\[\/b\]\s*\[code\]([\s\S]*?)\[\/code\]/i);
        if (match && match[1]) {
            return normalize(match[1]);
        }
        match = raw.match(/Erro exibido\s*([\s\S]{0,700})/i);
        return match ? normalize(match[1].split('=== /')[0]) : '';
    }

    function cleanReportBlock(raw) {
        return normalize(String(raw || '')
            .replace(/\[b\]===.*?===\[\/b\]/gi, '')
            .replace(/\[list\]|\[\/list\]/gi, '')
            .replace(/\[\*\]/g, '- ')
            .replace(/\[code\]([\s\S]*?)\[\/code\]/gi, '$1')
            .replace(/\[(?:b|\/b|url=.*?|\/url)\]/gi, ''));
    }

    function inferCause(firstPost, solution, errorText) {
        var hay = (firstPost + '\n' + solution + '\n' + errorText).toLowerCase();

        if (hay.indexOf('diretório de instalação') !== -1 || hay.indexOf('pasta install') !== -1 || hay.indexOf('directory de instalação') !== -1 || hay.indexOf('rename the install') !== -1 || hay.indexOf('install') !== -1 && hay.indexOf('acp') !== -1) {
            return 'A pasta [code]install[/code] ainda estava presente na raiz do phpBB após a instalação.';
        }
        if (hay.indexOf('permission') !== -1 || hay.indexOf('permiss') !== -1 || hay.indexOf('acl') !== -1) {
            return 'A configuração de permissões/ACL não correspondia ao acesso esperado para o usuário ou grupo afetado.';
        }
        if (hay.indexOf('migration') !== -1 || hay.indexOf('update database') !== -1 || hay.indexOf('dbal') !== -1) {
            return 'O problema estava ligado ao processo de atualização/migration do phpBB ou da extensão.';
        }
        if (hay.indexOf('cache') !== -1) {
            return 'Parte do comportamento observado estava relacionada a cache desatualizado do phpBB/estilo.';
        }
        if (solution) {
            return 'A causa foi identificada a partir do diagnóstico feito no tópico e confirmada pela solução aplicada abaixo.';
        }
        return 'A causa raiz deve ser revisada manualmente com base no diagnóstico feito no tópico.';
    }

    function summarizeOriginal(firstPost, errorText) {
        var text = cleanReportBlock(firstPost);
        if (errorText) {
            text = text.replace(errorText, '').trim();
        }
        if (text.length > 550) {
            text = text.slice(0, 547).trim() + '...';
        }
        return text || 'Problema relatado no tópico de suporte.';
    }

    function findTopicTitle() {
        return textOf(document.querySelector('h2.topic-title, .viewtopic h2, .action-bar + h2, .topic-title')) || document.title;
    }

    function topicUrl() {
        return window.location.pathname + window.location.search;
    }

    function collectPosts() {
        return Array.prototype.slice.call(document.querySelectorAll('.post'));
    }

    function findFirstPostText() {
        var first = collectPosts()[0];
        var body = first ? first.querySelector('.content') : null;
        return body ? normalize(body.innerText || body.textContent || '') : '';
    }

    function findLastReplyText() {
        var posts = collectPosts();
        if (posts.length < 2) {
            return '';
        }
        for (var i = posts.length - 1; i >= 1; i -= 1) {
            var body = posts[i].querySelector('.content');
            var text = body ? normalize(body.innerText || body.textContent || '') : '';
            if (text) {
                return text;
            }
        }
        return '';
    }

    function buildDraft(firstPost, solution) {
        var errorText = extractErrorBlock(firstPost);
        var original = summarizeOriginal(firstPost, errorText);
        var cause = inferCause(firstPost, solution, errorText);
        var solutionText = normalize(solution || '').replace(/\[code\]/gi, '').replace(/\[\/code\]/gi, '');
        var title = findTopicTitle();

        return [
            '[b]Edite este rascunho, revise o texto e remova informações sensíveis antes de publicar.[/b]',
            '',
            '[b]Tópico de origem[/b]',
            '[url=' + topicUrl() + ']' + title + '[/url]',
            '',
            '[b]Status no suporte[/b]',
            'Resolvido',
            '',
            '[b]Relato original[/b]',
            original,
            '',
            '[b]Sintomas confirmados[/b]',
            errorText ? '[code]' + errorText + '[/code]' : 'Confirmar manualmente os sintomas observados no tópico original.',
            '',
            '[b]Causa raiz[/b]',
            cause,
            '',
            '[b]Solução aplicada[/b]',
            solutionText || 'Descrever manualmente a solução aplicada.',
            '',
            '[b]Observações finais[/b]',
            'Valide o texto final antes de publicar no KB e remova dados específicos do ambiente quando não forem necessários.',
        ].join('\n');
    }

    ready(function () {
        var helper = document.getElementById('supporttriage-kb-helper');
        if (!helper) {
            return;
        }

        var button = document.getElementById('supporttriage-kb-generate');
        var copyButton = document.getElementById('supporttriage-kb-copy');
        var source = document.getElementById('supporttriage-kb-solution');
        var draft = document.getElementById('supporttriage-kb-draft');
        var message = document.getElementById('supporttriage-kb-message');

        function setMessage(text) {
            if (message) {
                message.textContent = text || '';
            }
        }

        if (source && !source.value) {
            source.value = findLastReplyText();
        }

        if (button) {
            button.addEventListener('click', function () {
                var firstPost = findFirstPostText();
                var solution = normalize(source ? source.value : '') || findLastReplyText();
                if (!firstPost) {
                    setMessage(helper.getAttribute('data-no-topic') || 'Não foi possível montar o rascunho.');
                    return;
                }
                draft.value = buildDraft(firstPost, solution);
                setMessage('Rascunho gerado.');
            });
        }

        if (copyButton) {
            copyButton.addEventListener('click', function () {
                if (!draft || !draft.value) {
                    return;
                }
                draft.select();
                draft.setSelectionRange(0, draft.value.length);
                try {
                    document.execCommand('copy');
                    setMessage('Rascunho copiado.');
                } catch (e) {
                    setMessage('Copie manualmente o rascunho.');
                }
            });
        }
    });
})();
