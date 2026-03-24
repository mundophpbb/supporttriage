(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function listItem(label, value) {
        var clean = String(value || '').trim();
        if (!clean) {
            return '';
        }
        return '[*][b]' + label + ':[/b] ' + clean;
    }

    ready(function () {
        var panel = document.getElementById('supporttriage-panel');
        var message = document.getElementById('message');
        if (!panel || !message) {
            return;
        }

        var issueType = document.getElementById('supporttriage_issue_type');
        var insertButton = document.getElementById('supporttriage-insert-report');
        var clearButton = document.getElementById('supporttriage-clear-fields');

        function currentIssueType() {
            return issueType ? issueType.value : 'general';
        }

        function getValue(id) {
            var el = document.getElementById(id);
            return el ? String(el.value || '').trim() : '';
        }

        function getDisplayValue(id) {
            var el = document.getElementById(id);
            if (!el) {
                return '';
            }
            if (el.tagName === 'SELECT' && el.selectedIndex >= 0) {
                return String(el.options[el.selectedIndex].text || '').trim();
            }
            return String(el.value || '').trim();
        }

        function sanitizeCode(value) {
            return String(value || '').replace(/\[\/??code\]/gi, '').trim();
        }

        function syncConditionalBlocks() {
            var blocks = panel.querySelectorAll('.supporttriage-conditional');
            var type = currentIssueType();
            blocks.forEach(function (block) {
                if (block.getAttribute('data-issue-type') === type) {
                    block.classList.remove('is-hidden');
                } else {
                    block.classList.add('is-hidden');
                }
            });
        }

        function specificItems(type) {
            if (type === 'extension') {
                return [
                    listItem(panel.getAttribute('data-label-ext-version'), getValue('supporttriage_ext_version')),
                    listItem(panel.getAttribute('data-label-ext-stage'), getValue('supporttriage_ext_stage')),
                    listItem(panel.getAttribute('data-label-ext-source'), getValue('supporttriage_ext_source'))
                ];
            }
            if (type === 'update') {
                return [
                    listItem(panel.getAttribute('data-label-update-from'), getValue('supporttriage_update_from')),
                    listItem(panel.getAttribute('data-label-update-to'), getValue('supporttriage_update_to')),
                    listItem(panel.getAttribute('data-label-update-method'), getValue('supporttriage_update_method')),
                    listItem(panel.getAttribute('data-label-update-db'), getValue('supporttriage_update_db'))
                ];
            }
            if (type === 'style') {
                return [
                    listItem(panel.getAttribute('data-label-style-browser'), getValue('supporttriage_style_browser')),
                    listItem(panel.getAttribute('data-label-style-page'), getValue('supporttriage_style_page')),
                    listItem(panel.getAttribute('data-label-style-device'), getValue('supporttriage_style_device'))
                ];
            }
            if (type === 'permissions') {
                return [
                    listItem(panel.getAttribute('data-label-perm-actor'), getValue('supporttriage_perm_actor')),
                    listItem(panel.getAttribute('data-label-perm-action'), getValue('supporttriage_perm_action')),
                    listItem(panel.getAttribute('data-label-perm-target'), getValue('supporttriage_perm_target'))
                ];
            }
            if (type === 'email') {
                return [
                    listItem(panel.getAttribute('data-label-email-transport'), getValue('supporttriage_email_transport')),
                    listItem(panel.getAttribute('data-label-email-case'), getValue('supporttriage_email_case')),
                    listItem(panel.getAttribute('data-label-email-log'), getValue('supporttriage_email_log'))
                ];
            }
            return [];
        }

        function buildReport() {
            var type = currentIssueType();
            var lines = [];
            var specific = specificItems(type).filter(Boolean);
            var errorText = sanitizeCode(getValue('supporttriage_error'));
            var stepsText = getValue('supporttriage_steps');
            var boardUrl = getValue('supporttriage_board_url') || window.location.origin;
            var typeLabel = panel.getAttribute('data-type-label-' + type) || type;

            lines.push('[b]=== ' + panel.getAttribute('data-report-title') + ' ===[/b]');
            lines.push('[list]');
            [
                [panel.getAttribute('data-label-issue-type'), typeLabel],
                [panel.getAttribute('data-label-phpbb'), getValue('supporttriage_phpbb')],
                [panel.getAttribute('data-label-php'), getValue('supporttriage_php')],
                [panel.getAttribute('data-label-style'), getValue('supporttriage_style')],
                [panel.getAttribute('data-label-extension'), getValue('supporttriage_extension')],
                [panel.getAttribute('data-label-board-url'), boardUrl],
                [panel.getAttribute('data-label-prosilver'), getDisplayValue('supporttriage_prosilver')],
                [panel.getAttribute('data-label-debug'), getDisplayValue('supporttriage_debug')]
            ].forEach(function (item) {
                var line = listItem(item[0], item[1]);
                if (line) {
                    lines.push(line);
                }
            });
            lines.push('[/list]');

            if (specific.length) {
                lines.push('');
                lines.push('[b]' + panel.getAttribute('data-specific-title') + '[/b]');
                lines.push('[list]');
                specific.forEach(function (item) { lines.push(item); });
                lines.push('[/list]');
            }

            if (errorText) {
                lines.push('');
                lines.push('[b]' + panel.getAttribute('data-label-error') + '[/b]');
                lines.push('[code]' + errorText + '[/code]');
            }

            if (stepsText) {
                lines.push('');
                lines.push('[b]' + panel.getAttribute('data-label-steps') + '[/b]');
                lines.push(stepsText);
            }

            lines.push('');
            lines.push('[b]=== /' + panel.getAttribute('data-report-title') + ' ===[/b]');
            return lines.join('\n');
        }

        function mergeReport() {
            var report = buildReport();
            var current = String(message.value || '');
            var blockRegex = /\[b\]=== .*? ===\[\/b\][\s\S]*?\[b\]=== \/.*? ===\[\/b\]\n*/m;
            if (blockRegex.test(current)) {
                message.value = current.replace(blockRegex, report + '\n\n');
            } else if (current.trim() === '') {
                message.value = report + '\n\n';
            } else {
                message.value = report + '\n\n' + current;
            }
        }

        function clearFields() {
            panel.querySelectorAll('input[type="text"], textarea').forEach(function (el) {
                el.value = '';
            });
            panel.querySelectorAll('select').forEach(function (el) {
                el.selectedIndex = 0;
            });
            document.getElementById('supporttriage_board_url').value = window.location.origin;
            syncConditionalBlocks();
        }

        var boardUrl = document.getElementById('supporttriage_board_url');
        if (boardUrl && !boardUrl.value) {
            boardUrl.value = window.location.origin;
        }

        if (issueType) {
            issueType.addEventListener('change', syncConditionalBlocks);
            syncConditionalBlocks();
        }

        if (insertButton) {
            insertButton.addEventListener('click', mergeReport);
        }

        if (clearButton) {
            clearButton.addEventListener('click', clearFields);
        }
    });
})();
