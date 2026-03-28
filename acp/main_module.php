<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    /** @var \phpbb\config\config */
    protected $config;

    public function main($id, $mode)
    {
        global $config, $db, $request, $template, $user, $table_prefix;

        $user->add_lang_ext('mundophpbb/supporttriage', 'common');

        $this->config = $config;

        // Redireciona modo antigo 'settings' para dashboard
        if ($mode === 'settings' || empty($mode)) {
            $mode = 'dashboard';
        }

        $page_meta = $this->get_page_meta($user, $mode);

        $this->tpl_name   = $page_meta['template'];
        $this->page_title = $page_meta['title'];

        add_form_key('mundophpbb_supporttriage');

        $snippets_supported = isset($config['mundophpbb_supporttriage_snippets_enable']);
        if ($snippets_supported) {
            $this->ensure_default_snippets($db, $table_prefix, $user);
        }

        $logs_supported = !empty($config['mundophpbb_supporttriage_logs_enable']);
        $kb_supported   = isset($config['mundophpbb_supporttriage_kb_enable']);
        $queue_supported = isset($config['mundophpbb_supporttriage_queue_enable']) && !empty($config['mundophpbb_supporttriage_queue_enable']);

        $export_filters = $this->get_export_filters($request);

        if ($this->handle_export_request(
            $db,
            $table_prefix,
            $user,
            $request,
            $logs_supported,
            $kb_supported,
            $queue_supported,
            $export_filters
        )) {
            return;
        }

        $this->handle_clear_logs_request($db, $table_prefix, $user, $request, $logs_supported);
        $this->handle_clear_notices_request($db, $table_prefix, $user, $request);
        $this->handle_repair_topics_request($db, $table_prefix, $user, $request);

        if ($page_meta['show_submit'] && $request->is_set_post('submit')) {
            if (!check_form_key('mundophpbb_supporttriage')) {
                trigger_error('FORM_INVALID');
            }

            switch ($mode) {
                case 'general':
                    $this->save_general_settings($config, $request);
                    break;

                case 'automation':
                    $this->save_automation_settings($config, $request);
                    break;

                case 'content':
                    $this->save_content_settings($config, $request, $db, $table_prefix, $user, $snippets_supported);
                    break;
            }

            trigger_error($user->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        // ====================== ESTATÍSTICAS E DADOS PARA O TEMPLATE ======================
        $counts = [
            'new'          => 0,
            'in_progress'  => 0,
            'waiting_reply'=> 0,
            'solved'       => 0,
            'no_reply'     => 0,
        ];

        $status_table = $table_prefix . 'supporttriage_topics';
        $sql = 'SELECT status_key, COUNT(topic_id) AS total FROM ' . $status_table . ' GROUP BY status_key';
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result)) {
            if (isset($counts[$row['status_key']])) {
                $counts[$row['status_key']] = (int) $row['total'];
            }
        }
        $db->sql_freeresult($result);

        $priority_counts = [
            'low'      => 0,
            'normal'   => 0,
            'high'     => 0,
            'critical' => 0,
        ];

        if (isset($config['mundophpbb_supporttriage_priority_enable'])) {
            $sql = 'SELECT priority_key, COUNT(topic_id) AS total FROM ' . $status_table . ' GROUP BY priority_key';
            $result = $db->sql_query($sql);

            while ($row = $db->sql_fetchrow($result)) {
                if (isset($priority_counts[$row['priority_key']])) {
                    $priority_counts[$row['priority_key']] = (int) $row['total'];
                }
            }
            $db->sql_freeresult($result);
        }

        $kb_links_count = 0;
        if (isset($config['mundophpbb_supporttriage_kb_enable'])) {
            $sql = 'SELECT COUNT(source_topic_id) AS total FROM ' . $table_prefix . 'supporttriage_kb_links';
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            $kb_links_count = $row ? (int) $row['total'] : 0;
        }

        $logs_count = 0;
        $log_rows = [];
        if ($logs_supported) {
            $sql = 'SELECT COUNT(log_id) AS total FROM ' . $this->logs_table($table_prefix);
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            $logs_count = $row ? (int) $row['total'] : 0;
            $log_rows = $this->get_recent_logs($db, $table_prefix, $user, 15);
        }

        $snippet_rows = [];
        if ($snippets_supported) {
            $snippet_rows = $this->get_snippets($db, $table_prefix);
        }

        $metric_rows      = $this->build_metrics_rows($db, $table_prefix, $user, $logs_supported, $kb_supported);
        $moderator_rows   = $this->get_top_moderators($db, $table_prefix, $user, 30, 10);
        $slow_rows        = $this->get_slowest_topics($db, $table_prefix, $user, 10);
        $health_rows      = $this->build_health_rows($db, $table_prefix, $user, $logs_supported, $kb_supported);
        $approval_rows    = $this->build_approval_rows($db, $table_prefix, $user);
        $approval_summary = $this->build_approval_summary($approval_rows);
        $active_notice_count = $this->count_active_notices($db, $table_prefix);
        $tracked_forum_ids = $this->csv_to_int_list($config['mundophpbb_supporttriage_forums']);
        $missing_topic_rows_count = $this->count_missing_topic_rows($db, $table_prefix, $tracked_forum_ids);

        $open_topics_count   = (int) ($counts['new'] + $counts['in_progress'] + $counts['waiting_reply'] + $counts['no_reply']);
        $urgent_topics_count = (int) ($priority_counts['high'] + $priority_counts['critical']);
        $tracked_forums_count = count($tracked_forum_ids);
        $kb_forum_id = isset($config['mundophpbb_supporttriage_kb_forum']) ? (int) $config['mundophpbb_supporttriage_kb_forum'] : 0;
        $kb_health_ok = !$kb_supported || empty($config['mundophpbb_supporttriage_kb_enable']) || $kb_forum_id > 0;
        $dashboard_health_ok = ($tracked_forums_count > 0 && $missing_topic_rows_count === 0 && $kb_health_ok);

        if ($tracked_forums_count === 0) {
            $dashboard_health_details = $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TRACKED_FORUMS_WARN');
        } else if ($missing_topic_rows_count > 0) {
            $dashboard_health_details = $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TOPIC_ROWS_WARN', (int) $missing_topic_rows_count);
        } else if (!$kb_health_ok) {
            $dashboard_health_details = $user->lang('ACP_SUPPORTTRIAGE_HEALTH_KB_FORUM_WARN');
        } else {
            $dashboard_health_details = $user->lang('ACP_SUPPORTTRIAGE_DASHBOARD_HEALTH_READY');
        }

        // Assign blocks
        foreach ($snippet_rows as $index => $snippet) {
            $template->assign_block_vars('supporttriage_snippet_rows', [
                'ROW_INDEX'  => (int) $index,
                'TITLE'      => $this->html($snippet['snippet_title']),
                'TEXT'       => $this->html($snippet['snippet_text']),
                'SORT_ORDER' => (int) $snippet['sort_order'],
                'S_ACTIVE'   => !empty($snippet['is_active']),
            ]);
        }

        foreach ($metric_rows as $metric_row) {
            $template->assign_block_vars('supporttriage_metric_rows', $metric_row);
        }

        foreach ($moderator_rows as $moderator_row) {
            $template->assign_block_vars('supporttriage_moderator_rows', $moderator_row);
        }

        foreach ($slow_rows as $slow_row) {
            $template->assign_block_vars('supporttriage_slow_rows', $slow_row);
        }

        foreach ($log_rows as $log_row) {
            $template->assign_block_vars('supporttriage_log_rows', [
                'ACTION_LABEL' => $log_row['action_label'],
                'DETAILS'      => $log_row['details'],
                'USERNAME'     => $log_row['username'],
                'TIME'         => $log_row['time'],
                'TOPIC_LINK'   => $log_row['topic_link'],
            ]);
        }

        foreach ($health_rows as $health_row) {
            $template->assign_block_vars('supporttriage_health_rows', $health_row);
        }

        foreach ($approval_rows as $approval_row) {
            $template->assign_block_vars('supporttriage_approval_rows', $approval_row);
        }

        $template->assign_vars([
            'PAGE_TITLE'                        => $this->page_title,
            'PAGE_EXPLAIN'                      => $page_meta['explain'],
            'U_SUPPORTTRIAGE_PAGE_DASHBOARD'    => $this->build_mode_url($this->u_action, 'dashboard'),
            'U_SUPPORTTRIAGE_PAGE_GENERAL'      => $this->build_mode_url($this->u_action, 'general'),
            'U_SUPPORTTRIAGE_PAGE_AUTOMATION'   => $this->build_mode_url($this->u_action, 'automation'),
            'U_SUPPORTTRIAGE_PAGE_CONTENT'      => $this->build_mode_url($this->u_action, 'content'),
            'U_SUPPORTTRIAGE_PAGE_DIAGNOSTICS'  => $this->build_mode_url($this->u_action, 'diagnostics'),
            'U_ACTION'                          => $this->u_action,

            'SUPPORTTRIAGE_ENABLE'              => !empty($config['mundophpbb_supporttriage_enable']),
            'SUPPORTTRIAGE_FORUMS'              => $this->html($config['mundophpbb_supporttriage_forums']),
            'SUPPORTTRIAGE_AUTO_INSERT'         => !empty($config['mundophpbb_supporttriage_auto_insert']),
            'SUPPORTTRIAGE_PREFIX'              => $this->html($config['mundophpbb_supporttriage_prefix']),

            'SUPPORTTRIAGE_STATUS_ENABLE'       => !empty($config['mundophpbb_supporttriage_status_enable']),
            'SUPPORTTRIAGE_DEFAULT_STATUS'      => (string) $config['mundophpbb_supporttriage_default_status'],

            'S_SUPPORTTRIAGE_PRIORITY_SUPPORTED'=> isset($config['mundophpbb_supporttriage_priority_enable']),
            'SUPPORTTRIAGE_PRIORITY_ENABLE'     => isset($config['mundophpbb_supporttriage_priority_enable']) ? !empty($config['mundophpbb_supporttriage_priority_enable']) : true,
            'SUPPORTTRIAGE_DEFAULT_PRIORITY'    => isset($config['mundophpbb_supporttriage_default_priority']) ? (string) $config['mundophpbb_supporttriage_default_priority'] : 'normal',

            'S_SUPPORTTRIAGE_PRIORITY_AUTOMATION_SUPPORTED' => isset($config['mundophpbb_supporttriage_priority_auto_enable']),
            'SUPPORTTRIAGE_PRIORITY_AUTO_ENABLE'            => isset($config['mundophpbb_supporttriage_priority_auto_enable']) ? !empty($config['mundophpbb_supporttriage_priority_auto_enable']) : false,
            'SUPPORTTRIAGE_PRIORITY_AUTO_STALE_DAYS'        => isset($config['mundophpbb_supporttriage_priority_auto_stale_days']) ? (int) $config['mundophpbb_supporttriage_priority_auto_stale_days'] : 3,
            'SUPPORTTRIAGE_PRIORITY_AUTO_STALE_TARGET'      => isset($config['mundophpbb_supporttriage_priority_auto_stale_target']) ? (string) $config['mundophpbb_supporttriage_priority_auto_stale_target'] : 'high',
            'SUPPORTTRIAGE_PRIORITY_AUTO_FORUMS'            => isset($config['mundophpbb_supporttriage_priority_auto_forums']) ? $this->html($config['mundophpbb_supporttriage_priority_auto_forums']) : '',
            'SUPPORTTRIAGE_PRIORITY_AUTO_FORUMS_TARGET'     => isset($config['mundophpbb_supporttriage_priority_auto_forums_target']) ? (string) $config['mundophpbb_supporttriage_priority_auto_forums_target'] : 'critical',
            'SUPPORTTRIAGE_PRIORITY_AUTO_ISSUE_TYPES'       => isset($config['mundophpbb_supporttriage_priority_auto_issue_types']) ? $this->html($config['mundophpbb_supporttriage_priority_auto_issue_types']) : 'permissions,email',
            'SUPPORTTRIAGE_PRIORITY_AUTO_ISSUE_TARGET'      => isset($config['mundophpbb_supporttriage_priority_auto_issue_target']) ? (string) $config['mundophpbb_supporttriage_priority_auto_issue_target'] : 'high',

            'SUPPORTTRIAGE_COUNT_PRIORITY_LOW'      => $priority_counts['low'],
            'SUPPORTTRIAGE_COUNT_PRIORITY_NORMAL'   => $priority_counts['normal'],
            'SUPPORTTRIAGE_COUNT_PRIORITY_HIGH'     => $priority_counts['high'],
            'SUPPORTTRIAGE_COUNT_PRIORITY_CRITICAL' => $priority_counts['critical'],

            'SUPPORTTRIAGE_COUNT_NEW'           => $counts['new'],
            'SUPPORTTRIAGE_COUNT_IN_PROGRESS'   => $counts['in_progress'],
            'SUPPORTTRIAGE_COUNT_WAITING_REPLY' => $counts['waiting_reply'],
            'SUPPORTTRIAGE_COUNT_SOLVED'        => $counts['solved'],
            'SUPPORTTRIAGE_COUNT_NO_REPLY'      => $counts['no_reply'],

            'S_SUPPORTTRIAGE_AUTOMATION_SUPPORTED' => isset($config['mundophpbb_supporttriage_automation_enable']),
            'SUPPORTTRIAGE_AUTOMATION_ENABLE'      => isset($config['mundophpbb_supporttriage_automation_enable']) ? !empty($config['mundophpbb_supporttriage_automation_enable']) : false,
            'SUPPORTTRIAGE_AUTO_WAITING_REPLY'     => isset($config['mundophpbb_supporttriage_auto_waiting_reply']) ? !empty($config['mundophpbb_supporttriage_auto_waiting_reply']) : true,
            'SUPPORTTRIAGE_AUTO_IN_PROGRESS'       => isset($config['mundophpbb_supporttriage_auto_in_progress']) ? !empty($config['mundophpbb_supporttriage_auto_in_progress']) : true,
            'SUPPORTTRIAGE_AUTO_NO_REPLY_DAYS'     => isset($config['mundophpbb_supporttriage_auto_no_reply_days']) ? (int) $config['mundophpbb_supporttriage_auto_no_reply_days'] : 7,

            'S_SUPPORTTRIAGE_QUEUE_SUPPORTED'   => isset($config['mundophpbb_supporttriage_queue_enable']),
            'SUPPORTTRIAGE_QUEUE_ENABLE'        => isset($config['mundophpbb_supporttriage_queue_enable']) ? !empty($config['mundophpbb_supporttriage_queue_enable']) : true,
            'SUPPORTTRIAGE_QUEUE_STALE_DAYS'    => isset($config['mundophpbb_supporttriage_queue_stale_days']) ? (int) $config['mundophpbb_supporttriage_queue_stale_days'] : 5,

            'S_SUPPORTTRIAGE_NOTIFICATIONS_SUPPORTED' => isset($config['mundophpbb_supporttriage_notifications_enable']),
            'SUPPORTTRIAGE_NOTIFICATIONS_ENABLE'      => isset($config['mundophpbb_supporttriage_notifications_enable']) ? !empty($config['mundophpbb_supporttriage_notifications_enable']) : true,
            'SUPPORTTRIAGE_ALERT_AUTHOR_RETURN'       => isset($config['mundophpbb_supporttriage_alert_author_return']) ? !empty($config['mundophpbb_supporttriage_alert_author_return']) : true,
            'SUPPORTTRIAGE_ALERT_NO_REPLY'            => isset($config['mundophpbb_supporttriage_alert_no_reply']) ? !empty($config['mundophpbb_supporttriage_alert_no_reply']) : true,
            'SUPPORTTRIAGE_ALERT_SLA_WARNING'         => isset($config['mundophpbb_supporttriage_alert_sla_warning']) ? !empty($config['mundophpbb_supporttriage_alert_sla_warning']) : true,
            'SUPPORTTRIAGE_ALERT_SLA_HOURS'           => isset($config['mundophpbb_supporttriage_alert_sla_hours']) ? (int) $config['mundophpbb_supporttriage_alert_sla_hours'] : 24,
            'SUPPORTTRIAGE_ALERT_KB_LINKED'           => isset($config['mundophpbb_supporttriage_alert_kb_linked']) ? !empty($config['mundophpbb_supporttriage_alert_kb_linked']) : true,

            'S_SUPPORTTRIAGE_KB_SUPPORTED'      => isset($config['mundophpbb_supporttriage_kb_enable']),
            'SUPPORTTRIAGE_KB_ENABLE'           => isset($config['mundophpbb_supporttriage_kb_enable']) ? !empty($config['mundophpbb_supporttriage_kb_enable']) : false,
            'SUPPORTTRIAGE_KB_FORUM'            => isset($config['mundophpbb_supporttriage_kb_forum']) ? $this->html($config['mundophpbb_supporttriage_kb_forum']) : '',
            'SUPPORTTRIAGE_KB_PREFIX'           => isset($config['mundophpbb_supporttriage_kb_prefix']) ? $this->html($config['mundophpbb_supporttriage_kb_prefix']) : '[KB Draft]',
            'SUPPORTTRIAGE_KB_LOCK'             => isset($config['mundophpbb_supporttriage_kb_lock']) ? !empty($config['mundophpbb_supporttriage_kb_lock']) : true,
            'SUPPORTTRIAGE_KB_LINKS_COUNT'      => $kb_links_count,

            'S_SUPPORTTRIAGE_SNIPPETS_SUPPORTED'=> $snippets_supported,
            'SUPPORTTRIAGE_SNIPPETS_ENABLE'     => $snippets_supported ? !empty($config['mundophpbb_supporttriage_snippets_enable']) : false,
            'SUPPORTTRIAGE_SNIPPETS_COUNT'      => count($snippet_rows),
            'SUPPORTTRIAGE_NEW_SNIPPET_SORT'    => count($snippet_rows) + 1,

            'S_SUPPORTTRIAGE_LOGS_SUPPORTED'    => $logs_supported,
            'S_SUPPORTTRIAGE_HAS_LOGS'          => ($logs_count > 0),
            'SUPPORTTRIAGE_LOGS_COUNT'          => $logs_count,

            'S_SUPPORTTRIAGE_NOTICE_FEED_SUPPORTED' => isset($config['mundophpbb_supporttriage_notice_feed_enable']),
            'S_SUPPORTTRIAGE_HAS_NOTICES'       => ($active_notice_count > 0),
            'SUPPORTTRIAGE_ACTIVE_NOTICE_COUNT' => $active_notice_count,

            'SUPPORTTRIAGE_MISSING_TOPIC_ROWS_COUNT' => $missing_topic_rows_count,
            'SUPPORTTRIAGE_DASHBOARD_OPEN_TOPICS'    => $open_topics_count,
            'SUPPORTTRIAGE_DASHBOARD_URGENT_TOPICS'  => $urgent_topics_count,
            'SUPPORTTRIAGE_DASHBOARD_TRACKED_FORUMS' => $tracked_forums_count,
            'SUPPORTTRIAGE_DASHBOARD_HEALTH_LABEL'   => $dashboard_health_ok ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_OK') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_WARN'),
            'SUPPORTTRIAGE_DASHBOARD_HEALTH_DETAILS' => $dashboard_health_details,
            'SUPPORTTRIAGE_DASHBOARD_QUEUE_STATUS'   => $queue_supported ? $user->lang('YES') : $user->lang('NO'),
            'SUPPORTTRIAGE_DASHBOARD_LAST_PERIOD'    => $metric_rows ? $metric_rows[0]['PERIOD'] : $user->lang('ACP_SUPPORTTRIAGE_METRICS_NOT_AVAILABLE'),
            'SUPPORTTRIAGE_DASHBOARD_LAST_ACTIONS'   => $metric_rows ? $metric_rows[0]['ACTIONS_COUNT'] : '0',
            'SUPPORTTRIAGE_DASHBOARD_LAST_SOLVED'    => $metric_rows ? $metric_rows[0]['TOPICS_SOLVED'] : '0',

            'S_SUPPORTTRIAGE_HEALTH_SUPPORTED'   => true,
            'S_SUPPORTTRIAGE_APPROVAL_SUPPORTED' => true,
            'SUPPORTTRIAGE_APPROVAL_TRACKED_FORUMS'   => $approval_summary['tracked_forums'],
            'SUPPORTTRIAGE_APPROVAL_UNAPPROVED_TOPICS'=> $approval_summary['unapproved_topics'],
            'SUPPORTTRIAGE_APPROVAL_UNAPPROVED_POSTS' => $approval_summary['unapproved_posts'],
            'SUPPORTTRIAGE_APPROVAL_REQUIRES_REVIEW'  => $approval_summary['requires_review'],

            'S_SUPPORTTRIAGE_METRICS_SUPPORTED'       => true,
            'SUPPORTTRIAGE_METRIC_MODERATORS_COUNT'   => count($moderator_rows),
            'SUPPORTTRIAGE_METRIC_SLOW_COUNT'         => count($slow_rows),

            'S_SUPPORTTRIAGE_EXPORTS_SUPPORTED'       => true,
            'S_SUPPORTTRIAGE_EXPORT_LOGS_SUPPORTED'   => $logs_supported,
            'S_SUPPORTTRIAGE_EXPORT_QUEUE_SUPPORTED'  => $queue_supported,
            'S_SUPPORTTRIAGE_EXPORT_PRIORITY_SUPPORTED'=> isset($config['mundophpbb_supporttriage_priority_enable']),
            'SUPPORTTRIAGE_EXPORT_PERIOD'   => $export_filters['period'],
            'SUPPORTTRIAGE_EXPORT_ACTION'   => $export_filters['action'],
            'SUPPORTTRIAGE_EXPORT_STATUS'   => $export_filters['status'],
            'SUPPORTTRIAGE_EXPORT_PRIORITY' => $export_filters['priority'],
            'SUPPORTTRIAGE_EXPORT_FORUMS'   => $this->html($export_filters['forums_csv']),
            'SUPPORTTRIAGE_EXPORT_STALLED_ONLY' => !empty($export_filters['stalled_only']),
        ]);
    }

    public function get_module_info()
    {
        global $user;
        return [
            'title' => $user->lang('ACP_SUPPORTTRIAGE_TITLE'),
            'modes' => ['dashboard', 'general', 'automation', 'content', 'diagnostics'],
            'auth'  => 'acl_a_supporttriage_manage',
        ];
    }

    protected function get_page_meta($user, $mode)
    {
        switch ($mode) {
            case 'general':
                return [
                    'title'       => $user->lang('ACP_SUPPORTTRIAGE_GENERAL_TAB'),
                    'explain'     => $user->lang('ACP_SUPPORTTRIAGE_GENERAL_EXPLAIN'),
                    'show_submit' => true,
                    'template'    => 'acp_supporttriage_general',
                ];

            case 'automation':
                return [
                    'title'       => $user->lang('ACP_SUPPORTTRIAGE_AUTOMATION_TAB'),
                    'explain'     => $user->lang('ACP_SUPPORTTRIAGE_AUTOMATION_EXPLAIN_TAB'),
                    'show_submit' => true,
                    'template'    => 'acp_supporttriage_automation',
                ];

            case 'content':
                return [
                    'title'       => $user->lang('ACP_SUPPORTTRIAGE_CONTENT_TAB'),
                    'explain'     => $user->lang('ACP_SUPPORTTRIAGE_CONTENT_EXPLAIN'),
                    'show_submit' => true,
                    'template'    => 'acp_supporttriage_content',
                ];

            case 'diagnostics':
                return [
                    'title'       => $user->lang('ACP_SUPPORTTRIAGE_DIAGNOSTICS_TAB'),
                    'explain'     => $user->lang('ACP_SUPPORTTRIAGE_DIAGNOSTICS_EXPLAIN_TAB'),
                    'show_submit' => false,
                    'template'    => 'acp_supporttriage_diagnostics',
                ];

            case 'dashboard':
            default:
                return [
                    'title'       => $user->lang('ACP_SUPPORTTRIAGE_DASHBOARD_TAB'),
                    'explain'     => $user->lang('ACP_SUPPORTTRIAGE_DASHBOARD_EXPLAIN'),
                    'show_submit' => false,
                    'template'    => 'acp_supporttriage_dashboard',
                ];
        }
    }

    protected function build_mode_url($url, $mode)
    {
        $url = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');

        if (preg_match('/([?&])mode=[^&]+/', $url)) {
            $url = preg_replace('/([?&])mode=[^&]+/', '$1mode=' . $mode, $url, 1);
        } else {
            $url .= ((strpos($url, '?') === false) ? '?' : '&') . 'mode=' . $mode;
        }

        return str_replace('&', '&amp;', $url);
    }

    protected function save_general_settings($config, $request)
    {
        $forums = preg_replace('/[^0-9,\s]/', '', $request->variable('mundophpbb_supporttriage_forums', '', true));
        $prefix = trim($request->variable('mundophpbb_supporttriage_prefix', '', true));
        $default_status = $request->variable('mundophpbb_supporttriage_default_status', 'new', true);
        $allowed_statuses = ['new', 'in_progress', 'waiting_reply', 'solved', 'no_reply'];

        if (!in_array($default_status, $allowed_statuses, true)) {
            $default_status = 'new';
        }

        $config->set('mundophpbb_supporttriage_enable', $request->variable('mundophpbb_supporttriage_enable', 0));
        $config->set('mundophpbb_supporttriage_forums', trim($forums));
        $config->set('mundophpbb_supporttriage_auto_insert', $request->variable('mundophpbb_supporttriage_auto_insert', 0));
        $config->set('mundophpbb_supporttriage_prefix', $prefix);
        $config->set('mundophpbb_supporttriage_status_enable', $request->variable('mundophpbb_supporttriage_status_enable', 0));
        $config->set('mundophpbb_supporttriage_default_status', $default_status);

        if (isset($config['mundophpbb_supporttriage_priority_enable'])) {
            $default_priority = $request->variable('mundophpbb_supporttriage_default_priority', 'normal', true);
            $allowed_priorities = ['low', 'normal', 'high', 'critical'];

            if (!in_array($default_priority, $allowed_priorities, true)) {
                $default_priority = 'normal';
            }

            $config->set('mundophpbb_supporttriage_priority_enable', $request->variable('mundophpbb_supporttriage_priority_enable', 0));
            $config->set('mundophpbb_supporttriage_default_priority', $default_priority);
        }

        if (isset($config['mundophpbb_supporttriage_queue_enable'])) {
            $queue_stale_days = max(0, (int) $request->variable('mundophpbb_supporttriage_queue_stale_days', 5));
            $config->set('mundophpbb_supporttriage_queue_enable', $request->variable('mundophpbb_supporttriage_queue_enable', 0));
            $config->set('mundophpbb_supporttriage_queue_stale_days', $queue_stale_days);
        }

        if (isset($config['mundophpbb_supporttriage_notifications_enable'])) {
            $sla_hours = max(1, (int) $request->variable('mundophpbb_supporttriage_alert_sla_hours', 24));
            $config->set('mundophpbb_supporttriage_notifications_enable', $request->variable('mundophpbb_supporttriage_notifications_enable', 0));
            $config->set('mundophpbb_supporttriage_alert_author_return', $request->variable('mundophpbb_supporttriage_alert_author_return', 0));
            $config->set('mundophpbb_supporttriage_alert_no_reply', $request->variable('mundophpbb_supporttriage_alert_no_reply', 0));
            $config->set('mundophpbb_supporttriage_alert_sla_warning', $request->variable('mundophpbb_supporttriage_alert_sla_warning', 0));
            $config->set('mundophpbb_supporttriage_alert_sla_hours', $sla_hours);
            $config->set('mundophpbb_supporttriage_alert_kb_linked', $request->variable('mundophpbb_supporttriage_alert_kb_linked', 0));
        }
    }

    protected function save_automation_settings($config, $request)
    {
        if (isset($config['mundophpbb_supporttriage_priority_auto_enable'])) {
            $auto_stale_days = max(0, (int) $request->variable('mundophpbb_supporttriage_priority_auto_stale_days', 3));
            $auto_forums = preg_replace('/[^0-9,\s]/', '', $request->variable('mundophpbb_supporttriage_priority_auto_forums', '', true));
            $auto_issue_types = $this->normalize_issue_type_csv($request->variable('mundophpbb_supporttriage_priority_auto_issue_types', '', true));
            $allowed_priorities = ['low', 'normal', 'high', 'critical'];
            $auto_stale_target = $request->variable('mundophpbb_supporttriage_priority_auto_stale_target', 'high', true);
            $auto_forums_target = $request->variable('mundophpbb_supporttriage_priority_auto_forums_target', 'critical', true);
            $auto_issue_target = $request->variable('mundophpbb_supporttriage_priority_auto_issue_target', 'high', true);

            if (!in_array($auto_stale_target, $allowed_priorities, true)) $auto_stale_target = 'high';
            if (!in_array($auto_forums_target, $allowed_priorities, true)) $auto_forums_target = 'critical';
            if (!in_array($auto_issue_target, $allowed_priorities, true)) $auto_issue_target = 'high';

            $config->set('mundophpbb_supporttriage_priority_auto_enable', $request->variable('mundophpbb_supporttriage_priority_auto_enable', 0));
            $config->set('mundophpbb_supporttriage_priority_auto_stale_days', $auto_stale_days);
            $config->set('mundophpbb_supporttriage_priority_auto_stale_target', $auto_stale_target);
            $config->set('mundophpbb_supporttriage_priority_auto_forums', trim($auto_forums));
            $config->set('mundophpbb_supporttriage_priority_auto_forums_target', $auto_forums_target);
            $config->set('mundophpbb_supporttriage_priority_auto_issue_types', $auto_issue_types);
            $config->set('mundophpbb_supporttriage_priority_auto_issue_target', $auto_issue_target);
        }

        if (isset($config['mundophpbb_supporttriage_automation_enable'])) {
            $auto_days = max(0, (int) $request->variable('mundophpbb_supporttriage_auto_no_reply_days', 7));
            $config->set('mundophpbb_supporttriage_automation_enable', $request->variable('mundophpbb_supporttriage_automation_enable', 0));
            $config->set('mundophpbb_supporttriage_auto_waiting_reply', $request->variable('mundophpbb_supporttriage_auto_waiting_reply', 0));
            $config->set('mundophpbb_supporttriage_auto_in_progress', $request->variable('mundophpbb_supporttriage_auto_in_progress', 0));
            $config->set('mundophpbb_supporttriage_auto_no_reply_days', $auto_days);
        }
    }

    protected function save_content_settings($config, $request, $db, $table_prefix, $user, $snippets_supported)
    {
        if (isset($config['mundophpbb_supporttriage_kb_enable'])) {
            $kb_forum = preg_replace('/[^0-9]/', '', $request->variable('mundophpbb_supporttriage_kb_forum', '', true));
            $kb_prefix = trim($request->variable('mundophpbb_supporttriage_kb_prefix', '', true));
            if ($kb_prefix === '') {
                $kb_prefix = '[KB Draft]';
            }

            $config->set('mundophpbb_supporttriage_kb_enable', $request->variable('mundophpbb_supporttriage_kb_enable', 0));
            $config->set('mundophpbb_supporttriage_kb_forum', $kb_forum);
            $config->set('mundophpbb_supporttriage_kb_prefix', $kb_prefix);
            $config->set('mundophpbb_supporttriage_kb_lock', $request->variable('mundophpbb_supporttriage_kb_lock', 0));
        }

        if ($snippets_supported) {
            $config->set('mundophpbb_supporttriage_snippets_enable', $request->variable('mundophpbb_supporttriage_snippets_enable', 0));
            $this->save_snippets($db, $table_prefix, $user, $request);
        }
    }

    protected function normalize_issue_type_csv($value)
    {
        $value = strtolower((string) $value);
        $parts = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $allowed = ['general', 'extension', 'update', 'style', 'permissions', 'email'];
        $clean = [];

        foreach ($parts as $part) {
            if (in_array($part, $allowed, true)) {
                $clean[$part] = $part;
            }
        }
        return implode(',', array_values($clean));
    }

    protected function notices_table($table_prefix)  { return $table_prefix . 'supporttriage_notices'; }
    protected function snippets_table($table_prefix){ return $table_prefix . 'supporttriage_snippets'; }
    protected function logs_table($table_prefix)    { return $table_prefix . 'supporttriage_logs'; }
    protected function kb_links_table($table_prefix){ return $table_prefix . 'supporttriage_kb_links'; }
    protected function status_table($table_prefix)  { return $table_prefix . 'supporttriage_topics'; }

    protected function handle_clear_notices_request($db, $table_prefix, $user, $request)
    {
        if (!isset($this->config['mundophpbb_supporttriage_notice_feed_enable']) || !$request->is_set_post('supporttriage_clear_notices')) {
            return;
        }

        if (!check_form_key('mundophpbb_supporttriage')) {
            trigger_error('FORM_INVALID');
        }

        $sql = 'DELETE FROM ' . $this->notices_table($table_prefix);
        $db->sql_query($sql);

        trigger_error($user->lang('ACP_SUPPORTTRIAGE_NOTICES_CLEARED') . adm_back_link($this->u_action));
    }

    protected function handle_repair_topics_request($db, $table_prefix, $user, $request)
    {
        if (!$request->is_set_post('supporttriage_repair_topics')) {
            return;
        }

        if (!check_form_key('mundophpbb_supporttriage')) {
            trigger_error('FORM_INVALID');
        }

        $forum_ids = $this->csv_to_int_list($this->config['mundophpbb_supporttriage_forums']);
        if (empty($forum_ids)) {
            trigger_error($user->lang('ACP_SUPPORTTRIAGE_REPAIR_NOT_CONFIGURED') . adm_back_link($this->u_action));
        }

        $repaired = $this->repair_missing_topic_rows($db, $table_prefix, $forum_ids);
        trigger_error($user->lang('ACP_SUPPORTTRIAGE_REPAIR_DONE', (int) $repaired) . adm_back_link($this->u_action));
    }

    protected function repair_missing_topic_rows($db, $table_prefix, array $forum_ids)
    {
        if (empty($forum_ids)) return 0;

        $default_status = isset($this->config['mundophpbb_supporttriage_default_status']) ? (string) $this->config['mundophpbb_supporttriage_default_status'] : 'new';
        $default_priority = isset($this->config['mundophpbb_supporttriage_default_priority']) ? (string) $this->config['mundophpbb_supporttriage_default_priority'] : 'normal';

        $sql = 'SELECT t.topic_id, t.forum_id, t.topic_poster, t.topic_time
                FROM ' . TOPICS_TABLE . ' t
                LEFT JOIN ' . $this->status_table($table_prefix) . ' st ON st.topic_id = t.topic_id
                WHERE t.topic_moved_id = 0
                    AND ' . $db->sql_in_set('t.forum_id', array_map('intval', $forum_ids)) . '
                    AND st.topic_id IS NULL
                ORDER BY t.topic_id ASC';

        $result = $db->sql_query($sql);
        $count = 0;

        while ($row = $db->sql_fetchrow($result)) {
            $sql_ary = [
                'topic_id'       => (int) $row['topic_id'],
                'forum_id'       => (int) $row['forum_id'],
                'status_key'     => $default_status,
                'status_updated' => !empty($row['topic_time']) ? (int) $row['topic_time'] : time(),
                'status_user_id' => 0,
            ];

            if (isset($this->config['mundophpbb_supporttriage_default_priority'])) {
                $sql_ary['priority_key'] = $default_priority;
            }

            if (isset($this->config['mundophpbb_supporttriage_automation_enable'])) {
                $sql_ary['topic_author_id'] = !empty($row['topic_poster']) ? (int) $row['topic_poster'] : 0;
                $sql_ary['last_author_reply'] = 0;
                $sql_ary['last_staff_reply']  = 0;
            }

            $sql_insert = 'INSERT INTO ' . $this->status_table($table_prefix) . ' ' . $db->sql_build_array('INSERT', $sql_ary);
            $db->sql_query($sql_insert);
            $count++;
        }

        $db->sql_freeresult($result);
        return $count;
    }

    protected function count_missing_topic_rows($db, $table_prefix, array $forum_ids)
    {
        if (empty($forum_ids)) return 0;

        $sql = 'SELECT COUNT(t.topic_id) AS total
                FROM ' . TOPICS_TABLE . ' t
                LEFT JOIN ' . $this->status_table($table_prefix) . ' st ON st.topic_id = t.topic_id
                WHERE t.topic_moved_id = 0
                    AND ' . $db->sql_in_set('t.forum_id', array_map('intval', $forum_ids)) . '
                    AND st.topic_id IS NULL';

        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return $row ? (int) $row['total'] : 0;
    }

    protected function count_active_notices($db, $table_prefix)
    {
        if (!isset($this->config['mundophpbb_supporttriage_notice_feed_enable'])) {
            return 0;
        }

        $sql = 'SELECT COUNT(notice_id) AS total FROM ' . $this->notices_table($table_prefix) . ' WHERE is_active = 1';
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return $row ? (int) $row['total'] : 0;
    }

    protected function build_approval_rows($db, $table_prefix, $user)
    {
        $forum_ids = $this->csv_to_int_list($this->config['mundophpbb_supporttriage_forums']);
        $rows = [];

        if (empty($forum_ids)) return $rows;

        $forums = [];
        $sql = 'SELECT forum_id, forum_name FROM ' . FORUMS_TABLE . '
                WHERE ' . $db->sql_in_set('forum_id', array_map('intval', $forum_ids)) . ' ORDER BY left_id ASC';
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result)) {
            $forums[(int) $row['forum_id']] = (string) $row['forum_name'];
        }
        $db->sql_freeresult($result);

        $unapproved_value = defined('ITEM_UNAPPROVED') ? (int) ITEM_UNAPPROVED : 0;

        $topic_counts = [];
        $post_counts = [];

        $sql = 'SELECT forum_id, COUNT(topic_id) AS total FROM ' . TOPICS_TABLE . '
                WHERE topic_moved_id = 0
                AND ' . $db->sql_in_set('topic_visibility', [(int) $unapproved_value]) . '
                AND ' . $db->sql_in_set('forum_id', array_map('intval', $forum_ids)) . ' GROUP BY forum_id';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result)) {
            $topic_counts[(int) $row['forum_id']] = (int) $row['total'];
        }
        $db->sql_freeresult($result);

        $sql = 'SELECT forum_id, COUNT(post_id) AS total FROM ' . POSTS_TABLE . '
                WHERE ' . $db->sql_in_set('post_visibility', [(int) $unapproved_value]) . '
                AND ' . $db->sql_in_set('forum_id', array_map('intval', $forum_ids)) . ' GROUP BY forum_id';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result)) {
            $post_counts[(int) $row['forum_id']] = (int) $row['total'];
        }
        $db->sql_freeresult($result);

        foreach ($forum_ids as $forum_id) {
            $forum_id = (int) $forum_id;
            $topic_total = $topic_counts[$forum_id] ?? 0;
            $post_total  = $post_counts[$forum_id] ?? 0;
            $requires_review = ($topic_total > 0 || $post_total > 0);

            $rows[] = [
                'FORUM_ID'          => $forum_id,
                'FORUM_NAME'        => $forums[$forum_id] ?? $user->lang('ACP_SUPPORTTRIAGE_APPROVAL_FORUM_UNKNOWN', $forum_id),
                'UNAPPROVED_TOPICS' => $topic_total,
                'UNAPPROVED_POSTS'  => $post_total,
                'STATE_LABEL'       => $requires_review ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_WARN') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_OK'),
                'DETAILS'           => $requires_review
                    ? $user->lang('ACP_SUPPORTTRIAGE_APPROVAL_ROW_WARN', $topic_total, $post_total)
                    : $user->lang('ACP_SUPPORTTRIAGE_APPROVAL_ROW_OK'),
                'S_WARN' => $requires_review,
                'S_OK'   => !$requires_review,
            ];
        }

        return $rows;
    }

    protected function build_approval_summary(array $rows)
    {
        $summary = [
            'tracked_forums'     => count($rows),
            'unapproved_topics'  => 0,
            'unapproved_posts'   => 0,
            'requires_review'    => false,
        ];

        foreach ($rows as $row) {
            $summary['unapproved_topics'] += $row['UNAPPROVED_TOPICS'] ?? 0;
            $summary['unapproved_posts']  += $row['UNAPPROVED_POSTS'] ?? 0;
        }

        $summary['requires_review'] = ($summary['unapproved_topics'] > 0 || $summary['unapproved_posts'] > 0);

        return $summary;
    }

    protected function build_health_rows($db, $table_prefix, $user, $logs_supported, $kb_supported)
    {
        $rows = [];
        $forum_ids = $this->csv_to_int_list($this->config['mundophpbb_supporttriage_forums']);
        $forums_ok = !empty($forum_ids);

        $rows[] = [
            'TITLE'        => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TRACKED_FORUMS'),
            'STATUS_LABEL' => $forums_ok ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_OK') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_WARN'),
            'DETAILS'      => $forums_ok ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TRACKED_FORUMS_OK', count($forum_ids)) : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TRACKED_FORUMS_WARN'),
            'S_OK'         => $forums_ok,
            'S_WARN'       => !$forums_ok,
        ];

        $missing_rows = $this->count_missing_topic_rows($db, $table_prefix, $forum_ids);
        $rows[] = [
            'TITLE'        => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TOPIC_ROWS'),
            'STATUS_LABEL' => ($missing_rows === 0) ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_OK') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_WARN'),
            'DETAILS'      => ($missing_rows === 0) ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TOPIC_ROWS_OK') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_TOPIC_ROWS_WARN', (int) $missing_rows),
            'S_OK'         => ($missing_rows === 0),
            'S_WARN'       => ($missing_rows > 0),
        ];

        if ($kb_supported) {
            $kb_forum = isset($this->config['mundophpbb_supporttriage_kb_forum']) ? (int) $this->config['mundophpbb_supporttriage_kb_forum'] : 0;
            $kb_ok = empty($this->config['mundophpbb_supporttriage_kb_enable']) || $kb_forum > 0;
            $rows[] = [
                'TITLE'        => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_KB_FORUM'),
                'STATUS_LABEL' => $kb_ok ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_OK') : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_WARN'),
                'DETAILS'      => $kb_ok ? $user->lang('ACP_SUPPORTTRIAGE_HEALTH_KB_FORUM_OK', max(0, $kb_forum)) : $user->lang('ACP_SUPPORTTRIAGE_HEALTH_KB_FORUM_WARN'),
                'S_OK'         => $kb_ok,
                'S_WARN'       => !$kb_ok,
            ];
        }

        if (isset($this->config['mundophpbb_supporttriage_notice_feed_enable'])) {
            $active_notices = $this->count_active_notices($db, $table_prefix);
            $rows[] = [
                'TITLE'        => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_NOTICE_FEED'),
                'STATUS_LABEL' => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_INFO'),
                'DETAILS'      => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_NOTICE_FEED_INFO', (int) $active_notices),
                'S_OK'         => false,
                'S_WARN'       => false,
            ];
        }

        if ($logs_supported) {
            $rows[] = [
                'TITLE'        => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_LOGS'),
                'STATUS_LABEL' => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_INFO'),
                'DETAILS'      => $user->lang('ACP_SUPPORTTRIAGE_HEALTH_LOGS_INFO'),
                'S_OK'         => false,
                'S_WARN'       => false,
            ];
        }

        return $rows;
    }

    protected function handle_clear_logs_request($db, $table_prefix, $user, $request, $logs_supported)
    {
        if (!$logs_supported || !$request->is_set_post('supporttriage_clear_logs')) {
            return;
        }

        if (!check_form_key('mundophpbb_supporttriage')) {
            trigger_error('FORM_INVALID');
        }

        $sql = 'DELETE FROM ' . $this->logs_table($table_prefix);
        $db->sql_query($sql);

        trigger_error($user->lang('ACP_SUPPORTTRIAGE_LOGS_CLEARED') . adm_back_link($this->u_action));
    }

    protected function handle_export_request($db, $table_prefix, $user, $request, $logs_supported, $kb_supported, $queue_supported, array $filters)
    {
        if (!$request->is_set_post('supporttriage_export')) {
            return false;
        }

        if (!check_form_key('mundophpbb_supporttriage')) {
            trigger_error('FORM_INVALID');
        }

        $export = trim($request->variable('supporttriage_export', '', true));

        switch ($export) {
            case 'metrics':
                $headers = [
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_PERIOD'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_TOPICS_CREATED'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_TOPICS_SOLVED'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_KB_CREATED'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_ACTIONS'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_FIRST_REPLY'),
                    $user->lang('ACP_SUPPORTTRIAGE_METRICS_RESOLUTION'),
                ];
                $rows = $this->build_metrics_export_rows($db, $table_prefix, $user, $logs_supported, $kb_supported, $filters['period']);
                $this->send_csv_download('supporttriage_metrics_' . gmdate('Ymd_His') . '.csv', $headers, $rows);
                break;

            case 'logs':
                if (!$logs_supported) trigger_error('NOT_AUTHORISED');
                $headers = ['log_id','log_time','topic_id','forum_id','action','details','old_value','new_value','related_topic_id','related_forum_id','user_id','username','topic_url'];
                $rows = $this->get_logs_export_rows($db, $table_prefix, $user, $filters);
                $this->send_csv_download('supporttriage_history_' . gmdate('Ymd_His') . '.csv', $headers, $rows);
                break;

            case 'queue':
                if (!$queue_supported) trigger_error('NOT_AUTHORISED');
                $headers = ['topic_id','forum_id','forum_name','topic_title','status','priority','topic_created','status_updated','last_author_reply','last_staff_reply','open_for','last_change_age','topic_url'];
                $rows = $this->get_queue_export_rows($db, $table_prefix, $user, $filters, $this->config);
                $this->send_csv_download('supporttriage_open_queue_' . gmdate('Ymd_His') . '.csv', $headers, $rows);
                break;
        }

        return true;
    }

    // ==================== MÉTODOS DE EXPORTAÇÃO E UTILITÁRIOS ====================

    protected function get_export_filters($request)
    {
        $period = $this->normalize_export_period($request->variable('supporttriage_export_period', 'all', true));
        $action = $this->normalize_export_action($request->variable('supporttriage_export_action', 'all', true));
        $status = $this->normalize_export_status($request->variable('supporttriage_export_status', 'all', true));
        $priority = $this->normalize_export_priority($request->variable('supporttriage_export_priority', 'all', true));
        $forums_csv = preg_replace('/[^0-9,\s]/', '', $request->variable('supporttriage_export_forums', '', true));

        return [
            'period'       => $period,
            'since'        => $this->period_to_since($period),
            'action'       => $action,
            'status'       => $status,
            'priority'     => $priority,
            'forums_csv'   => trim((string) $forums_csv),
            'forum_ids'    => $this->csv_to_int_list($forums_csv),
            'stalled_only' => (int) $request->variable('supporttriage_export_stalled_only', 0),
        ];
    }

    protected function normalize_export_period($value)
    {
        $allowed = ['all', '24h', '7d', '30d', '90d'];
        $value = strtolower(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    protected function normalize_export_action($value)
    {
        $allowed = ['all','status_change','priority_change','kb_created','kb_synced','status_auto_waiting_reply','status_auto_in_progress','status_auto_no_reply','priority_auto_stale','priority_auto_forum','priority_auto_issue_type'];
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    protected function normalize_export_status($value)
    {
        $allowed = ['all', 'new', 'in_progress', 'waiting_reply', 'solved', 'no_reply'];
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    protected function normalize_export_priority($value)
    {
        $allowed = ['all', 'low', 'normal', 'high', 'critical'];
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    protected function period_to_since($period)
    {
        switch ($period) {
            case '24h': return time() - 86400;
            case '7d':  return time() - (7 * 86400);
            case '30d': return time() - (30 * 86400);
            case '90d': return time() - (90 * 86400);
        }
        return 0;
    }

    protected function csv_to_int_list($value)
    {
        $parts = preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }

    protected function build_metrics_rows($db, $table_prefix, $user, $logs_supported, $kb_supported)
    {
        $rows = [];
        foreach ($this->get_metric_periods($user) as $period) {
            $since = time() - (int) $period['seconds'];
            $rows[] = [
                'PERIOD'         => $period['label'],
                'TOPICS_CREATED' => $this->count_topics_created_since($db, $table_prefix, $since),
                'TOPICS_SOLVED'  => $logs_supported ? $this->count_topics_solved_since($db, $table_prefix, $since) : 0,
                'KB_CREATED'     => $kb_supported ? $this->count_kb_created_since($db, $table_prefix, $since) : 0,
                'ACTIONS_COUNT'  => $logs_supported ? $this->count_actions_since($db, $table_prefix, $since) : 0,
                'FIRST_REPLY'    => $this->format_duration_compact($user, $this->average_first_reply_since($db, $table_prefix, $since)),
                'RESOLUTION_TIME'=> $logs_supported ? $this->format_duration_compact($user, $this->average_resolution_since($db, $table_prefix, $since)) : $user->lang('ACP_SUPPORTTRIAGE_METRICS_NOT_AVAILABLE'),
            ];
        }
        return $rows;
    }

    protected function get_metric_periods($user)
    {
        return [
            ['key' => '24h', 'label' => $user->lang('ACP_SUPPORTTRIAGE_METRICS_PERIOD_24H'), 'seconds' => 86400],
            ['key' => '7d',  'label' => $user->lang('ACP_SUPPORTTRIAGE_METRICS_PERIOD_7D'),  'seconds' => 7 * 86400],
            ['key' => '30d', 'label' => $user->lang('ACP_SUPPORTTRIAGE_METRICS_PERIOD_30D'), 'seconds' => 30 * 86400],
            ['key' => '90d', 'label' => $user->lang('ACP_SUPPORTTRIAGE_METRICS_PERIOD_90D'), 'seconds' => 90 * 86400],
        ];
    }

    protected function build_metrics_export_rows($db, $table_prefix, $user, $logs_supported, $kb_supported, $selected_period = 'all')
    {
        $rows = [];
        foreach ($this->get_metric_periods($user) as $period) {
            $since = time() - (int) $period['seconds'];
            $rows[] = [
                $period['label'],
                $this->count_topics_created_since($db, $table_prefix, $since),
                $logs_supported ? $this->count_topics_solved_since($db, $table_prefix, $since) : 0,
                $kb_supported ? $this->count_kb_created_since($db, $table_prefix, $since) : 0,
                $logs_supported ? $this->count_actions_since($db, $table_prefix, $since) : 0,
                $this->format_duration_compact($user, $this->average_first_reply_since($db, $table_prefix, $since)),
                $logs_supported ? $this->format_duration_compact($user, $this->average_resolution_since($db, $table_prefix, $since)) : $user->lang('ACP_SUPPORTTRIAGE_METRICS_NOT_AVAILABLE'),
            ];
        }
        return $rows;
    }

    // ==================== MÉTODOS DE CONSULTA ====================

    protected function count_topics_created_since($db, $table_prefix, $since)
    {
        $sql = 'SELECT COUNT(st.topic_id) AS total
                FROM ' . $this->status_table($table_prefix) . ' st
                INNER JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = st.topic_id
                WHERE t.topic_moved_id = 0 AND t.topic_visibility = 1 AND t.topic_time >= ' . (int) $since;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return $row ? (int) $row['total'] : 0;
    }

    protected function count_topics_solved_since($db, $table_prefix, $since)
    {
        $sql = "SELECT COUNT(DISTINCT l.topic_id) AS total
                FROM " . $this->logs_table($table_prefix) . " l
                WHERE l.topic_id > 0 AND l.new_value = 'solved' AND l.log_time >= " . (int) $since;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return $row ? (int) $row['total'] : 0;
    }

    protected function count_kb_created_since($db, $table_prefix, $since)
    {
        $sql = 'SELECT COUNT(source_topic_id) AS total FROM ' . $this->kb_links_table($table_prefix) . ' WHERE created_time >= ' . (int) $since;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return $row ? (int) $row['total'] : 0;
    }

    protected function count_actions_since($db, $table_prefix, $since)
    {
        $sql = 'SELECT COUNT(log_id) AS total FROM ' . $this->logs_table($table_prefix) . ' WHERE log_time >= ' . (int) $since;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return $row ? (int) $row['total'] : 0;
    }

    protected function average_first_reply_since($db, $table_prefix, $since)
    {
        $sql = 'SELECT AVG(fr.first_reply_time - t.topic_time) AS avg_seconds
                FROM ' . TOPICS_TABLE . ' t
                INNER JOIN ' . $this->status_table($table_prefix) . ' st ON st.topic_id = t.topic_id
                INNER JOIN (
                    SELECT p.topic_id, MIN(p.post_time) AS first_reply_time
                    FROM ' . POSTS_TABLE . ' p
                    INNER JOIN ' . TOPICS_TABLE . ' t2 ON t2.topic_id = p.topic_id
                    WHERE p.post_visibility = 1 AND p.poster_id <> t2.topic_poster
                    GROUP BY p.topic_id
                ) fr ON fr.topic_id = t.topic_id
                WHERE t.topic_moved_id = 0 AND t.topic_visibility = 1 AND t.topic_time >= ' . (int) $since;

        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return ($row && $row['avg_seconds'] !== null) ? (float) $row['avg_seconds'] : 0;
    }

    protected function average_resolution_since($db, $table_prefix, $since)
    {
        $sql = "SELECT AVG(sol.first_solved_time - t.topic_time) AS avg_seconds
                FROM " . TOPICS_TABLE . " t
                INNER JOIN " . $this->status_table($table_prefix) . " st ON st.topic_id = t.topic_id
                INNER JOIN (
                    SELECT l.topic_id, MIN(l.log_time) AS first_solved_time
                    FROM " . $this->logs_table($table_prefix) . " l
                    WHERE l.new_value = 'solved'
                    GROUP BY l.topic_id
                ) sol ON sol.topic_id = t.topic_id
                WHERE t.topic_moved_id = 0 AND t.topic_visibility = 1 AND sol.first_solved_time >= " . (int) $since;

        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        return ($row && $row['avg_seconds'] !== null) ? (float) $row['avg_seconds'] : 0;
    }

    protected function get_top_moderators($db, $table_prefix, $user, $days = 30, $limit = 10)
    {
        $since = time() - (max(1, (int) $days) * 86400);
        $limit = max(1, min(50, (int) $limit));

        $sql = 'SELECT l.user_id, COUNT(l.log_id) AS total_actions, u.username, u.user_colour
                FROM ' . $this->logs_table($table_prefix) . ' l
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = l.user_id
                WHERE l.user_id > 0 AND l.log_time >= ' . (int) $since . '
                GROUP BY l.user_id, u.username, u.user_colour
                ORDER BY total_actions DESC, l.user_id ASC';

        $result = $db->sql_query_limit($sql, $limit);
        $rows = [];

        while ($row = $db->sql_fetchrow($result)) {
            $rows[] = [
                'USERNAME'     => !empty($row['username']) ? get_username_string('full', (int) $row['user_id'], $row['username'], $row['user_colour']) : $user->lang('SUPPORTTRIAGE_SYSTEM_USER'),
                'TOTAL_ACTIONS'=> (int) $row['total_actions'],
            ];
        }
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function get_slowest_topics($db, $table_prefix, $user, $limit = 10)
    {
        $limit = max(1, min(50, (int) $limit));
        $sql = 'SELECT st.topic_id, st.forum_id, st.status_key, st.status_updated, t.topic_title, t.topic_time
                FROM ' . $this->status_table($table_prefix) . ' st
                INNER JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = st.topic_id
                WHERE t.topic_moved_id = 0 AND t.topic_visibility = 1 AND st.status_key <> \'solved\'
                ORDER BY st.status_updated ASC, t.topic_time ASC, st.topic_id ASC';

        $result = $db->sql_query_limit($sql, $limit);
        $rows = [];

        while ($row = $db->sql_fetchrow($result)) {
            $updated = !empty($row['status_updated']) ? (int) $row['status_updated'] : (int) $row['topic_time'];
            $rows[] = [
                'TITLE'      => $this->html($row['topic_title']),
                'TOPIC_URL'  => append_sid('viewtopic.php', 'f=' . (int) $row['forum_id'] . '&t=' . (int) $row['topic_id']),
                'STATUS_LABEL'=> $this->status_label($user, $row['status_key']),
                'UPDATED'    => $updated > 0 ? $user->format_date($updated) : '',
                'AGE'        => $updated > 0 ? $this->format_duration_compact($user, time() - $updated) : $user->lang('ACP_SUPPORTTRIAGE_METRICS_NOT_AVAILABLE'),
            ];
        }
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function format_duration_compact($user, $seconds)
    {
        $seconds = (float) $seconds;
        if ($seconds <= 0) return $user->lang('ACP_SUPPORTTRIAGE_METRICS_NOT_AVAILABLE');

        if ($seconds < 3600) {
            return $user->lang('ACP_SUPPORTTRIAGE_DURATION_MINUTES', max(1, round($seconds / 60)));
        }
        if ($seconds < 86400) {
            return $user->lang('ACP_SUPPORTTRIAGE_DURATION_HOURS', round($seconds / 3600, 1));
        }
        return $user->lang('ACP_SUPPORTTRIAGE_DURATION_DAYS', round($seconds / 86400, 1));
    }

    protected function get_recent_logs($db, $table_prefix, $user, $limit = 15)
    {
        $limit = max(1, min(50, (int) $limit));
        $sql = 'SELECT l.*, u.username, u.user_colour
                FROM ' . $this->logs_table($table_prefix) . ' l
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = l.user_id
                ORDER BY l.log_time DESC, l.log_id DESC';

        $result = $db->sql_query_limit($sql, $limit);
        $rows = [];

        while ($row = $db->sql_fetchrow($result)) {
            $rows[] = $this->format_log_row($row, $user);
        }
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function get_logs_export_rows($db, $table_prefix, $user, array $filters)
    {
        $where = [];
        if (!empty($filters['since'])) $where[] = 'l.log_time >= ' . (int) $filters['since'];
        if (!empty($filters['forum_ids'])) $where[] = $db->sql_in_set('l.forum_id', array_map('intval', $filters['forum_ids']));
        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $where[] = $this->sql_string_equals($db, 'l.action_key', $filters['action']);
        }

        $sql = 'SELECT l.*, u.username FROM ' . $this->logs_table($table_prefix) . ' l
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = l.user_id';

        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);

        $sql .= ' ORDER BY l.log_time DESC, l.log_id DESC';

        $result = $db->sql_query($sql);
        $rows = [];

        while ($row = $db->sql_fetchrow($result)) {
            $rows[] = [
                (int) $row['log_id'],
                !empty($row['log_time']) ? $user->format_date((int) $row['log_time']) : '',
                (int) $row['topic_id'],
                (int) $row['forum_id'],
                $this->log_action_label($user, $row['action_key'] ?? ''),
                $this->log_details_plain($row, $user),
                $row['old_value'] ?? '',
                $row['new_value'] ?? '',
                (int) ($row['related_topic_id'] ?? 0),
                (int) ($row['related_forum_id'] ?? 0),
                (int) ($row['user_id'] ?? 0),
                !empty($row['username']) ? $row['username'] : $user->lang('SUPPORTTRIAGE_SYSTEM_USER'),
                (!empty($row['topic_id']) && !empty($row['forum_id'])) ? append_sid('viewtopic.php', 'f=' . (int) $row['forum_id'] . '&t=' . (int) $row['topic_id']) : '',
            ];
        }
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function get_queue_export_rows($db, $table_prefix, $user, array $filters, $config)
    {
        $status_table = $this->status_table($table_prefix);
        $queue_stale_days = max(0, (int) ($config['mundophpbb_supporttriage_queue_stale_days'] ?? 5));
        $stale_threshold = $queue_stale_days > 0 ? time() - ($queue_stale_days * 86400) : 0;
        $priority_column = isset($config['mundophpbb_supporttriage_priority_enable']) ? ', st.priority_key' : ", 'normal' AS priority_key";

        $where = [
            't.topic_moved_id = 0',
            't.topic_visibility = 1',
            "st.status_key <> 'solved'",
        ];

        if (!empty($filters['forum_ids'])) $where[] = $db->sql_in_set('st.forum_id', array_map('intval', $filters['forum_ids']));
        if (!empty($filters['status']) && $filters['status'] !== 'all') $where[] = $this->sql_string_equals($db, 'st.status_key', $filters['status']);
        if (!empty($filters['priority']) && $filters['priority'] !== 'all' && isset($config['mundophpbb_supporttriage_priority_enable'])) {
            $where[] = $this->sql_string_equals($db, 'st.priority_key', $filters['priority']);
        }
        if (!empty($filters['since'])) $where[] = 'st.status_updated >= ' . (int) $filters['since'];
        if (!empty($filters['stalled_only']) && $stale_threshold > 0) $where[] = 'st.status_updated <= ' . (int) $stale_threshold;

        $sql = 'SELECT st.topic_id, st.forum_id, st.status_key, st.status_updated, st.last_author_reply, st.last_staff_reply' . $priority_column . ',
                       t.topic_title, t.topic_time, f.forum_name
                FROM ' . $status_table . ' st
                INNER JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = st.topic_id
                LEFT JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = st.forum_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY st.status_updated ASC, t.topic_time ASC, st.topic_id ASC';

        $result = $db->sql_query($sql);
        $rows = [];

        while ($row = $db->sql_fetchrow($result)) {
            $status_updated = !empty($row['status_updated']) ? (int) $row['status_updated'] : (int) $row['topic_time'];
            $topic_time = !empty($row['topic_time']) ? (int) $row['topic_time'] : 0;
            $is_stalled = ($stale_threshold > 0 && $status_updated > 0 && $status_updated <= $stale_threshold);

            $rows[] = [
                (int) $row['topic_id'],
                (int) $row['forum_id'],
                $row['forum_name'] ?? '',
                $row['topic_title'] ?? '',
                $this->status_label($user, $row['status_key'] ?? ''),
                $this->priority_label($user, $row['priority_key'] ?? 'normal'),
                $topic_time > 0 ? $user->format_date($topic_time) : '',
                $status_updated > 0 ? $user->format_date($status_updated) : '',
                !empty($row['last_author_reply']) ? $user->format_date((int) $row['last_author_reply']) : '',
                !empty($row['last_staff_reply']) ? $user->format_date((int) $row['last_staff_reply']) : '',
                $topic_time > 0 ? $this->format_duration_compact($user, time() - $topic_time) : '',
                $status_updated > 0 ? $this->format_duration_compact($user, time() - $status_updated) : '',
                append_sid('viewtopic.php', 'f=' . (int) $row['forum_id'] . '&t=' . (int) $row['topic_id']) . ($is_stalled ? '&stalled=1' : ''),
            ];
        }
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function format_log_row($row, $user)
    {
        $action_key = $row['action_key'] ?? '';
        $topic_id   = (int) ($row['topic_id'] ?? 0);
        $forum_id   = (int) ($row['forum_id'] ?? 0);

        return [
            'action_label' => $this->log_action_label($user, $action_key),
            'details'      => $this->log_details_html($row, $user),
            'username'     => !empty($row['username']) ? get_username_string('full', (int) $row['user_id'], $row['username'], $row['user_colour'] ?? '') : $user->lang('SUPPORTTRIAGE_SYSTEM_USER'),
            'time'         => !empty($row['log_time']) ? $user->format_date((int) $row['log_time']) : '',
            'topic_link'   => ($topic_id > 0 && $forum_id > 0) ? append_sid('viewtopic.php', 'f=' . $forum_id . '&t=' . $topic_id) : '',
        ];
    }

    protected function log_action_label($user, $action_key)
    {
        $lang_key = 'SUPPORTTRIAGE_LOG_ACTION_' . strtoupper((string) $action_key);
        $label = $user->lang($lang_key);
        return ($label === $lang_key) ? (string) $action_key : $label;
    }

    protected function log_details_html($row, $user)
    {
        $action_key = $row['action_key'] ?? '';
        $old_value  = $row['old_value'] ?? '';
        $new_value  = $row['new_value'] ?? '';

        switch ($action_key) {
            case 'status_change':
            case 'status_auto_waiting_reply':
            case 'status_auto_in_progress':
            case 'status_auto_no_reply':
                return $user->lang('SUPPORTTRIAGE_LOG_DETAILS_STATUS', $this->status_label($user, $old_value), $this->status_label($user, $new_value));

            case 'priority_change':
                return $user->lang('SUPPORTTRIAGE_LOG_DETAILS_PRIORITY', $this->priority_label($user, $old_value), $this->priority_label($user, $new_value));

            case 'kb_create':
            case 'kb_sync':
                $related_topic_id = (int) ($row['related_topic_id'] ?? 0);
                $related_forum_id = (int) ($row['related_forum_id'] ?? 0);
                $kb_label = '#' . $related_topic_id;
                if ($related_topic_id > 0 && $related_forum_id > 0) {
                    $kb_url = append_sid('viewtopic.php', 'f=' . $related_forum_id . '&t=' . $related_topic_id);
                    $kb_label = '<a href="' . $kb_url . '">#' . $related_topic_id . '</a>';
                }
                return $user->lang($action_key === 'kb_create' ? 'SUPPORTTRIAGE_LOG_DETAILS_KB_CREATE' : 'SUPPORTTRIAGE_LOG_DETAILS_KB_SYNC', $kb_label);
        }
        return '';
    }

    protected function log_details_plain($row, $user)
    {
        $details = $this->log_details_html($row, $user);
        $details = preg_replace('/<[^>]+>/', '', (string) $details);
        return html_entity_decode($details, ENT_COMPAT, 'UTF-8');
    }

    protected function get_snippets($db, $table_prefix)
    {
        $sql = 'SELECT snippet_id, snippet_title, snippet_text, sort_order, is_active
                FROM ' . $this->snippets_table($table_prefix) . '
                ORDER BY sort_order ASC, snippet_id ASC';
        $result = $db->sql_query($sql);
        $rows = [];
        while ($row = $db->sql_fetchrow($result)) $rows[] = $row;
        $db->sql_freeresult($result);
        return $rows;
    }

    protected function ensure_default_snippets($db, $table_prefix, $user)
    {
        $sql = 'SELECT COUNT(snippet_id) AS total FROM ' . $this->snippets_table($table_prefix);
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!empty($row['total'])) return;

        $defaults = $this->default_snippets($user);
        $next_id = 1;
        foreach ($defaults as $index => $snippet) {
            $sql_ary = [
                'snippet_id'   => $next_id++,
                'snippet_title'=> $snippet['snippet_title'],
                'snippet_text' => $snippet['snippet_text'],
                'sort_order'   => $index + 1,
                'is_active'    => 1,
            ];
            $db->sql_query('INSERT INTO ' . $this->snippets_table($table_prefix) . ' ' . $db->sql_build_array('INSERT', $sql_ary));
        }
    }

    protected function default_snippets($user)
    {
        return [
            ['snippet_title' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_CACHE'), 'snippet_text' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_CACHE')],
            ['snippet_title' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_PROSILVER'), 'snippet_text' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_PROSILVER')],
            ['snippet_title' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_DISABLE_EXT'), 'snippet_text' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_DISABLE_EXT')],
            ['snippet_title' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_DEBUG'), 'snippet_text' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_DEBUG')],
            ['snippet_title' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_STEPS'), 'snippet_text' => $user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_STEPS')],
        ];
    }

    protected function save_snippets($db, $table_prefix, $user, $request)
    {
        $titles  = $request->variable('snippet_title', [0 => ''], true);
        $texts   = $request->variable('snippet_text', [0 => ''], true);
        $sorts   = $request->variable('snippet_sort', [0 => 0]);
        $actives = $request->variable('snippet_active', [0 => 0]);
        $deletes = $request->variable('snippet_delete', [0 => 0]);

        $rows = [];
        foreach ($titles as $index => $title) {
            if (isset($deletes[$index])) continue;

            $title = $this->sanitize_line($title, 255);
            $text  = $this->sanitize_text($texts[$index] ?? '');
            $sort  = isset($sorts[$index]) ? max(0, (int) $sorts[$index]) : (count($rows) + 1);
            $active = isset($actives[$index]) ? 1 : 0;

            if ($title === '' && $text === '') continue;
            if ($title === '') $title = $user->lang('SUPPORTTRIAGE_SNIPPET_UNTITLED');

            $rows[] = [
                'snippet_title' => $title,
                'snippet_text'  => $text,
                'sort_order'    => $sort,
                'is_active'     => $active,
            ];
        }

        // Novo snippet
        $new_title = $this->sanitize_line($request->variable('new_snippet_title', '', true), 255);
        $new_text  = $this->sanitize_text($request->variable('new_snippet_text', '', true));
        $new_sort  = max(0, (int) $request->variable('new_snippet_sort', count($rows) + 1));
        $new_active = $request->variable('new_snippet_active', 0) ? 1 : 0;

        if ($new_title !== '' || $new_text !== '') {
            if ($new_title === '') $new_title = $user->lang('SUPPORTTRIAGE_SNIPPET_UNTITLED');
            $rows[] = ['snippet_title' => $new_title, 'snippet_text' => $new_text, 'sort_order' => $new_sort, 'is_active' => $new_active];
        }

        if (empty($rows)) {
            $rows = $this->default_snippets($user);
            foreach ($rows as $index => &$row) {
                $row['sort_order'] = $index + 1;
                $row['is_active'] = 1;
            }
        }

        usort($rows, function ($a, $b) {
            if ((int) $a['sort_order'] === (int) $b['sort_order']) {
                return strcmp((string) $a['snippet_title'], (string) $b['snippet_title']);
            }
            return ((int) $a['sort_order'] < (int) $b['sort_order']) ? -1 : 1;
        });

        $db->sql_query('DELETE FROM ' . $this->snippets_table($table_prefix));

        $next_id = 1;
        foreach ($rows as $index => $row) {
            $sql_ary = [
                'snippet_id'   => $next_id++,
                'snippet_title'=> $row['snippet_title'],
                'snippet_text' => $row['snippet_text'],
                'sort_order'   => $index + 1,
                'is_active'    => !empty($row['is_active']) ? 1 : 0,
            ];
            $db->sql_query('INSERT INTO ' . $this->snippets_table($table_prefix) . ' ' . $db->sql_build_array('INSERT', $sql_ary));
        }
    }

    protected function status_label($user, $status_key)
    {
        $status_key = trim((string) $status_key);
        $allowed = ['new', 'in_progress', 'waiting_reply', 'solved', 'no_reply'];
        if (!in_array($status_key, $allowed, true)) return $user->lang('SUPPORTTRIAGE_STATUS_NONE');
        return $user->lang('SUPPORTTRIAGE_STATUS_' . strtoupper($status_key));
    }

    protected function priority_label($user, $priority_key)
    {
        $priority_key = trim((string) $priority_key);
        $allowed = ['low', 'normal', 'high', 'critical'];
        if (!in_array($priority_key, $allowed, true)) $priority_key = 'normal';
        return $user->lang('SUPPORTTRIAGE_PRIORITY_' . strtoupper($priority_key));
    }

    protected function send_csv_download($filename, array $headers, array $rows)
    {
        if (function_exists('garbage_collection')) garbage_collection();

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $filename);
        if (ob_get_level()) @ob_end_clean();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        file_put_contents('php://output', "\xEF\xBB\xBF" . $csv);
    }

    protected function sanitize_line($value, $max_length)
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $value = strip_tags($value);
        if (function_exists('utf8_normalize_nfc')) $value = utf8_normalize_nfc($value);
        if ((int) $max_length > 0) $value = utf8_substr($value, 0, (int) $max_length);
        return $value;
    }

    protected function sanitize_text($value)
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if (function_exists('utf8_normalize_nfc')) $value = utf8_normalize_nfc($value);
        return $value;
    }

    protected function html($value)
    {
        return utf8_htmlspecialchars((string) $value);
    }

    protected function sql_string_equals($db, $column, $value)
    {
        return $db->sql_in_set($column, [(string) $value]);
    }
}
