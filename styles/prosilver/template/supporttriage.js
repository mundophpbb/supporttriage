/* Support Triage — prosilver assets */
document.addEventListener('DOMContentLoaded', function () {
    var panels = document.querySelectorAll('.supporttriage-snippets-panel');
    if (!panels.length) {
        return;
    }

    function findMessageField() {
        return document.querySelector('textarea[name="message"]') || document.getElementById('message');
    }

    function copyText(text, sourceField) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
            return true;
        }

        if (!sourceField) {
            return false;
        }

        sourceField.focus();
        sourceField.select();
        try {
            return document.execCommand('copy');
        } catch (e) {
            return false;
        }
    }

    panels.forEach(function (panel) {
        var insertedMessage = panel.getAttribute('data-supporttriage-snippet-inserted') || '';
        var copiedMessage = panel.getAttribute('data-supporttriage-snippet-copied') || '';
        var fallbackMessage = panel.getAttribute('data-supporttriage-snippet-copy-fallback') || '';

        panel.querySelectorAll('.supporttriage-snippet-card').forEach(function (card) {
            var textField = card.querySelector('.supporttriage-snippet-text');
            var insertButton = card.querySelector('.supporttriage-snippet-insert');
            var copyButton = card.querySelector('.supporttriage-snippet-copy');
            var feedback = card.querySelector('.supporttriage-snippet-feedback');

            function setFeedback(message) {
                if (feedback) {
                    feedback.textContent = message;
                }
            }

            if (insertButton) {
                insertButton.addEventListener('click', function () {
                    var messageField = findMessageField();
                    var text = textField ? textField.value : '';

                    if (messageField) {
                        var prefix = messageField.value && !/\n\s*$/.test(messageField.value) ? '\n\n' : '';
                        messageField.value += prefix + text;
                        messageField.focus();
                        setFeedback(insertedMessage);
                    } else if (copyText(text, textField)) {
                        setFeedback(copiedMessage);
                    } else {
                        setFeedback(fallbackMessage);
                    }
                });
            }

            if (copyButton) {
                copyButton.addEventListener('click', function () {
                    var text = textField ? textField.value : '';
                    if (copyText(text, textField)) {
                        setFeedback(copiedMessage);
                    } else {
                        setFeedback(fallbackMessage);
                    }
                });
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('supporttriage-panel');
    if (!panel) {
        return;
    }

    var form = document.getElementById('postform');
    var message = document.getElementById('message');
    var subject = document.getElementById('subject');
    var similarPanel = document.getElementById('supporttriage-similar-panel');
    var similarList = document.getElementById('supporttriage-similar-list');
    var similarEmpty = document.getElementById('supporttriage-similar-empty');
    var suggestionsData = document.getElementById('supporttriage-suggestions-data');
    var autoInsert = panel.getAttribute('data-auto-insert') === '1';
    var prefix = panel.getAttribute('data-title-prefix') || '';
    var startMarker = '[b]=== ' + panel.getAttribute('data-report-title') + ' ===[/b]';
    var endMarker = '[b]=== /' + panel.getAttribute('data-report-title') + ' ===[/b]';
    var conditionalBlocks = panel.querySelectorAll('.supporttriage-conditional');
    var suggestions = [];

    try {
        suggestions = suggestionsData ? JSON.parse(suggestionsData.textContent || '[]') : [];
    } catch (error) {
        suggestions = [];
    }

    function getField(id) {
        return document.getElementById(id);
    }

    function getValue(id) {
        var field = getField(id);
        return field ? field.value.trim() : '';
    }

    function getDisplayValue(id) {
        var field = getField(id);
        if (!field) {
            return '';
        }

        if (field.tagName && field.tagName.toLowerCase() === 'select') {
            if (field.value === '' || field.selectedIndex < 0) {
                return '';
            }
            return field.options[field.selectedIndex].text.trim();
        }

        return field.value.trim();
    }

    function setValue(id, value) {
        var field = getField(id);
        if (field) {
            field.value = value;
        }
    }

    function sanitizeCode(text) {
        return text.replace(/\[\/?code\]/gi, '').trim();
    }

    function currentIssueType() {
        return getValue('supporttriage_issue_type') || 'general';
    }

    function hasAnyContent() {
        return [
            getValue('supporttriage_issue_type') !== 'general' ? getValue('supporttriage_issue_type') : '',
            getValue('supporttriage_phpbb'),
            getValue('supporttriage_php'),
            getValue('supporttriage_style'),
            getValue('supporttriage_extension'),
            getValue('supporttriage_board_url'),
            getValue('supporttriage_prosilver'),
            getValue('supporttriage_debug'),
            getValue('supporttriage_error'),
            getValue('supporttriage_steps'),
            getValue('supporttriage_ext_version'),
            getValue('supporttriage_ext_stage'),
            getValue('supporttriage_ext_source'),
            getValue('supporttriage_update_from'),
            getValue('supporttriage_update_to'),
            getValue('supporttriage_update_method'),
            getValue('supporttriage_update_db'),
            getValue('supporttriage_style_browser'),
            getValue('supporttriage_style_page'),
            getValue('supporttriage_style_device'),
            getValue('supporttriage_perm_actor'),
            getValue('supporttriage_perm_action'),
            getValue('supporttriage_perm_target'),
            getValue('supporttriage_email_transport'),
            getValue('supporttriage_email_case'),
            getValue('supporttriage_email_log')
        ].join('') !== '';
    }

    function listItem(label, value) {
        return value ? '[*][b]' + label + ':[/b] ' + value : '';
    }

    function getIssueTypeLabel(issueType) {
        return panel.getAttribute('data-type-label-' + issueType) || panel.getAttribute('data-type-label-general') || issueType;
    }

    function toggleIssueSections() {
        var issueType = currentIssueType();

        Array.prototype.forEach.call(conditionalBlocks, function (block) {
            if (block.getAttribute('data-issue-type') === issueType) {
                block.classList.remove('is-hidden');
            } else {
                block.classList.add('is-hidden');
            }
        });
    }

    function normalizeText(text) {
        return (text || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9\s]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getSuggestionQuery() {
        if (!subject) {
            return '';
        }

        var value = subject.value || '';
        if (prefix && value.indexOf(prefix) === 0) {
            value = value.slice(prefix.length);
        }

        return value.trim();
    }

    function scoreSuggestion(query, topic) {
        var normalizedQuery = normalizeText(query);
        var normalizedTitle = normalizeText(topic.title || '');

        if (!normalizedQuery || !normalizedTitle) {
            return 0;
        }

        var score = 0;
        var tokens = normalizedQuery.split(' ').filter(function (token) {
            return token.length >= 3;
        });

        if (normalizedTitle === normalizedQuery) {
            score += 200;
        }

        if (normalizedTitle.indexOf(normalizedQuery) !== -1) {
            score += 120;
        }

        tokens.forEach(function (token, index) {
            if (normalizedTitle.indexOf(token) !== -1) {
                score += 28;
                if (index === 0) {
                    score += 8;
                }
            }
        });

        if (tokens.length >= 2) {
            var overlap = tokens.filter(function (token) {
                return normalizedTitle.indexOf(token) !== -1;
            }).length;

            if (overlap >= 2) {
                score += overlap * 12;
            }
        }

        return score;
    }

    function clearSuggestions() {
        if (similarList) {
            similarList.innerHTML = '';
        }
        if (similarPanel) {
            similarPanel.classList.add('is-hidden');
        }
        if (similarEmpty) {
            similarEmpty.classList.add('is-hidden');
        }
    }

    function renderSuggestions() {
        if (!similarPanel || !similarList || !subject || !Array.isArray(suggestions) || !suggestions.length) {
            clearSuggestions();
            return;
        }

        var query = getSuggestionQuery();
        if (normalizeText(query).length < 4) {
            clearSuggestions();
            return;
        }

        var matches = suggestions
            .map(function (item) {
                return {
                    item: item,
                    score: scoreSuggestion(query, item)
                };
            })
            .filter(function (entry) {
                return entry.score >= 35;
            })
            .sort(function (a, b) {
                return b.score - a.score;
            })
            .slice(0, 5);

        similarList.innerHTML = '';
        similarPanel.classList.remove('is-hidden');

        if (!matches.length) {
            if (similarEmpty) {
                similarEmpty.classList.remove('is-hidden');
            }
            return;
        }

        if (similarEmpty) {
            similarEmpty.classList.add('is-hidden');
        }

        matches.forEach(function (entry) {
            var item = entry.item;
            var li = document.createElement('li');
            var link = document.createElement('a');
            link.href = item.url || '#';
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = item.title || '';
            li.appendChild(link);

            if (item.last_post_time) {
                var meta = document.createElement('span');
                meta.className = 'supporttriage-similar-meta';
                meta.textContent = panel.getAttribute('data-label-similar-last-post') + ': ' + item.last_post_time;
                li.appendChild(meta);
            }

            similarList.appendChild(li);
        });
    }

    function getSpecificItems(issueType) {
        if (issueType === 'extension') {
            return [
                listItem(panel.getAttribute('data-label-ext-version'), getValue('supporttriage_ext_version')),
                listItem(panel.getAttribute('data-label-ext-stage'), getDisplayValue('supporttriage_ext_stage')),
                listItem(panel.getAttribute('data-label-ext-source'), getValue('supporttriage_ext_source'))
            ];
        }

        if (issueType === 'update') {
            return [
                listItem(panel.getAttribute('data-label-update-from'), getValue('supporttriage_update_from')),
                listItem(panel.getAttribute('data-label-update-to'), getValue('supporttriage_update_to')),
                listItem(panel.getAttribute('data-label-update-method'), getDisplayValue('supporttriage_update_method')),
                listItem(panel.getAttribute('data-label-update-db'), getValue('supporttriage_update_db'))
            ];
        }

        if (issueType === 'style') {
            return [
                listItem(panel.getAttribute('data-label-style-browser'), getValue('supporttriage_style_browser')),
                listItem(panel.getAttribute('data-label-style-page'), getValue('supporttriage_style_page')),
                listItem(panel.getAttribute('data-label-style-device'), getValue('supporttriage_style_device'))
            ];
        }

        if (issueType === 'permissions') {
            return [
                listItem(panel.getAttribute('data-label-perm-actor'), getValue('supporttriage_perm_actor')),
                listItem(panel.getAttribute('data-label-perm-action'), getValue('supporttriage_perm_action')),
                listItem(panel.getAttribute('data-label-perm-target'), getValue('supporttriage_perm_target'))
            ];
        }

        if (issueType === 'email') {
            return [
                listItem(panel.getAttribute('data-label-email-transport'), getDisplayValue('supporttriage_email_transport')),
                listItem(panel.getAttribute('data-label-email-case'), getDisplayValue('supporttriage_email_case')),
                listItem(panel.getAttribute('data-label-email-log'), sanitizeCode(getValue('supporttriage_email_log')))
            ];
        }

        return [];
    }

    function buildReport() {
        var lines = [];
        var issueType = currentIssueType();
        var specificItems = getSpecificItems(issueType).filter(Boolean);
        var errorText = sanitizeCode(getValue('supporttriage_error'));
        var stepsText = getValue('supporttriage_steps');

        lines.push(startMarker);
        lines.push('[list]');

        [
            [panel.getAttribute('data-label-issue-type'), getIssueTypeLabel(issueType)],
            [panel.getAttribute('data-label-phpbb'), getValue('supporttriage_phpbb')],
            [panel.getAttribute('data-label-php'), getValue('supporttriage_php')],
            [panel.getAttribute('data-label-style'), getValue('supporttriage_style')],
            [panel.getAttribute('data-label-extension'), getValue('supporttriage_extension')],
            [panel.getAttribute('data-label-board-url'), getValue('supporttriage_board_url')],
            [panel.getAttribute('data-label-prosilver'), getDisplayValue('supporttriage_prosilver')],
            [panel.getAttribute('data-label-debug'), getDisplayValue('supporttriage_debug')]
        ].forEach(function (item) {
            var line = listItem(item[0], item[1]);
            if (line) {
                lines.push(line);
            }
        });

        lines.push('[/list]');

        if (specificItems.length) {
            lines.push('');
            lines.push('[b]' + panel.getAttribute('data-specific-title') + '[/b]');
            lines.push('[list]');
            specificItems.forEach(function (item) {
                lines.push(item);
            });
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
        lines.push(endMarker);

        return lines.join('\n');
    }

    function mergeReportIntoMessage(force) {
        if (!message) {
            return;
        }

        var report = buildReport();
        var current = message.value || '';
        var blockRegex = /\[b\]=== .*? ===\[\/b\][\s\S]*?\[b\]=== \/.*? ===\[\/b\]\n*/m;

        if (!force && !hasAnyContent()) {
            return;
        }

        if (blockRegex.test(current)) {
            message.value = current.replace(blockRegex, report + '\n\n');
        } else if (current.trim() === '') {
            message.value = report + '\n\n';
        } else {
            message.value = report + '\n\n' + current;
        }
    }

    function clearFields() {
        [
            'supporttriage_phpbb',
            'supporttriage_php',
            'supporttriage_style',
            'supporttriage_extension',
            'supporttriage_prosilver',
            'supporttriage_debug',
            'supporttriage_error',
            'supporttriage_steps',
            'supporttriage_ext_version',
            'supporttriage_ext_stage',
            'supporttriage_ext_source',
            'supporttriage_update_from',
            'supporttriage_update_to',
            'supporttriage_update_method',
            'supporttriage_update_db',
            'supporttriage_style_browser',
            'supporttriage_style_page',
            'supporttriage_style_device',
            'supporttriage_perm_actor',
            'supporttriage_perm_action',
            'supporttriage_perm_target',
            'supporttriage_email_transport',
            'supporttriage_email_case',
            'supporttriage_email_log'
        ].forEach(function (id) {
            setValue(id, '');
        });

        setValue('supporttriage_issue_type', 'general');
        setValue('supporttriage_board_url', panel.getAttribute('data-default-board-url') || '');
        toggleIssueSections();
    }

    var issueTypeField = getField('supporttriage_issue_type');
    if (issueTypeField) {
        issueTypeField.addEventListener('change', toggleIssueSections);
    }

    var insertButton = document.getElementById('supporttriage-insert');
    if (insertButton) {
        insertButton.addEventListener('click', function () {
            mergeReportIntoMessage(true);
        });
    }

    var clearButton = document.getElementById('supporttriage-clear');
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            clearFields();
        });
    }

    if (subject && prefix && subject.value.indexOf(prefix) !== 0) {
        subject.value = prefix + ' ' + subject.value.replace(/^\s+/, '');
    }

    if (subject) {
        subject.addEventListener('input', renderSuggestions);
        subject.addEventListener('change', renderSuggestions);
    }

    if (autoInsert && form) {
        form.addEventListener('submit', function () {
            mergeReportIntoMessage(hasAnyContent());
        });
    }

    toggleIssueSections();
    renderSuggestions();

    if (autoInsert && message && message.value.trim() === '' && hasAnyContent()) {
        mergeReportIntoMessage(true);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var panel = document.querySelector('.supporttriage-mcp-panel');
    var filter = document.getElementById('supporttriage-mcp-filter');
    var workflow = document.getElementById('supporttriage-mcp-workflow');
    var attention = document.getElementById('supporttriage-mcp-attention');
    var priority = document.getElementById('supporttriage-mcp-priority');
    var sortMode = document.getElementById('supporttriage-mcp-sort');
    var search = document.getElementById('supporttriage-mcp-search');
    if (!filter) {
        return;
    }

    function normalize(text) {
        return (text || '').toString().toLowerCase();
    }

    function priorityWeight(key) {
        if (key === 'critical') {
            return 4;
        }
        if (key === 'high') {
            return 3;
        }
        if (key === 'normal') {
            return 2;
        }
        return 1;
    }

    function workflowBucket(meta) {
        if (meta.status === 'waiting_reply' || meta.status === 'no_reply') {
            return 'awaiting_author';
        }

        if (meta.status === 'new' || meta.status === 'in_progress' || meta.primaryAlert === 'author_return') {
            return 'awaiting_team';
        }

        return 'all';
    }

    function isActionNow(meta) {
        return meta.primaryAlert === 'author_return'
            || meta.primaryAlert === 'sla_warning'
            || meta.stale
            || meta.status === 'new'
            || meta.status === 'in_progress'
            || meta.status === 'no_reply'
            || meta.priorityKey === 'critical';
    }

    function urgencyScore(meta) {
        var score = 0;
        if (meta.status === 'new') {
            score += 50;
        } else if (meta.status === 'in_progress') {
            score += 45;
        } else if (meta.status === 'no_reply') {
            score += 60;
        } else if (meta.status === 'waiting_reply') {
            score += 20;
        }

        if (meta.primaryAlert === 'author_return') {
            score += 80;
        } else if (meta.primaryAlert === 'sla_warning') {
            score += 70;
        } else if (meta.primaryAlert === 'no_reply') {
            score += 60;
        } else if (meta.primaryAlert === 'kb_linked') {
            score += 5;
        }

        if (meta.stale) {
            score += 30;
        }

        score += priorityWeight(meta.priorityKey) * 10;
        if (meta.updatedTs > 0) {
            score += Math.min(20, Math.floor((Date.now() / 1000 - meta.updatedTs) / 86400));
        }

        return score;
    }

    function getEntries() {
        return Array.prototype.slice.call(document.querySelectorAll('.supporttriage-topic-marker')).map(function(marker, index) {
            var row = marker.closest('li.row, tr');
            var parent = row ? row.parentNode : null;
            return {
                marker: marker,
                row: row,
                parent: parent,
                index: index,
                status: marker.getAttribute('data-supporttriage-status') || 'none',
                stale: marker.getAttribute('data-supporttriage-stale') === '1',
                hasAlert: marker.getAttribute('data-supporttriage-alert') === '1',
                title: normalize(marker.getAttribute('data-supporttriage-title') || (row ? row.textContent : '')),
                priorityKey: marker.getAttribute('data-supporttriage-priority') || 'normal',
                updatedTs: parseInt(marker.getAttribute('data-supporttriage-updated') || '0', 10) || 0,
                primaryAlert: marker.getAttribute('data-supporttriage-primary-alert') || '',
                topicId: marker.getAttribute('data-supporttriage-topic-id') || '0'
            };
        }).filter(function(entry) {
            return !!entry.row;
        });
    }

    function updateSelectionCount() {
        var countBox = document.getElementById('supporttriage-mcp-bulk-count');
        if (!countBox) {
            return;
        }

        var count = document.querySelectorAll('.supporttriage-mcp-bulk-checkbox:checked').length;
        countBox.textContent = ((panel && panel.getAttribute('data-supporttriage-bulk-selected-label')) || 'Selected') + ': ' + count;
    }

    function updateSmartCounts(entries, visibleCount) {
        var actionNow = 0;
        var awaitingTeam = 0;
        var awaitingAuthor = 0;
        entries.forEach(function(entry) {
            if (isActionNow(entry)) {
                actionNow++;
            }
            if (workflowBucket(entry) === 'awaiting_team') {
                awaitingTeam++;
            }
            if (workflowBucket(entry) === 'awaiting_author') {
                awaitingAuthor++;
            }
        });

        var actionNowBox = document.getElementById('supporttriage-mcp-count-action-now');
        var awaitingTeamBox = document.getElementById('supporttriage-mcp-count-awaiting-team');
        var awaitingAuthorBox = document.getElementById('supporttriage-mcp-count-awaiting-author');
        var visibleMeta = document.getElementById('supporttriage-mcp-visible-meta');

        if (actionNowBox) {
            actionNowBox.textContent = actionNow;
        }
        if (awaitingTeamBox) {
            awaitingTeamBox.textContent = awaitingTeam;
        }
        if (awaitingAuthorBox) {
            awaitingAuthorBox.textContent = awaitingAuthor;
        }
        if (visibleMeta) {
            visibleMeta.textContent = ((panel && panel.getAttribute('data-supporttriage-visible-label')) || 'Visible') + ': ' + visibleCount + '/' + entries.length;
        }
    }

    function sortEntries(entries, sortValue) {
        var groups = [];
        entries.forEach(function(entry) {
            var found = null;
            for (var i = 0; i < groups.length; i++) {
                if (groups[i].parent === entry.parent) {
                    found = groups[i];
                    break;
                }
            }
            if (!found) {
                found = { parent: entry.parent, rows: [] };
                groups.push(found);
            }
            found.rows.push(entry);
        });

        function compare(a, b) {
            var aUrgency = urgencyScore(a);
            var bUrgency = urgencyScore(b);
            var aPriority = priorityWeight(a.priorityKey);
            var bPriority = priorityWeight(b.priorityKey);
            var aTitle = a.title || '';
            var bTitle = b.title || '';

            if (sortValue === 'oldest_update') {
                if (a.updatedTs !== b.updatedTs) {
                    if (a.updatedTs === 0) { return 1; }
                    if (b.updatedTs === 0) { return -1; }
                    return a.updatedTs - b.updatedTs;
                }
            } else if (sortValue === 'newest_update') {
                if (a.updatedTs !== b.updatedTs) {
                    return b.updatedTs - a.updatedTs;
                }
            } else if (sortValue === 'priority') {
                if (aPriority !== bPriority) {
                    return bPriority - aPriority;
                }
                if (aUrgency !== bUrgency) {
                    return bUrgency - aUrgency;
                }
            } else if (sortValue === 'title') {
                if (aTitle < bTitle) { return -1; }
                if (aTitle > bTitle) { return 1; }
            } else {
                if (aUrgency !== bUrgency) {
                    return bUrgency - aUrgency;
                }
                if (a.stale !== b.stale) {
                    return a.stale ? -1 : 1;
                }
                if (aPriority !== bPriority) {
                    return bPriority - aPriority;
                }
                if (a.updatedTs !== b.updatedTs) {
                    if (a.updatedTs === 0) { return 1; }
                    if (b.updatedTs === 0) { return -1; }
                    return a.updatedTs - b.updatedTs;
                }
            }

            return a.index - b.index;
        }

        groups.forEach(function(group) {
            group.rows.sort(compare).forEach(function(entry) {
                if (entry.parent) {
                    entry.parent.appendChild(entry.row);
                }
            });
        });
    }

    function updateRows() {
        var filterValue = filter.value || 'all';
        var workflowValue = workflow ? (workflow.value || 'all') : 'all';
        var attentionValue = attention ? (attention.value || 'all') : 'all';
        var priorityValue = priority ? (priority.value || 'all') : 'all';
        var sortValue = sortMode ? (sortMode.value || 'urgency') : 'urgency';
        var searchValue = search ? normalize(search.value) : '';
        var entries = getEntries();
        var visibleCount = 0;

        entries.forEach(function(entry) {
            var statusMatch = filterValue === 'all' || entry.status === filterValue || (filterValue === 'stale' && entry.stale);
            var workflowMatch = workflowValue === 'all'
                || (workflowValue === 'action_now' && isActionNow(entry))
                || (workflowValue === 'awaiting_team' && workflowBucket(entry) === 'awaiting_team')
                || (workflowValue === 'awaiting_author' && workflowBucket(entry) === 'awaiting_author');
            var attentionMatch = attentionValue === 'all'
                || (attentionValue === 'alerts' && entry.hasAlert)
                || (attentionValue === 'stale' && entry.stale)
                || (attentionValue === 'attention' && (entry.hasAlert || entry.stale || entry.status === 'no_reply'))
                || (attentionValue === 'clean' && !entry.hasAlert && !entry.stale);
            var priorityMatch = priorityValue === 'all' || entry.priorityKey === priorityValue;
            var searchMatch = searchValue === '' || entry.title.indexOf(searchValue) !== -1;
            var show = statusMatch && workflowMatch && attentionMatch && priorityMatch && searchMatch;

            entry.row.style.display = show ? '' : 'none';
            entry.row.classList.toggle('supporttriage-row-alert', entry.hasAlert);
            entry.row.classList.toggle('supporttriage-row-stale', entry.stale);
            entry.row.classList.toggle('supporttriage-row-critical', entry.priorityKey === 'critical');
            entry.row.classList.toggle('supporttriage-row-author-return', entry.primaryAlert === 'author_return');
            entry.row.classList.toggle('supporttriage-row-sla', entry.primaryAlert === 'sla_warning');
            entry.row.classList.toggle('supporttriage-row-awaiting-author', workflowBucket(entry) === 'awaiting_author');
            entry.row.classList.toggle('supporttriage-row-action-now', isActionNow(entry));

            if (show) {
                visibleCount++;
            }
        });

        updateSmartCounts(entries, visibleCount);
        sortEntries(entries, sortValue);
        updateSelectionCount();

        try {
            var url = new URL(window.location.href);
            if (filterValue === 'all') {
                url.searchParams.delete('st_filter');
            } else {
                url.searchParams.set('st_filter', filterValue);
            }
            if (workflowValue === 'all') {
                url.searchParams.delete('st_workflow');
            } else {
                url.searchParams.set('st_workflow', workflowValue);
            }
            if (attentionValue === 'all') {
                url.searchParams.delete('st_attention');
            } else {
                url.searchParams.set('st_attention', attentionValue);
            }
            if (priorityValue === 'all') {
                url.searchParams.delete('st_priority');
            } else {
                url.searchParams.set('st_priority', priorityValue);
            }
            if (sortValue === 'urgency') {
                url.searchParams.delete('st_sort');
            } else {
                url.searchParams.set('st_sort', sortValue);
            }
            if (searchValue === '') {
                url.searchParams.delete('st_q');
            } else {
                url.searchParams.set('st_q', search.value);
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) {
            // Ignore history API issues.
        }
    }

    filter.value = filter.getAttribute('data-supporttriage-current-filter') || 'all';
    if (workflow) {
        workflow.value = workflow.getAttribute('data-supporttriage-current-workflow') || 'all';
    }
    if (attention) {
        attention.value = attention.getAttribute('data-supporttriage-current-attention') || 'all';
    }
    if (priority) {
        priority.value = priority.getAttribute('data-supporttriage-current-priority') || 'all';
    }
    if (sortMode) {
        sortMode.value = sortMode.getAttribute('data-supporttriage-current-sort') || 'urgency';
    }
    updateRows();

    filter.addEventListener('change', updateRows);
    if (workflow) {
        workflow.addEventListener('change', updateRows);
    }
    if (attention) {
        attention.addEventListener('change', updateRows);
    }
    if (priority) {
        priority.addEventListener('change', updateRows);
    }
    if (sortMode) {
        sortMode.addEventListener('change', updateRows);
    }
    if (search) {
        search.addEventListener('input', updateRows);
    }

    document.querySelectorAll('[data-supporttriage-set-filter]').forEach(function(button) {
        button.addEventListener('click', function() {
            filter.value = button.getAttribute('data-supporttriage-set-filter') || 'all';
            updateRows();
        });
    });

    document.querySelectorAll('[data-supporttriage-set-workflow]').forEach(function(button) {
        button.addEventListener('click', function() {
            if (workflow) {
                workflow.value = button.getAttribute('data-supporttriage-set-workflow') || 'all';
                updateRows();
            }
        });
    });

    var quickForm = document.getElementById('supporttriage-mcp-quick-form');
    if (quickForm) {
        var quickFlag = quickForm.querySelector('input[name="supporttriage_mcp_quick_status"]');
        var bulkFlag = quickForm.querySelector('input[name="supporttriage_mcp_bulk_apply"]');
        var topicIdField = quickForm.querySelector('input[name="supporttriage_topic_id"]');
        var quickStatusField = quickForm.querySelector('input[name="supporttriage_quick_status"]');
        var bulkStatusField = quickForm.querySelector('input[name="supporttriage_bulk_status"]');
        var bulkPriorityField = quickForm.querySelector('input[name="supporttriage_bulk_priority"]');
        var bulkTopicIds = document.getElementById('supporttriage-mcp-bulk-topic-ids');
        var bulkPanel = document.getElementById('supporttriage-mcp-bulk-panel');
        var bulkStatus = document.getElementById('supporttriage-mcp-bulk-status');
        var bulkPriority = document.getElementById('supporttriage-mcp-bulk-priority');
        var bulkApply = document.getElementById('supporttriage-mcp-bulk-apply');
        var selectVisible = document.getElementById('supporttriage-mcp-select-visible');
        var clearSelection = document.getElementById('supporttriage-mcp-clear-selection');

        function syncQuickFormAction() {
            try {
                quickForm.action = window.location.href;
            } catch (e) {
                // Ignore location assignment issues.
            }
        }

        document.querySelectorAll('.supporttriage-mcp-bulk-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateSelectionCount);
        });

        document.querySelectorAll('.supporttriage-mcp-quick').forEach(function(link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                var topicId = link.getAttribute('data-topic-id') || '0';
                var status = link.getAttribute('data-status') || '';
                quickFlag.value = '1';
                bulkFlag.value = '0';
                topicIdField.value = topicId;
                quickStatusField.value = status;
                bulkStatusField.value = '';
                bulkPriorityField.value = '';
                if (bulkTopicIds) {
                    bulkTopicIds.innerHTML = '';
                }
                syncQuickFormAction();
                quickForm.submit();
            });
        });

        if (selectVisible) {
            selectVisible.addEventListener('click', function() {
                document.querySelectorAll('.supporttriage-mcp-bulk-checkbox').forEach(function(checkbox) {
                    var row = checkbox.closest('li.row, tr');
                    if (!row || row.style.display !== 'none') {
                        checkbox.checked = true;
                    }
                });
                updateSelectionCount();
            });
        }

        if (clearSelection) {
            clearSelection.addEventListener('click', function() {
                document.querySelectorAll('.supporttriage-mcp-bulk-checkbox').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                updateSelectionCount();
            });
        }

        if (bulkApply) {
            bulkApply.addEventListener('click', function() {
                var selected = Array.prototype.slice.call(document.querySelectorAll('.supporttriage-mcp-bulk-checkbox:checked')).map(function(checkbox) {
                    return checkbox.value;
                }).filter(Boolean);
                var selectedStatus = bulkStatus ? (bulkStatus.value || '') : '';
                var selectedPriority = bulkPriority ? (bulkPriority.value || '') : '';

                if (!selected.length) {
                    window.alert(bulkPanel ? (bulkPanel.getAttribute('data-empty-selection') || '') : '');
                    return;
                }

                if (!selectedStatus && !selectedPriority) {
                    window.alert(bulkPanel ? (bulkPanel.getAttribute('data-empty-action') || '') : '');
                    return;
                }

                if (bulkPanel) {
                    var confirmText = bulkPanel.getAttribute('data-confirm') || '';
                    if (confirmText && !window.confirm(confirmText)) {
                        return;
                    }
                }

                quickFlag.value = '0';
                bulkFlag.value = '1';
                topicIdField.value = '0';
                quickStatusField.value = '';
                bulkStatusField.value = selectedStatus;
                bulkPriorityField.value = selectedPriority;

                if (bulkTopicIds) {
                    bulkTopicIds.innerHTML = '';
                    selected.forEach(function(topicId) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'supporttriage_topic_ids[]';
                        input.value = topicId;
                        bulkTopicIds.appendChild(input);
                    });
                }

                syncQuickFormAction();
                quickForm.submit();
            });
        }

        updateSelectionCount();
    }
});
