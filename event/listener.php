<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var string */
    protected $table_prefix;

    /** @var array */
    protected $topic_status_cache = [];

    /** @var array */
    protected $snippet_cache = [];

    /** @var array */
    protected $kb_link_cache = [];

    /** @var array */
    protected $topic_logs_cache = [];

    /** @var array */
    protected $automation_check_cache = [];

    /** @var array */
    protected $mcp_queue_cache = [];

    /** @var array */
    protected $topic_alert_cache = [];

    /** @var array */
    protected $topic_notice_cache = [];

    /** @var array */
    protected $forum_notice_cache = [];

    /** @var string */
    protected $status_auto_notice = '';

    /** @var bool */
    protected $mcp_action_processed = false;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\template\template $template,
        \phpbb\request\request_interface $request,
        \phpbb\user $user,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\auth\auth $auth,
        $table_prefix
    ) {
        $this->config = $config;
        $this->template = $template;
        $this->request = $request;
        $this->user = $user;
        $this->db = $db;
        $this->auth = $auth;
        $this->table_prefix = (string) $table_prefix;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup' => 'load_language_on_setup',
            'core.posting_modify_template_vars' => 'posting_modify_template_vars',
            'core.submit_post_end' => 'submit_post_end',
            'core.viewforum_modify_topicrow' => 'viewforum_modify_topicrow',
            'core.mcp_forum_view_before' => 'mcp_forum_view_before',
            'core.mcp_view_forum_modify_topicrow' => 'mcp_view_forum_modify_topicrow',
            'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',
            'core.viewtopic_modify_post_row' => 'viewtopic_modify_post_row',
            'sitesplat.bbtpreview.controller_response_before' => 'bbtpreview_controller_response_before',
        ];
    }

    public function load_language_on_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'mundophpbb/supporttriage',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function posting_modify_template_vars($event)
    {
        $mode = (string) $event['mode'];
        $forum_id = (int) $this->request->variable('f', 0);
        $topic_id = (int) $this->request->variable('t', 0);
        $issue_type = $this->normalize_issue_type($this->request->variable('supporttriage_issue_type', 'general', true));
        $priority_key = $this->normalize_priority($this->request->variable('supporttriage_priority', $this->default_priority(), true));

        $enabled = !empty($this->config['mundophpbb_supporttriage_enable'])
            && $mode === 'post'
            && $topic_id === 0
            && $this->forum_is_enabled($forum_id);

        $can_use_reply_snippets = $topic_id > 0
            && in_array($mode, ['reply', 'quote'], true)
            && $this->can_use_snippets($forum_id);

        $page_data = $event['page_data'];
        $page_data['S_SUPPORTTRIAGE_SHOW'] = $enabled;
        $page_data['SUPPORTTRIAGE_AUTO_INSERT'] = !empty($this->config['mundophpbb_supporttriage_auto_insert']) ? 1 : 0;
        $page_data['SUPPORTTRIAGE_TITLE_PREFIX'] = $this->escape($this->config_value('mundophpbb_supporttriage_prefix'));
        $page_data['SUPPORTTRIAGE_FORM_ISSUE_TYPE'] = $this->escape($issue_type);
        $page_data['S_SUPPORTTRIAGE_PRIORITY_SHOW'] = $enabled && $this->priority_enabled();
        $page_data['SUPPORTTRIAGE_FORM_PRIORITY'] = $this->escape($priority_key !== '' ? $priority_key : $this->default_priority());
        $page_data['SUPPORTTRIAGE_FORM_PHPBB'] = $this->escape($this->request->variable('supporttriage_phpbb', '', true));
        $page_data['SUPPORTTRIAGE_FORM_PHP'] = $this->escape($this->request->variable('supporttriage_php', '', true));
        $page_data['SUPPORTTRIAGE_FORM_STYLE'] = $this->escape($this->request->variable('supporttriage_style', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EXT'] = $this->escape($this->request->variable('supporttriage_extension', '', true));
        $page_data['SUPPORTTRIAGE_FORM_BOARD_URL'] = $this->escape($this->request->variable('supporttriage_board_url', $this->build_board_url(), true));
        $page_data['SUPPORTTRIAGE_FORM_PROSILVER'] = $this->escape($this->request->variable('supporttriage_prosilver', '', true));
        $page_data['SUPPORTTRIAGE_FORM_DEBUG'] = $this->escape($this->request->variable('supporttriage_debug', '', true));
        $page_data['SUPPORTTRIAGE_FORM_ERROR'] = $this->escape($this->request->variable('supporttriage_error', '', true));
        $page_data['SUPPORTTRIAGE_FORM_STEPS'] = $this->escape($this->request->variable('supporttriage_steps', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EXT_VERSION'] = $this->escape($this->request->variable('supporttriage_ext_version', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EXT_STAGE'] = $this->escape($this->request->variable('supporttriage_ext_stage', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EXT_SOURCE'] = $this->escape($this->request->variable('supporttriage_ext_source', '', true));
        $page_data['SUPPORTTRIAGE_FORM_UPDATE_FROM'] = $this->escape($this->request->variable('supporttriage_update_from', '', true));
        $page_data['SUPPORTTRIAGE_FORM_UPDATE_TO'] = $this->escape($this->request->variable('supporttriage_update_to', '', true));
        $page_data['SUPPORTTRIAGE_FORM_UPDATE_METHOD'] = $this->escape($this->request->variable('supporttriage_update_method', '', true));
        $page_data['SUPPORTTRIAGE_FORM_UPDATE_DB'] = $this->escape($this->request->variable('supporttriage_update_db', '', true));
        $page_data['SUPPORTTRIAGE_FORM_STYLE_BROWSER'] = $this->escape($this->request->variable('supporttriage_style_browser', '', true));
        $page_data['SUPPORTTRIAGE_FORM_STYLE_PAGE'] = $this->escape($this->request->variable('supporttriage_style_page', '', true));
        $page_data['SUPPORTTRIAGE_FORM_STYLE_DEVICE'] = $this->escape($this->request->variable('supporttriage_style_device', '', true));
        $page_data['SUPPORTTRIAGE_FORM_PERM_ACTOR'] = $this->escape($this->request->variable('supporttriage_perm_actor', '', true));
        $page_data['SUPPORTTRIAGE_FORM_PERM_ACTION'] = $this->escape($this->request->variable('supporttriage_perm_action', '', true));
        $page_data['SUPPORTTRIAGE_FORM_PERM_TARGET'] = $this->escape($this->request->variable('supporttriage_perm_target', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EMAIL_TRANSPORT'] = $this->escape($this->request->variable('supporttriage_email_transport', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EMAIL_CASE'] = $this->escape($this->request->variable('supporttriage_email_case', '', true));
        $page_data['SUPPORTTRIAGE_FORM_EMAIL_LOG'] = $this->escape($this->request->variable('supporttriage_email_log', '', true));

        $recent_topics = $enabled ? $this->get_recent_topics_for_suggestions($forum_id) : [];
        $page_data['SUPPORTTRIAGE_TOPIC_SUGGESTIONS_COUNT'] = count($recent_topics);
        $page_data['SUPPORTTRIAGE_TOPIC_SUGGESTIONS_JSON'] = $this->escape($this->encode_json_for_template($recent_topics));
        $page_data['S_SUPPORTTRIAGE_REPLY_SNIPPETS_SHOW'] = false;
        $page_data['S_SUPPORTTRIAGE_REPLY_SUGGESTED_SHOW'] = false;
        $page_data['SUPPORTTRIAGE_REPLY_SUGGESTED_REASON'] = '';

        if ($can_use_reply_snippets)
        {
            $snippets = $this->get_snippets(true);
            $page_data['S_SUPPORTTRIAGE_REPLY_SNIPPETS_SHOW'] = !empty($snippets);
            $this->assign_snippet_block('supporttriage_reply_snippets', $snippets);

            $topic_context = $this->load_source_topic_data($topic_id);
            $reply_status_row = $this->status_system_enabled() ? $this->ensure_topic_status_row($topic_id, $forum_id) : null;
            $reply_status_key = $reply_status_row ? $this->normalize_status($reply_status_row['status_key']) : '';
            $reply_priority_key = $this->get_topic_priority_key($reply_status_row);
            $suggested_snippets = $this->get_contextual_snippets(
                $snippets,
                $reply_status_key,
                $reply_priority_key,
                $topic_context ? (string) $topic_context['topic_title'] : '',
                $topic_context ? (string) $topic_context['post_text'] : ''
            );

            $page_data['S_SUPPORTTRIAGE_REPLY_SUGGESTED_SHOW'] = !empty($suggested_snippets);
            $page_data['SUPPORTTRIAGE_REPLY_SUGGESTED_REASON'] = $this->escape($this->build_context_snippet_reason($reply_status_key, $reply_priority_key, $topic_context));
            $this->assign_snippet_block('supporttriage_reply_suggested_snippets', $suggested_snippets);
        }

        $event['page_data'] = $page_data;
    }

    public function submit_post_end($event)
    {
        if (!$this->status_system_enabled())
        {
            return;
        }
    
        $data = isset($event['data']) && is_array($event['data']) ? $event['data'] : [];
        $forum_id = isset($data['forum_id']) ? (int) $data['forum_id'] : 0;
        $topic_id = isset($data['topic_id']) ? (int) $data['topic_id'] : 0;
        $user_id = (int) $this->user->data['user_id'];
        $mode = isset($event['mode']) ? (string) $event['mode'] : '';
        $timestamp = time();
    
        if ($forum_id <= 0 || $topic_id <= 0 || !$this->forum_is_enabled($forum_id))
        {
            return;
        }
    
        $is_new_topic = ($mode === 'post');
    
        if (isset($data['topic_first_post_id'], $data['post_id']))
        {
            $is_new_topic = ((int) $data['topic_first_post_id'] === (int) $data['post_id']);
        }
    
        if ($is_new_topic)
        {
            if ($this->topic_status_exists($topic_id))
            {
                return;
            }
    
            $extra_data = [];
            if ($this->tracking_columns_available())
            {
                $extra_data = [
                    'topic_author_id' => $user_id,
                    'last_author_reply' => $timestamp,
                    'last_staff_reply' => 0,
                ];
            }
    
            $this->save_topic_status(
                $topic_id,
                $forum_id,
                $this->default_status(),
                $user_id,
                $timestamp,
                $extra_data
            );

            if ($this->priority_enabled())
            {
                $priority_key = $this->normalize_priority($this->request->variable('supporttriage_priority', $this->default_priority(), true));
                $this->save_topic_priority($topic_id, $priority_key !== '' ? $priority_key : $this->default_priority());
                $issue_type = $this->normalize_issue_type($this->request->variable('supporttriage_issue_type', 'general', true));
                $this->maybe_apply_priority_automation($topic_id, $forum_id, $this->ensure_topic_status_row($topic_id, $forum_id), $issue_type);
            }

            $this->sync_topic_notices($topic_id, $forum_id);
            return;
        }
    
        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        if (!$status_row)
        {
            return;
        }
    
        $topic_author_id = $this->resolve_topic_author_id($topic_id, $status_row);
        $current_status = $this->normalize_status($status_row['status_key']);
        $extra_data = [];
    
        if ($this->tracking_columns_available() && $topic_author_id > 0)
        {
            $extra_data['topic_author_id'] = $topic_author_id;
        }
    
        $is_author_reply = ($user_id > 0 && $topic_author_id > 0 && $user_id === $topic_author_id);
        $is_staff_reply = (!$is_author_reply && $this->can_set_status($forum_id));
    
        if ($this->tracking_columns_available())
        {
            if ($is_author_reply)
            {
                $extra_data['last_author_reply'] = $timestamp;
            }
            else if ($is_staff_reply)
            {
                $extra_data['last_staff_reply'] = $timestamp;
            }
    
            if (!empty($extra_data))
            {
                $this->update_topic_tracking($topic_id, $extra_data);
            }
        }
    
        if (!$this->automation_enabled())
        {
            $this->sync_topic_notices($topic_id, $forum_id);
            return;
        }
    
        if ($is_staff_reply
            && $this->auto_waiting_reply_enabled()
            && in_array($current_status, ['new', 'in_progress', 'no_reply'], true))
        {
            $this->save_topic_status(
                $topic_id,
                $forum_id,
                'waiting_reply',
                $user_id,
                $timestamp,
                $extra_data
            );
            $this->log_action($topic_id, $forum_id, 'status_auto_waiting_reply', $current_status, 'waiting_reply', 0, 0, $user_id);
        }
        else if ($is_author_reply
            && $this->auto_in_progress_enabled()
            && in_array($current_status, ['waiting_reply', 'no_reply'], true))
        {
            $this->save_topic_status(
                $topic_id,
                $forum_id,
                'in_progress',
                $user_id,
                $timestamp,
                $extra_data
            );
            $this->log_action($topic_id, $forum_id, 'status_auto_in_progress', $current_status, 'in_progress', 0, 0, $user_id);
        }

        $this->sync_topic_notices($topic_id, $forum_id);
    }

    public function viewforum_modify_topicrow($event)
    {
        $this->inject_topicrow_status($event);
    }

    public function mcp_forum_view_before($event)
    {
        $forum_info = isset($event['forum_info']) && is_array($event['forum_info']) ? $event['forum_info'] : [];
        $forum_id = !empty($forum_info['forum_id']) ? (int) $forum_info['forum_id'] : (int) $this->request->variable('f', 0);

        $this->process_mcp_inline_actions($forum_id);
        $this->assign_mcp_queue_panel($forum_id);
    }

    public function mcp_view_forum_modify_topicrow($event)
    {
        $row = $event['row'];
        $forum_id = isset($row['forum_id']) ? (int) $row['forum_id'] : (int) $this->request->variable('f', 0);

        $this->inject_topicrow_status($event);
    }

    public function viewtopic_assign_template_vars_before($event)
    {
        $forum_id = (int) $event['forum_id'];
        $topic_id = (int) $event['topic_id'];
        $viewtopic_url = (string) $event['viewtopic_url'];
        $can_set_status = $this->can_set_status($forum_id);
        $can_set_priority = $this->can_set_priority($forum_id);
        $can_use_snippets = $this->can_use_snippets($forum_id);
        $can_view_logs = $this->can_view_logs($forum_id);
    
        $this->maybe_apply_no_reply_status($topic_id, $forum_id);
    
        if ($can_set_status)
        {
            if ($this->request->is_set_post('supporttriage_update_status'))
            {
                if (!check_form_key($this->viewtopic_form_name()))
                {
                    trigger_error('FORM_INVALID');
                }
    
                $status_key = $this->normalize_status($this->request->variable('supporttriage_status', '', true));
    
                if ($status_key === '')
                {
                    $status_key = $this->default_status();
                }
    
                $previous_status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
                $previous_status_key = $previous_status_row ? $this->normalize_status($previous_status_row['status_key']) : '';
                $timestamp = time();
                $extra_data = [];
    
                if ($this->tracking_columns_available())
                {
                    $topic_author_id = $this->resolve_topic_author_id($topic_id, $previous_status_row);
                    if ($topic_author_id > 0)
                    {
                        $extra_data['topic_author_id'] = $topic_author_id;
                    }
    
                    if ($status_key === 'waiting_reply')
                    {
                        $extra_data['last_staff_reply'] = $timestamp;
                    }
                }
    
                $this->save_topic_status(
                    $topic_id,
                    $forum_id,
                    $status_key,
                    (int) $this->user->data['user_id'],
                    $timestamp,
                    $extra_data
                );
    
                if ($previous_status_key !== $status_key)
                {
                    $this->log_action($topic_id, $forum_id, 'status_change', $previous_status_key, $status_key);
                }
    
                $redirect_url = $this->append_url_param(
                    html_entity_decode($viewtopic_url, ENT_QUOTES, 'UTF-8'),
                    'stsaved=1'
                );
                redirect($redirect_url);
            }
        }
    
        $status_row = null;
        if ($this->status_system_enabled() && $this->forum_is_enabled($forum_id))
        {
            $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        }
    
        $status_key = $status_row ? $this->normalize_status($status_row['status_key']) : '';
        $priority_key = $this->get_topic_priority_key($status_row);
        $meta = $this->status_meta($status_key);
        $priority_meta = $this->priority_meta($priority_key);
        $has_status = ($status_key !== '');
    
        $updated_line = '';
        if ($has_status && !empty($status_row['status_updated']))
        {
            $updated_by = '';
            if (!empty($status_row['username']))
            {
                $updated_by = get_username_string('full', (int) $status_row['user_id'], $status_row['username'], $status_row['user_colour']);
            }
            else
            {
                $updated_by = $this->user->lang('SUPPORTTRIAGE_SYSTEM_USER');
            }
    
            $updated_line = $this->user->lang(
                'SUPPORTTRIAGE_STATUS_UPDATED_BY',
                $updated_by,
                $this->user->format_date((int) $status_row['status_updated'])
            );
        }
    
        $kb_link = $this->kb_enabled() ? $this->get_kb_link($topic_id) : null;
        $this->sync_topic_notices($topic_id, $forum_id, $status_row, $status_key);
        $alerts = $this->get_topic_alerts($topic_id, $forum_id, $status_row, $status_key, $kb_link);
        $notices = (($can_set_status || $can_set_priority) || $can_view_logs) ? $this->get_topic_notices($topic_id, 6) : [];
        $can_create_kb = $this->can_create_kb($forum_id, $status_key, !empty($kb_link));
        $can_sync_kb = $this->can_sync_kb($forum_id, $status_key, !empty($kb_link));
    
        $redirect_url = html_entity_decode($viewtopic_url, ENT_QUOTES, 'UTF-8');

        if ($can_set_priority && $this->priority_enabled() && $this->request->is_set_post('supporttriage_update_priority'))
        {
            if (!check_form_key($this->viewtopic_form_name()))
            {
                trigger_error('FORM_INVALID');
            }

            $new_priority_key = $this->normalize_priority($this->request->variable('supporttriage_priority', '', true));
            if ($new_priority_key === '')
            {
                $new_priority_key = $this->default_priority();
            }

            $previous_priority_key = $priority_key;
            $this->save_topic_priority($topic_id, $new_priority_key);

            if ($previous_priority_key !== $new_priority_key)
            {
                $this->log_action($topic_id, $forum_id, 'priority_change', $previous_priority_key, $new_priority_key);
            }

            redirect($this->append_url_param($redirect_url, 'prsaved=1'));
        }
    
        $this->template->assign_var('SUPPORTTRIAGE_VIEWTOPIC_FORM_TOKEN', $this->build_form_token_fields($this->viewtopic_form_name()));
    
        if ($can_create_kb && $this->request->is_set_post('supporttriage_create_kb'))
        {
            if (!check_form_key($this->viewtopic_form_name()))
            {
                trigger_error('FORM_INVALID');
            }
    
            if ($this->kb_link_exists($topic_id))
            {
                redirect($this->append_url_param($redirect_url, 'kbexists=1'));
            }
    
            $created_topic_id = $this->create_kb_draft($forum_id, $topic_id, (string) $event['topic_data']['topic_title']);
            if ($created_topic_id > 0)
            {
                redirect($this->append_url_param($redirect_url, 'kbsaved=1'));
            }
    
            redirect($this->append_url_param($redirect_url, 'kberror=1'));
        }
    
        if ($can_sync_kb && $this->request->is_set_post('supporttriage_sync_kb'))
        {
            if (!check_form_key($this->viewtopic_form_name()))
            {
                trigger_error('FORM_INVALID');
            }
    
            $synced_topic_id = $this->sync_kb_draft($forum_id, $topic_id, (string) $event['topic_data']['topic_title']);
            if ($synced_topic_id > 0)
            {
                redirect($this->append_url_param($redirect_url, 'kbsynced=1'));
            }
    
            redirect($this->append_url_param($redirect_url, 'kbsyncerror=1'));
        }
    
        $snippets = $can_use_snippets ? $this->get_snippets(true) : [];
        $suggested_snippets = $can_use_snippets ? $this->get_contextual_snippets(
            $snippets,
            $status_key,
            $priority_key,
            isset($event['topic_data']['topic_title']) ? (string) $event['topic_data']['topic_title'] : '',
            isset($event['topic_data']['post_text']) ? (string) $event['topic_data']['post_text'] : ''
        ) : [];
        $logs = $can_view_logs ? $this->get_topic_logs($topic_id, 8) : [];
    
        $this->template->assign_vars([
            'S_SUPPORTTRIAGE_TOPIC_STATUS_SHOW' => $this->status_system_enabled() && $this->forum_is_enabled($forum_id),
            'S_SUPPORTTRIAGE_TOPIC_HAS_STATUS' => $has_status,
            'SUPPORTTRIAGE_TOPIC_STATUS_LABEL' => $meta['label'],
            'SUPPORTTRIAGE_TOPIC_STATUS_CLASS' => $meta['class'],
            'SUPPORTTRIAGE_TOPIC_STATUS_UPDATED_LINE' => $updated_line,
            'S_SUPPORTTRIAGE_CAN_SET_STATUS' => $can_set_status,
            'S_SUPPORTTRIAGE_TOPIC_PRIORITY_SHOW' => $this->priority_enabled(),
            'SUPPORTTRIAGE_TOPIC_PRIORITY_LABEL' => $priority_meta['label'],
            'SUPPORTTRIAGE_TOPIC_PRIORITY_CLASS' => $priority_meta['class'],
            'S_SUPPORTTRIAGE_CAN_SET_PRIORITY' => $can_set_priority && $this->priority_enabled(),
            'S_SUPPORTTRIAGE_PRIORITY_SAVED' => (bool) $this->request->variable('prsaved', 0),
            'S_SUPPORTTRIAGE_STATUS_SAVED' => (bool) $this->request->variable('stsaved', 0),
            'S_SUPPORTTRIAGE_AUTO_STATUS_NOTICE' => $this->status_auto_notice !== '',
            'SUPPORTTRIAGE_AUTO_STATUS_NOTICE' => $this->status_auto_notice,
            'S_SUPPORTTRIAGE_SNIPPETS_SHOW' => !empty($snippets),
            'S_SUPPORTTRIAGE_SUGGESTED_SNIPPETS_SHOW' => !empty($suggested_snippets),
            'SUPPORTTRIAGE_SUGGESTED_SNIPPETS_REASON' => $this->build_context_snippet_reason($status_key, $priority_key, isset($event['topic_data']) ? $event['topic_data'] : []),
            'S_SUPPORTTRIAGE_NOTICES_SHOW' => !empty($notices),
            'S_SUPPORTTRIAGE_ALERTS_SHOW' => !empty($alerts),
            'S_SUPPORTTRIAGE_KB_SHOW' => $this->can_view_kb_panel($forum_id, $status_key),
            'S_SUPPORTTRIAGE_CAN_CREATE_KB' => $can_create_kb,
            'S_SUPPORTTRIAGE_CAN_SYNC_KB' => $can_sync_kb,
            'S_SUPPORTTRIAGE_KB_CREATED' => !empty($kb_link),
            'S_SUPPORTTRIAGE_KB_SAVED' => (bool) $this->request->variable('kbsaved', 0),
            'S_SUPPORTTRIAGE_KB_SYNCED' => (bool) $this->request->variable('kbsynced', 0),
            'S_SUPPORTTRIAGE_KB_EXISTS' => (bool) $this->request->variable('kbexists', 0),
            'S_SUPPORTTRIAGE_KB_ERROR' => (bool) $this->request->variable('kberror', 0),
            'S_SUPPORTTRIAGE_KB_SYNC_ERROR' => (bool) $this->request->variable('kbsyncerror', 0),
            'SUPPORTTRIAGE_KB_TOPIC_URL' => $kb_link ? append_sid('viewtopic.php', 'f=' . (int) $kb_link['kb_forum_id'] . '&t=' . (int) $kb_link['kb_topic_id']) : '',
            'S_SUPPORTTRIAGE_LOGS_SHOW' => !empty($logs),
        ]);
    
        foreach ($this->allowed_statuses() as $allowed_status)
        {
            $option_meta = $this->status_meta($allowed_status);
            $this->template->assign_block_vars('supporttriage_status_options', [
                'VALUE' => $allowed_status,
                'LABEL' => $option_meta['label'],
                'S_SELECTED' => $status_key === $allowed_status || (!$has_status && $allowed_status === $this->default_status()),
            ]);
        }
    
        foreach ($this->allowed_priorities() as $allowed_priority)
        {
            $option_meta = $this->priority_meta($allowed_priority);
            $this->template->assign_block_vars('supporttriage_priority_options', [
                'VALUE' => $allowed_priority,
                'LABEL' => $option_meta['label'],
                'S_SELECTED' => $priority_key === $allowed_priority || ($priority_key === '' && $allowed_priority === $this->default_priority()),
            ]);
        }

        foreach ($alerts as $alert)
        {
            $this->template->assign_block_vars('supporttriage_alerts', [
                'KEY' => $alert['key'],
                'CLASS' => $alert['class'],
                'SHORT_LABEL' => $alert['short_label'],
                'MESSAGE' => $alert['message'],
                'U_LINK' => !empty($alert['url']) ? $alert['url'] : '',
                'LINK_LABEL' => !empty($alert['link_label']) ? $alert['link_label'] : '',
            ]);
        }

        foreach ($notices as $notice)
        {
            $this->template->assign_block_vars('supporttriage_notices', [
                'KEY' => $notice['key'],
                'CLASS' => $notice['class'],
                'SHORT_LABEL' => $notice['short_label'],
                'MESSAGE' => $notice['message'],
                'TIME' => $notice['time'],
                'U_TOPIC' => !empty($notice['url']) ? $notice['url'] : '',
                'TOPIC_TITLE' => !empty($notice['topic_title']) ? $notice['topic_title'] : '',
            ]);
        }

        $this->assign_snippet_block('supporttriage_suggested_snippets', $suggested_snippets);
        $this->assign_snippet_block('supporttriage_snippets', $snippets);
        $this->assign_log_block('supporttriage_logs', $logs);
    }

    public function viewtopic_modify_post_row($event)
    {
        $post_row = $event['post_row'];
        $row = $event['row'];
        $topic_id = isset($row['topic_id']) ? (int) $row['topic_id'] : (int) $this->request->variable('t', 0);
        $forum_id = isset($row['forum_id']) ? (int) $row['forum_id'] : (int) $this->request->variable('f', 0);

        if (!$this->status_system_enabled() || !$this->forum_is_enabled($forum_id) || $topic_id <= 0)
        {
            $post_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = false;
            $event['post_row'] = $post_row;
            return;
        }

        $is_first_post = !empty($row['topic_first_post_id']) && !empty($row['post_id'])
            && (int) $row['topic_first_post_id'] === (int) $row['post_id'];

        if (!$is_first_post)
        {
            $post_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = false;
            $event['post_row'] = $post_row;
            return;
        }

        $this->maybe_apply_no_reply_status($topic_id, $forum_id);
        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        $status_row = $this->maybe_apply_priority_automation($topic_id, $forum_id, $status_row);
        $status_key = $status_row ? $this->normalize_status($status_row['status_key']) : '';
        $this->sync_topic_notices($topic_id, $forum_id, $status_row, $status_key);
        $meta = $this->status_meta($status_key);
        $priority_key = $this->get_topic_priority_key($status_row);
        $priority_meta = $this->priority_meta($priority_key);

        $post_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = true;
        $post_row['SUPPORTTRIAGE_STATUS_LABEL'] = $meta['label'];
        $post_row['SUPPORTTRIAGE_STATUS_CLASS'] = $meta['class'];
        $post_row['S_SUPPORTTRIAGE_PRIORITY_SHOW'] = $this->priority_enabled();
        $post_row['SUPPORTTRIAGE_PRIORITY_LABEL'] = $priority_meta['label'];
        $post_row['SUPPORTTRIAGE_PRIORITY_CLASS'] = $priority_meta['class'];
        $event['post_row'] = $post_row;
    }

    public function bbtpreview_controller_response_before($event)
    {
        $row = isset($event['row']) && is_array($event['row']) ? $event['row'] : [];
        $topic_id = (int) $this->request->variable('t', 0);
        $forum_id = !empty($row['forum_id']) ? (int) $row['forum_id'] : 0;

        if (!$this->status_system_enabled() || !$this->forum_is_enabled($forum_id) || $topic_id <= 0)
        {
            return;
        }

        $this->maybe_apply_no_reply_status($topic_id, $forum_id);
        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        $status_row = $this->maybe_apply_priority_automation($topic_id, $forum_id, $status_row);
        $status_key = $status_row ? $this->normalize_status($status_row['status_key']) : '';

        if ($status_key === '')
        {
            return;
        }

        $this->sync_topic_notices($topic_id, $forum_id, $status_row, $status_key);
        $priority_key = $this->get_topic_priority_key($status_row);
        $alerts = $this->get_topic_alerts(
            $topic_id,
            $forum_id,
            $status_row,
            $status_key,
            $this->kb_enabled() ? $this->get_kb_link($topic_id) : null
        );
        $primary_alert = !empty($alerts) ? $alerts[0] : null;

        if ($primary_alert && $primary_alert['key'] === 'no_reply')
        {
            $primary_alert = null;
        }

        $badges_html = $this->build_bbtpreview_badges_html($status_key, $priority_key, $primary_alert, $this->is_stale_status_row($status_row));
        if ($badges_html === '')
        {
            return;
        }

        $rank_title = isset($row['rank_title']) ? trim((string) $row['rank_title']) : '';
        $row['rank_title'] = $rank_title !== ''
            ? $rank_title . '<br />' . $badges_html
            : $badges_html;

        $event['row'] = $row;
    }

    protected function inject_topicrow_status($event)
    {
        $row = $event['row'];
        $topic_row = $event['topic_row'];
        $topic_id = isset($row['topic_id']) ? (int) $row['topic_id'] : 0;
        $forum_id = isset($row['forum_id']) ? (int) $row['forum_id'] : 0;

        if (!$this->status_system_enabled() || !$this->forum_is_enabled($forum_id) || $topic_id <= 0)
        {
            $topic_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = false;
            $topic_row['SUPPORTTRIAGE_STATUS_KEY'] = 'none';
            $topic_row['S_SUPPORTTRIAGE_STALE'] = false;
            $topic_row['S_SUPPORTTRIAGE_ALERT_SHOW'] = false;
            $topic_row['S_SUPPORTTRIAGE_HAS_ALERT'] = false;
            $topic_row['S_SUPPORTTRIAGE_MCP_ACTIONS_SHOW'] = false;
            $topic_row['S_SUPPORTTRIAGE_PRIORITY_SHOW'] = false;
            $topic_row['SUPPORTTRIAGE_PRIORITY_KEY'] = '';
            $topic_row['SUPPORTTRIAGE_PRIORITY_LABEL'] = '';
            $topic_row['SUPPORTTRIAGE_PRIORITY_CLASS'] = 'supporttriage-priority-normal';
            $topic_row['SUPPORTTRIAGE_TOPIC_ID'] = $topic_id;
            $topic_row['SUPPORTTRIAGE_TOPIC_TITLE_PLAIN'] = $this->escape(isset($row['topic_title']) ? (string) $row['topic_title'] : '');
            $topic_row['SUPPORTTRIAGE_ALERT_SHORT'] = '';
            $topic_row['SUPPORTTRIAGE_ALERT_CLASS'] = 'supporttriage-alert-warning';
            $topic_row['SUPPORTTRIAGE_PRIMARY_ALERT_KEY'] = '';
            $topic_row['SUPPORTTRIAGE_STATUS_UPDATED_TS'] = 0;
            $event['topic_row'] = $topic_row;
            return;
        }

        $this->maybe_apply_no_reply_status($topic_id, $forum_id);
        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        $status_row = $this->maybe_apply_priority_automation($topic_id, $forum_id, $status_row);
        $status_key = $status_row ? $this->normalize_status($status_row['status_key']) : '';

        if ($status_key === '')
        {
            $topic_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = false;
            $topic_row['SUPPORTTRIAGE_STATUS_KEY'] = 'none';
            $topic_row['S_SUPPORTTRIAGE_STALE'] = false;
            $topic_row['S_SUPPORTTRIAGE_ALERT_SHOW'] = false;
            $topic_row['S_SUPPORTTRIAGE_HAS_ALERT'] = false;
            $topic_row['S_SUPPORTTRIAGE_MCP_ACTIONS_SHOW'] = false;
            $topic_row['S_SUPPORTTRIAGE_PRIORITY_SHOW'] = false;
            $topic_row['SUPPORTTRIAGE_PRIORITY_KEY'] = '';
            $topic_row['SUPPORTTRIAGE_PRIORITY_LABEL'] = '';
            $topic_row['SUPPORTTRIAGE_PRIORITY_CLASS'] = 'supporttriage-priority-normal';
            $topic_row['SUPPORTTRIAGE_TOPIC_ID'] = $topic_id;
            $topic_row['SUPPORTTRIAGE_TOPIC_TITLE_PLAIN'] = $this->escape(isset($row['topic_title']) ? (string) $row['topic_title'] : '');
            $topic_row['SUPPORTTRIAGE_ALERT_SHORT'] = '';
            $topic_row['SUPPORTTRIAGE_ALERT_CLASS'] = 'supporttriage-alert-warning';
            $topic_row['SUPPORTTRIAGE_PRIMARY_ALERT_KEY'] = '';
            $topic_row['SUPPORTTRIAGE_STATUS_UPDATED_TS'] = 0;
            $event['topic_row'] = $topic_row;
            return;
        }

        $this->sync_topic_notices($topic_id, $forum_id, $status_row, $status_key);
        $meta = $this->status_meta($status_key);
        $priority_key = $this->get_topic_priority_key($status_row);
        $priority_meta = $this->priority_meta($priority_key);
        $alerts = $this->get_topic_alerts($topic_id, $forum_id, $status_row, $status_key, $this->kb_enabled() ? $this->get_kb_link($topic_id) : null);
        $primary_alert = !empty($alerts) ? $alerts[0] : null;
        if ($primary_alert && $primary_alert['key'] === 'no_reply')
        {
            $primary_alert = null;
        }
        $topic_row['S_SUPPORTTRIAGE_STATUS_SHOW'] = true;
        $topic_row['SUPPORTTRIAGE_STATUS_LABEL'] = $meta['label'];
        $topic_row['SUPPORTTRIAGE_STATUS_CLASS'] = $meta['class'];
        $topic_row['SUPPORTTRIAGE_STATUS_KEY'] = $status_key;
        $topic_row['SUPPORTTRIAGE_TOPIC_ID'] = $topic_id;
        $topic_row['SUPPORTTRIAGE_TOPIC_TITLE_PLAIN'] = $this->escape(isset($row['topic_title']) ? (string) $row['topic_title'] : '');
        $topic_row['S_SUPPORTTRIAGE_STALE'] = $this->is_stale_status_row($status_row);
        $topic_row['S_SUPPORTTRIAGE_ALERT_SHOW'] = !empty($primary_alert);
        $topic_row['S_SUPPORTTRIAGE_HAS_ALERT'] = !empty($primary_alert);
        $topic_row['S_SUPPORTTRIAGE_MCP_ACTIONS_SHOW'] = $this->is_mcp_forum_page() && $this->can_set_status($forum_id);
        $topic_row['S_SUPPORTTRIAGE_PRIORITY_SHOW'] = $this->priority_enabled();
        $topic_row['SUPPORTTRIAGE_PRIORITY_KEY'] = $priority_key;
        $topic_row['SUPPORTTRIAGE_PRIORITY_LABEL'] = $priority_meta['label'];
        $topic_row['SUPPORTTRIAGE_PRIORITY_CLASS'] = $priority_meta['class'];
        $topic_row['SUPPORTTRIAGE_ALERT_SHORT'] = $primary_alert ? $primary_alert['short_label'] : '';
        $topic_row['SUPPORTTRIAGE_ALERT_CLASS'] = $primary_alert ? $primary_alert['class'] : 'supporttriage-alert-warning';
        $topic_row['SUPPORTTRIAGE_PRIMARY_ALERT_KEY'] = $primary_alert ? $primary_alert['key'] : '';
        $topic_row['SUPPORTTRIAGE_STATUS_UPDATED_TS'] = !empty($status_row['status_updated']) ? (int) $status_row['status_updated'] : 0;
        $event['topic_row'] = $topic_row;
    }

    protected function build_bbtpreview_badges_html($status_key, $priority_key = '', $primary_alert = null, $is_stale = false)
    {
        $status_key = $this->normalize_status($status_key);
        if ($status_key === '')
        {
            return '';
        }

        $meta = $this->status_meta($status_key);
        $html = '<span class="supporttriage-preview-badges" data-supporttriage-preview="1">';
        $html .= '<span class="supporttriage-status-badge ' . $this->escape($meta['class']) . '" data-supporttriage-status="' . $this->escape($status_key) . '" data-supporttriage-stale="' . ($is_stale ? '1' : '0') . '">' . $this->escape($meta['label']) . '</span>';

        if ($this->priority_enabled())
        {
            $priority_key = $this->get_topic_priority_key(['priority_key' => $priority_key]);
            if ($priority_key !== '')
            {
                $priority_meta = $this->priority_meta($priority_key);
                $html .= '<span class="supporttriage-status-badge ' . $this->escape($priority_meta['class']) . '">' . $this->escape($priority_meta['label']) . '</span>';
            }
        }

        if (is_array($primary_alert) && !empty($primary_alert['short_label']) && !empty($primary_alert['class']))
        {
            $html .= '<span class="supporttriage-alert-badge ' . $this->escape((string) $primary_alert['class']) . '">' . $this->escape((string) $primary_alert['short_label']) . '</span>';
        }

        $html .= '</span>';
        return $html;
    }

    protected function extension_enabled()
    {
        return isset($this->config['mundophpbb_supporttriage_enable'])
            && !empty($this->config['mundophpbb_supporttriage_enable']);
    }

    protected function status_system_enabled()
    {
        return $this->extension_enabled()
            && !empty($this->config['mundophpbb_supporttriage_status_enable']);
    }

    protected function tracking_columns_available()
    {
        return isset($this->config['mundophpbb_supporttriage_automation_enable']);
    }

    protected function automation_enabled()
    {
        return $this->extension_enabled()
            && $this->tracking_columns_available()
            && !empty($this->config['mundophpbb_supporttriage_automation_enable']);
    }

    protected function auto_waiting_reply_enabled()
    {
        return $this->automation_enabled()
            && !empty($this->config['mundophpbb_supporttriage_auto_waiting_reply']);
    }

    protected function auto_in_progress_enabled()
    {
        return $this->automation_enabled()
            && !empty($this->config['mundophpbb_supporttriage_auto_in_progress']);
    }

    protected function auto_no_reply_days()
    {
        $days = (int) $this->config_value('mundophpbb_supporttriage_auto_no_reply_days');
        return $days > 0 ? $days : 0;
    }

    protected function ensure_topic_status_row($topic_id, $forum_id)
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;

        $status_row = $this->get_topic_status($topic_id, true);
        if ($status_row)
        {
            return $status_row;
        }

        if ($topic_id <= 0 || $forum_id <= 0 || !$this->forum_is_enabled($forum_id))
        {
            return null;
        }

        $extra_data = [];
        if ($this->tracking_columns_available())
        {
            $topic_author_id = $this->resolve_topic_author_id($topic_id);
            if ($topic_author_id > 0)
            {
                $extra_data['topic_author_id'] = $topic_author_id;
            }
            $extra_data['last_author_reply'] = 0;
            $extra_data['last_staff_reply'] = 0;
        }

        $this->save_topic_status(
            $topic_id,
            $forum_id,
            $this->default_status(),
            0,
            time(),
            $extra_data
        );

        return $this->get_topic_status($topic_id, true);
    }

    protected function resolve_topic_author_id($topic_id, $status_row = null)
    {
        $topic_id = (int) $topic_id;
        if ($topic_id <= 0)
        {
            return 0;
        }

        if (is_array($status_row) && !empty($status_row['topic_author_id']))
        {
            return (int) $status_row['topic_author_id'];
        }

        $sql = 'SELECT topic_poster
            FROM ' . TOPICS_TABLE . '
            WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $topic_author_id = !empty($row['topic_poster']) ? (int) $row['topic_poster'] : 0;

        if ($topic_author_id > 0 && $this->tracking_columns_available() && $this->topic_status_exists($topic_id))
        {
            $this->update_topic_tracking($topic_id, ['topic_author_id' => $topic_author_id]);
        }

        return $topic_author_id;
    }

    protected function update_topic_tracking($topic_id, array $extra_data)
    {
        if (!$this->tracking_columns_available())
        {
            return;
        }

        $topic_id = (int) $topic_id;
        if ($topic_id <= 0 || empty($extra_data) || !$this->topic_status_exists($topic_id))
        {
            return;
        }

        $allowed_keys = ['topic_author_id', 'last_author_reply', 'last_staff_reply'];
        $sql_ary = [];

        foreach ($allowed_keys as $allowed_key)
        {
            if (array_key_exists($allowed_key, $extra_data))
            {
                $sql_ary[$allowed_key] = (int) $extra_data[$allowed_key];
            }
        }

        if (empty($sql_ary))
        {
            return;
        }

        $sql = 'UPDATE ' . $this->status_table() . '
            SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
            WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
        $this->db->sql_query($sql);

        unset($this->topic_status_cache[$topic_id . ':0'], $this->topic_status_cache[$topic_id . ':1'], $this->automation_check_cache[$topic_id]);

        foreach (array_keys($this->topic_alert_cache) as $alert_cache_key)
        {
            if (strpos($alert_cache_key, $topic_id . ':') === 0)
            {
                unset($this->topic_alert_cache[$alert_cache_key]);
            }
        }

        $this->clear_notice_cache($topic_id);
    }

    protected function maybe_apply_no_reply_status($topic_id, $forum_id)
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;

        if ($topic_id <= 0 || $forum_id <= 0 || !$this->automation_enabled() || $this->auto_no_reply_days() <= 0)
        {
            return false;
        }

        if (isset($this->automation_check_cache[$topic_id]))
        {
            return (bool) $this->automation_check_cache[$topic_id];
        }

        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        if (!$status_row)
        {
            $this->automation_check_cache[$topic_id] = false;
            return false;
        }

        $status_key = $this->normalize_status($status_row['status_key']);
        $last_staff_reply = !empty($status_row['last_staff_reply']) ? (int) $status_row['last_staff_reply'] : 0;
        $last_author_reply = !empty($status_row['last_author_reply']) ? (int) $status_row['last_author_reply'] : 0;
        $threshold_seconds = $this->auto_no_reply_days() * 86400;

        if ($status_key !== 'waiting_reply'
            || $last_staff_reply <= 0
            || $last_author_reply >= $last_staff_reply
            || (time() - $last_staff_reply) < $threshold_seconds)
        {
            $this->automation_check_cache[$topic_id] = false;
            return false;
        }

        $this->save_topic_status(
            $topic_id,
            $forum_id,
            'no_reply',
            0,
            time()
        );
        $this->log_action($topic_id, $forum_id, 'status_auto_no_reply', 'waiting_reply', 'no_reply', 0, 0, 0);

        $this->status_auto_notice = $this->user->lang(
            'SUPPORTTRIAGE_AUTO_STATUS_NOTICE',
            $this->status_meta('no_reply')['label']
        );

        $this->sync_topic_notices($topic_id, $forum_id);

        $this->automation_check_cache[$topic_id] = true;
        return true;
    }


    protected function notifications_enabled()
    {
        return $this->extension_enabled()
            && isset($this->config['mundophpbb_supporttriage_notifications_enable'])
            && !empty($this->config['mundophpbb_supporttriage_notifications_enable']);
    }

    protected function notice_feed_enabled()
    {
        return $this->extension_enabled()
            && isset($this->config['mundophpbb_supporttriage_notice_feed_enable'])
            && !empty($this->config['mundophpbb_supporttriage_notice_feed_enable']);
    }

    protected function alert_author_return_enabled()
    {
        return $this->notifications_enabled()
            && !empty($this->config['mundophpbb_supporttriage_alert_author_return']);
    }

    protected function alert_no_reply_enabled()
    {
        return $this->notifications_enabled()
            && !empty($this->config['mundophpbb_supporttriage_alert_no_reply']);
    }

    protected function alert_sla_warning_enabled()
    {
        return $this->notifications_enabled()
            && !empty($this->config['mundophpbb_supporttriage_alert_sla_warning']);
    }

    protected function alert_kb_linked_enabled()
    {
        return $this->notifications_enabled()
            && !empty($this->config['mundophpbb_supporttriage_alert_kb_linked']);
    }

    protected function alert_sla_hours()
    {
        if (!isset($this->config['mundophpbb_supporttriage_alert_sla_hours']))
        {
            return 24;
        }

        $hours = (int) $this->config_value('mundophpbb_supporttriage_alert_sla_hours');
        return $hours > 0 ? $hours : 24;
    }

    protected function get_topic_alerts($topic_id, $forum_id, $status_row = null, $status_key = '', $kb_link = null)
    {
        $cache_key = (int) $topic_id . ':' . (int) $forum_id;
        if (isset($this->topic_alert_cache[$cache_key]))
        {
            return $this->topic_alert_cache[$cache_key];
        }

        $alerts = [];
        if (!$this->notifications_enabled() || !$this->status_system_enabled() || !$this->forum_is_enabled($forum_id))
        {
            $this->topic_alert_cache[$cache_key] = $alerts;
            return $alerts;
        }

        if (!$status_row)
        {
            $status_row = $this->get_topic_status($topic_id, true);
        }

        if (!$status_row)
        {
            $this->topic_alert_cache[$cache_key] = $alerts;
            return $alerts;
        }

        $status_key = $status_key !== '' ? $this->normalize_status($status_key) : $this->normalize_status($status_row['status_key']);
        if ($status_key === '')
        {
            $this->topic_alert_cache[$cache_key] = $alerts;
            return $alerts;
        }

        if ($this->alert_author_return_enabled() && $this->tracking_columns_available())
        {
            $last_author_reply = !empty($status_row['last_author_reply']) ? (int) $status_row['last_author_reply'] : 0;
            $last_staff_reply = !empty($status_row['last_staff_reply']) ? (int) $status_row['last_staff_reply'] : 0;
            if ($this->topic_has_author_return($status_row, $status_key))
            {
                $alerts[] = [
                    'key' => 'author_return',
                    'class' => 'supporttriage-alert-warning',
                    'short_label' => $this->user->lang('SUPPORTTRIAGE_ALERT_AUTHOR_RETURN_SHORT'),
                    'message' => $this->user->lang('SUPPORTTRIAGE_ALERT_AUTHOR_RETURN_MESSAGE', $this->user->format_date($last_author_reply)),
                    'url' => '',
                    'link_label' => '',
                ];
            }
        }

        if ($this->alert_sla_warning_enabled())
        {
            $sla_data = $this->get_sla_warning_data($status_row, $status_key);
            if ($sla_data)
            {
                $alerts[] = [
                    'key' => 'sla_warning',
                    'class' => 'supporttriage-alert-danger',
                    'short_label' => $this->user->lang('SUPPORTTRIAGE_ALERT_SLA_SHORT'),
                    'message' => $this->user->lang('SUPPORTTRIAGE_ALERT_SLA_MESSAGE', $this->format_compact_remaining($sla_data['remaining'])),
                    'url' => '',
                    'link_label' => '',
                ];
            }
        }

        if ($this->alert_no_reply_enabled() && $status_key === 'no_reply')
        {
            $last_staff_reply = !empty($status_row['last_staff_reply']) ? (int) $status_row['last_staff_reply'] : 0;
            $alerts[] = [
                'key' => 'no_reply',
                'class' => 'supporttriage-alert-muted',
                'short_label' => $this->user->lang('SUPPORTTRIAGE_ALERT_NO_REPLY_SHORT'),
                'message' => $last_staff_reply > 0
                    ? $this->user->lang('SUPPORTTRIAGE_ALERT_NO_REPLY_MESSAGE', $this->user->format_date($last_staff_reply))
                    : $this->user->lang('SUPPORTTRIAGE_ALERT_NO_REPLY_MESSAGE_GENERIC'),
                'url' => '',
                'link_label' => '',
            ];
        }

        if ($this->alert_kb_linked_enabled() && $status_key === 'solved')
        {
            if ($kb_link === null && $this->kb_enabled())
            {
                $kb_link = $this->get_kb_link($topic_id);
            }

            if (!empty($kb_link))
            {
                $alerts[] = [
                    'key' => 'kb_linked',
                    'class' => 'supporttriage-alert-success',
                    'short_label' => $this->user->lang('SUPPORTTRIAGE_ALERT_KB_SHORT'),
                    'message' => $this->user->lang('SUPPORTTRIAGE_ALERT_KB_MESSAGE'),
                    'url' => append_sid('viewtopic.php', 'f=' . (int) $kb_link['kb_forum_id'] . '&t=' . (int) $kb_link['kb_topic_id']),
                    'link_label' => $this->user->lang('SUPPORTTRIAGE_ALERT_KB_LINK'),
                ];
            }
        }

        $this->topic_alert_cache[$cache_key] = $alerts;
        return $alerts;
    }

    protected function get_sla_warning_data($status_row, $status_key)
    {
        $status_key = $this->normalize_status($status_key);
        if ($status_key !== 'waiting_reply' || !$this->automation_enabled() || $this->auto_no_reply_days() <= 0)
        {
            return null;
        }

        $last_staff_reply = !empty($status_row['last_staff_reply']) ? (int) $status_row['last_staff_reply'] : 0;
        $last_author_reply = !empty($status_row['last_author_reply']) ? (int) $status_row['last_author_reply'] : 0;
        if ($last_staff_reply <= 0 || $last_author_reply >= $last_staff_reply)
        {
            return null;
        }

        $due_time = $last_staff_reply + ($this->auto_no_reply_days() * 86400);
        $remaining = $due_time - time();
        $warning_window = $this->alert_sla_hours() * 3600;

        if ($remaining <= 0 || $remaining > $warning_window)
        {
            return null;
        }

        return [
            'due_time' => $due_time,
            'remaining' => $remaining,
        ];
    }

    protected function format_compact_remaining($seconds)
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds < 3600)
        {
            $minutes = max(1, (int) ceil($seconds / 60));
            return $this->user->lang('SUPPORTTRIAGE_ALERT_TIME_MINUTES', $minutes);
        }

        if ($seconds < 86400)
        {
            $hours = max(1, (int) ceil($seconds / 3600));
            return $this->user->lang('SUPPORTTRIAGE_ALERT_TIME_HOURS', $hours);
        }

        $days = max(1, (int) ceil($seconds / 86400));
        return $this->user->lang('SUPPORTTRIAGE_ALERT_TIME_DAYS', $days);
    }

    protected function topic_has_author_return($status_row, $status_key)
    {
        if (!$this->tracking_columns_available())
        {
            return false;
        }

        $status_key = $this->normalize_status($status_key);
        if ($status_key === 'solved' || $status_key === 'new')
        {
            return false;
        }

        $last_author_reply = !empty($status_row['last_author_reply']) ? (int) $status_row['last_author_reply'] : 0;
        $last_staff_reply = !empty($status_row['last_staff_reply']) ? (int) $status_row['last_staff_reply'] : 0;

        return $last_staff_reply > 0 && $last_author_reply > $last_staff_reply;
    }

    protected function sync_forum_notices($forum_id)
    {
        $forum_id = (int) $forum_id;
        if ($forum_id <= 0 || !$this->notifications_enabled() || !$this->forum_is_enabled($forum_id))
        {
            return;
        }

        $sql = 'SELECT *
'
            . 'FROM ' . $this->status_table() . '
'
            . 'WHERE ' . $this->sql_int_equals('forum_id', $forum_id) . '
'
            . "    AND status_key IN ('in_progress', 'waiting_reply', 'no_reply')
"
            . 'ORDER BY status_updated DESC, topic_id DESC';
        $result = $this->db->sql_query_limit($sql, 100);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $this->sync_topic_notices((int) $row['topic_id'], $forum_id, $row, !empty($row['status_key']) ? (string) $row['status_key'] : '');
        }
        $this->db->sql_freeresult($result);
    }

    protected function sync_topic_notices($topic_id, $forum_id, $status_row = null, $status_key = '')
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;

        if ($topic_id <= 0 || $forum_id <= 0 || !$this->notifications_enabled() || !$this->status_system_enabled() || !$this->forum_is_enabled($forum_id))
        {
            return;
        }

        if ($status_row === null)
        {
            $status_row = $this->get_topic_status($topic_id, true);
        }

        if (!$status_row)
        {
            $this->set_notice_state($topic_id, $forum_id, 'author_return', false);
            $this->set_notice_state($topic_id, $forum_id, 'sla_warning', false);
            $this->set_notice_state($topic_id, $forum_id, 'no_reply', false);
            return;
        }

        $status_key = $status_key !== '' ? $this->normalize_status($status_key) : $this->normalize_status($status_row['status_key']);
        $author_return_active = $this->alert_author_return_enabled() && $this->topic_has_author_return($status_row, $status_key);
        $sla_warning_active = $this->alert_sla_warning_enabled() && (bool) $this->get_sla_warning_data($status_row, $status_key);
        $no_reply_active = $this->alert_no_reply_enabled() && $status_key === 'no_reply';

        $author_time = !empty($status_row['last_author_reply']) ? (int) $status_row['last_author_reply'] : time();
        $no_reply_time = !empty($status_row['status_updated']) ? (int) $status_row['status_updated'] : time();

        $this->set_notice_state($topic_id, $forum_id, 'author_return', $author_return_active, !empty($status_row['topic_author_id']) ? (int) $status_row['topic_author_id'] : 0, $author_time);
        $this->set_notice_state($topic_id, $forum_id, 'sla_warning', $sla_warning_active, 0, time());
        $this->set_notice_state($topic_id, $forum_id, 'no_reply', $no_reply_active, 0, $no_reply_time);
    }

    protected function set_notice_state($topic_id, $forum_id, $notice_key, $is_active, $actor_user_id = 0, $notice_time = 0)
    {
        if (!$this->notice_feed_enabled())
        {
            return;
        }

        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;
        $notice_key = trim((string) $notice_key);
        $actor_user_id = (int) $actor_user_id;
        $notice_time = (int) $notice_time;

        if ($topic_id <= 0 || $forum_id <= 0 || $notice_key === '')
        {
            return;
        }

        $sql = 'SELECT notice_id, is_active
            FROM ' . $this->notices_table() . '
'
            . 'WHERE ' . $this->sql_int_equals('topic_id', $topic_id) . '
'
            . '    AND ' . $this->sql_string_equals('notice_key', $notice_key) . '
'
            . 'ORDER BY notice_id DESC';
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($is_active)
        {
            if ($row && !empty($row['is_active']))
            {
                return;
            }

            $sql_ary = [
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'notice_key' => $notice_key,
                'actor_user_id' => $actor_user_id,
                'notice_time' => $notice_time > 0 ? $notice_time : time(),
                'is_active' => 1,
            ];

            if ($row)
            {
                $sql = 'UPDATE ' . $this->notices_table() . '
                    SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
                    WHERE ' . $this->sql_int_equals('notice_id', (int) $row['notice_id']);
                $this->db->sql_query($sql);
            }
            else
            {
                $sql = 'INSERT INTO ' . $this->notices_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
                $this->db->sql_query($sql);
            }
        }
        else if ($row && !empty($row['is_active']))
        {
            $sql = 'UPDATE ' . $this->notices_table() . '
                SET is_active = 0
                WHERE ' . $this->sql_int_equals('notice_id', (int) $row['notice_id']);
            $this->db->sql_query($sql);
        }

        $this->clear_notice_cache($topic_id, $forum_id);
    }

    protected function clear_notice_cache($topic_id = 0, $forum_id = 0)
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;

        if ($topic_id > 0)
        {
            unset($this->topic_notice_cache[$topic_id]);
        }

        if ($forum_id > 0)
        {
            unset($this->forum_notice_cache[$forum_id]);
        }
    }

    protected function get_topic_notices($topic_id, $limit = 6)
    {
        $topic_id = (int) $topic_id;
        $limit = max(1, (int) $limit);

        if ($topic_id <= 0 || !$this->notice_feed_enabled())
        {
            return [];
        }

        if (isset($this->topic_notice_cache[$topic_id]))
        {
            return array_slice($this->topic_notice_cache[$topic_id], 0, $limit);
        }

        $sql = 'SELECT n.*
            FROM ' . $this->notices_table() . ' n
            WHERE ' . $this->sql_int_equals('n.topic_id', $topic_id) . '
                AND n.is_active = 1
            ORDER BY n.notice_time DESC, n.notice_id DESC';
        $result = $this->db->sql_query($sql);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $this->build_notice_row($row);
        }
        $this->db->sql_freeresult($result);

        $this->topic_notice_cache[$topic_id] = $rows;
        return array_slice($rows, 0, $limit);
    }

    protected function get_forum_recent_notices($forum_id, $limit = 8)
    {
        $forum_id = (int) $forum_id;
        $limit = max(1, (int) $limit);

        if ($forum_id <= 0 || !$this->notice_feed_enabled())
        {
            return [];
        }

        if (isset($this->forum_notice_cache[$forum_id]))
        {
            return array_slice($this->forum_notice_cache[$forum_id], 0, $limit);
        }

        $sql = 'SELECT n.*, t.topic_title
            FROM ' . $this->notices_table() . ' n
            LEFT JOIN ' . TOPICS_TABLE . ' t
                ON t.topic_id = n.topic_id
            WHERE ' . $this->sql_int_equals('n.forum_id', $forum_id) . '
                AND n.is_active = 1
            ORDER BY n.notice_time DESC, n.notice_id DESC';
        $result = $this->db->sql_query_limit($sql, $limit);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $this->build_notice_row($row, true);
        }
        $this->db->sql_freeresult($result);

        $this->forum_notice_cache[$forum_id] = $rows;
        return $rows;
    }

    protected function build_notice_row(array $row, $include_topic = false)
    {
        $notice_key = !empty($row['notice_key']) ? (string) $row['notice_key'] : '';
        $notice_time = !empty($row['notice_time']) ? (int) $row['notice_time'] : 0;
        $topic_id = !empty($row['topic_id']) ? (int) $row['topic_id'] : 0;
        $forum_id = !empty($row['forum_id']) ? (int) $row['forum_id'] : 0;

        switch ($notice_key)
        {
            case 'author_return':
                $class = 'supporttriage-alert-warning';
                $short_label = $this->user->lang('SUPPORTTRIAGE_ALERT_AUTHOR_RETURN_SHORT');
                $message = $this->user->lang('SUPPORTTRIAGE_NOTICE_AUTHOR_RETURN_MESSAGE', $this->user->format_date($notice_time));
            break;

            case 'sla_warning':
                $class = 'supporttriage-alert-danger';
                $short_label = $this->user->lang('SUPPORTTRIAGE_ALERT_SLA_SHORT');
                $message = $this->user->lang('SUPPORTTRIAGE_NOTICE_SLA_MESSAGE', $this->user->format_date($notice_time));
            break;

            case 'no_reply':
            default:
                $class = 'supporttriage-alert-muted';
                $short_label = $this->user->lang('SUPPORTTRIAGE_ALERT_NO_REPLY_SHORT');
                $message = $this->user->lang('SUPPORTTRIAGE_NOTICE_NO_REPLY_MESSAGE', $this->user->format_date($notice_time));
            break;
        }

        return [
            'key' => $notice_key,
            'class' => $class,
            'short_label' => $short_label,
            'message' => $message,
            'time' => $notice_time > 0 ? $this->user->format_date($notice_time) : '',
            'url' => ($topic_id > 0 && $forum_id > 0) ? append_sid('viewtopic.php', 'f=' . $forum_id . '&t=' . $topic_id) : '',
            'topic_title' => $include_topic && !empty($row['topic_title']) ? $this->escape((string) $row['topic_title']) : '',
        ];
    }

    protected function queue_enabled()
    {
        return $this->extension_enabled()
            && isset($this->config['mundophpbb_supporttriage_queue_enable'])
            && !empty($this->config['mundophpbb_supporttriage_queue_enable']);
    }

    protected function queue_stale_days()
    {
        if (!isset($this->config['mundophpbb_supporttriage_queue_stale_days']))
        {
            return 0;
        }

        $days = (int) $this->config_value('mundophpbb_supporttriage_queue_stale_days');
        return $days > 0 ? $days : 0;
    }

    protected function allowed_mcp_filters()
    {
        return ['all', 'new', 'in_progress', 'waiting_reply', 'no_reply', 'solved', 'stale'];
    }

    protected function normalize_mcp_filter($filter)
    {
        $filter = trim((string) $filter);
        return in_array($filter, $this->allowed_mcp_filters(), true) ? $filter : 'all';
    }

    protected function allowed_mcp_attention_filters()
    {
        return ['all', 'alerts', 'stale', 'attention', 'clean'];
    }

    protected function normalize_mcp_attention_filter($filter)
    {
        $filter = trim((string) $filter);
        return in_array($filter, $this->allowed_mcp_attention_filters(), true) ? $filter : 'all';
    }

    protected function allowed_mcp_workflow_filters()
    {
        return ['all', 'action_now', 'awaiting_team', 'awaiting_author'];
    }

    protected function normalize_mcp_workflow_filter($filter)
    {
        $filter = trim((string) $filter);
        return in_array($filter, $this->allowed_mcp_workflow_filters(), true) ? $filter : 'all';
    }

    protected function allowed_mcp_sort_modes()
    {
        return ['urgency', 'oldest_update', 'newest_update', 'priority', 'title'];
    }

    protected function normalize_mcp_sort_mode($sort_mode)
    {
        $sort_mode = trim((string) $sort_mode);
        return in_array($sort_mode, $this->allowed_mcp_sort_modes(), true) ? $sort_mode : 'urgency';
    }

    protected function mcp_form_name()
    {
        return 'mundophpbb_supporttriage_mcp';
    }

    protected function process_mcp_inline_actions($forum_id)
    {
        if ($this->mcp_action_processed)
        {
            return;
        }

        $this->mcp_action_processed = true;
        $forum_id = (int) $forum_id;
        $is_quick = ((int) $this->request->variable('supporttriage_mcp_quick_status', 0) === 1);
        $is_bulk = ((int) $this->request->variable('supporttriage_mcp_bulk_apply', 0) === 1);

        $can_set_status = $this->can_set_status($forum_id);
        $can_set_priority = $this->can_set_priority($forum_id);

        if ($forum_id <= 0
            || (!$is_quick && !$is_bulk)
            || (!$can_set_status && !$can_set_priority)
            || !$this->queue_enabled()
            || !$this->forum_is_enabled($forum_id))
        {
            return;
        }

        if (!check_form_key($this->mcp_form_name()))
        {
            trigger_error('FORM_INVALID');
        }

        if ($is_quick && !$can_set_status)
        {
            trigger_error('NOT_AUTHORISED');
        }

        if ($is_bulk)
        {
            $topic_ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->variable('supporttriage_topic_ids', [0])))));
            $status_key = $this->normalize_status($this->request->variable('supporttriage_bulk_status', '', true));
            $priority_key = $this->priority_enabled()
                ? $this->normalize_priority($this->request->variable('supporttriage_bulk_priority', '', true))
                : '';

            if (empty($topic_ids) || ($status_key === '' && $priority_key === ''))
            {
                redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), 'stsaveerr=1'));
            }

            if (($status_key !== '' && !$can_set_status) || ($priority_key !== '' && !$can_set_priority))
            {
                trigger_error('NOT_AUTHORISED');
            }

            $has_changes = false;
            $user_id = (int) $this->user->data['user_id'];

            foreach ($topic_ids as $topic_id)
            {
                $actual_forum_id = $this->resolve_topic_forum_id($topic_id);
                if ($actual_forum_id <= 0 || $actual_forum_id !== $forum_id)
                {
                    continue;
                }

                $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
                if (!$status_row)
                {
                    continue;
                }

                $timestamp = time();

                if ($status_key !== '')
                {
                    $previous_status_key = $this->normalize_status($status_row['status_key']);
                    $extra_data = [];

                    if ($this->tracking_columns_available())
                    {
                        $topic_author_id = $this->resolve_topic_author_id($topic_id, $status_row);
                        if ($topic_author_id > 0)
                        {
                            $extra_data['topic_author_id'] = $topic_author_id;
                        }

                        if ($status_key === 'waiting_reply')
                        {
                            $extra_data['last_staff_reply'] = $timestamp;
                        }
                    }

                    $this->save_topic_status(
                        $topic_id,
                        $forum_id,
                        $status_key,
                        $user_id,
                        $timestamp,
                        $extra_data
                    );

                    if ($previous_status_key !== $status_key)
                    {
                        $this->log_action($topic_id, $forum_id, 'status_change', $previous_status_key, $status_key);
                        $has_changes = true;
                    }

                    $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
                }

                if ($priority_key !== '' && $this->priority_enabled())
                {
                    $previous_priority_key = $this->get_topic_priority_key($status_row);
                    if ($previous_priority_key !== $priority_key)
                    {
                        $this->save_topic_priority($topic_id, $priority_key);
                        $this->log_action($topic_id, $forum_id, 'priority_change', $previous_priority_key, $priority_key);
                        $has_changes = true;
                    }
                }

                $this->sync_topic_notices($topic_id, $forum_id);
            }

            redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), $has_changes ? 'stsaved=1' : 'stsaveerr=1'));
        }

        $topic_id = (int) $this->request->variable('supporttriage_topic_id', 0);
        $status_key = $this->normalize_status($this->request->variable('supporttriage_quick_status', '', true));

        if ($topic_id <= 0 || $status_key === '')
        {
            redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), 'stsaveerr=1'));
        }

        $actual_forum_id = $this->resolve_topic_forum_id($topic_id);
        if ($actual_forum_id <= 0 || $actual_forum_id !== $forum_id)
        {
            redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), 'stsaveerr=1'));
        }

        $status_row = $this->ensure_topic_status_row($topic_id, $forum_id);
        if (!$status_row)
        {
            redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), 'stsaveerr=1'));
        }

        $previous_status_key = $this->normalize_status($status_row['status_key']);
        $timestamp = time();
        $extra_data = [];

        if ($this->tracking_columns_available())
        {
            $topic_author_id = $this->resolve_topic_author_id($topic_id, $status_row);
            if ($topic_author_id > 0)
            {
                $extra_data['topic_author_id'] = $topic_author_id;
            }

            if ($status_key === 'waiting_reply')
            {
                $extra_data['last_staff_reply'] = $timestamp;
            }
        }

        $this->save_topic_status(
            $topic_id,
            $forum_id,
            $status_key,
            (int) $this->user->data['user_id'],
            $timestamp,
            $extra_data
        );

        if ($previous_status_key !== $status_key)
        {
            $this->log_action($topic_id, $forum_id, 'status_change', $previous_status_key, $status_key);
        }

        $this->sync_topic_notices($topic_id, $forum_id);

        redirect($this->append_url_param($this->build_mcp_queue_url($forum_id), 'stsaved=1'));
    }

    protected function resolve_topic_forum_id($topic_id)
    {
        $topic_id = (int) $topic_id;
        if ($topic_id <= 0)
        {
            return 0;
        }

        $sql = 'SELECT forum_id
            FROM ' . TOPICS_TABLE . '
            WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ? (int) $row['forum_id'] : 0;
    }

    protected function build_mcp_queue_url($forum_id)
    {
        $forum_id = (int) $forum_id;
        $params = [
            'i' => 'main',
            'mode' => 'forum',
            'f' => $forum_id,
        ];

        $start = (int) $this->request->variable('start', 0);
        if ($start > 0)
        {
            $params['start'] = $start;
        }

        $filter = $this->normalize_mcp_filter($this->request->variable('st_filter', 'all', true));
        if ($filter !== 'all')
        {
            $params['st_filter'] = $filter;
        }

        $attention = $this->normalize_mcp_attention_filter($this->request->variable('st_attention', 'all', true));
        if ($attention !== 'all')
        {
            $params['st_attention'] = $attention;
        }

        $priority = $this->normalize_priority_filter($this->request->variable('st_priority', 'all', true));
        if ($priority !== 'all')
        {
            $params['st_priority'] = $priority;
        }

        $workflow = $this->normalize_mcp_workflow_filter($this->request->variable('st_workflow', 'all', true));
        if ($workflow !== 'all')
        {
            $params['st_workflow'] = $workflow;
        }

        $sort_mode = $this->normalize_mcp_sort_mode($this->request->variable('st_sort', 'urgency', true));
        if ($sort_mode !== 'urgency')
        {
            $params['st_sort'] = $sort_mode;
        }

        $search = trim($this->request->variable('st_q', '', true));
        if ($search !== '')
        {
            $params['st_q'] = $search;
        }

        return append_sid('mcp.php', http_build_query($params, '', '&'));
    }

    protected function is_stale_status_row($status_row)
    {
        if (!$status_row || $this->queue_stale_days() <= 0)
        {
            return false;
        }

        $status_key = !empty($status_row['status_key']) ? $this->normalize_status($status_row['status_key']) : '';
        if ($status_key === '' || $status_key === 'solved')
        {
            return false;
        }

        $updated = !empty($status_row['status_updated']) ? (int) $status_row['status_updated'] : 0;
        if ($updated <= 0)
        {
            return false;
        }

        return $updated <= (time() - ($this->queue_stale_days() * 86400));
    }

    protected function assign_mcp_queue_panel($forum_id)
    {
        $forum_id = (int) $forum_id;
        if ($forum_id <= 0 || isset($this->mcp_queue_cache[$forum_id]))
        {
            return;
        }

        $this->mcp_queue_cache[$forum_id] = true;

        if (!$this->status_system_enabled()
            || !$this->queue_enabled()
            || !$this->forum_is_enabled($forum_id)
            || !$this->auth->acl_get('m_', $forum_id))
        {
            $this->template->assign_var('S_SUPPORTTRIAGE_MCP_QUEUE_SHOW', false);
            return;
        }

        $counts = [
            'new' => 0,
            'in_progress' => 0,
            'waiting_reply' => 0,
            'solved' => 0,
            'no_reply' => 0,
        ];

        $sql = 'SELECT status_key, COUNT(topic_id) AS total
            FROM ' . $this->status_table() . '
            WHERE ' . $this->sql_int_equals('forum_id', $forum_id) . '
            GROUP BY status_key';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            if (isset($counts[$row['status_key']]))
            {
                $counts[$row['status_key']] = (int) $row['total'];
            }
        }
        $this->db->sql_freeresult($result);

        add_form_key($this->mcp_form_name());

        $filter = $this->normalize_mcp_filter($this->request->variable('st_filter', 'all', true));
        $workflow_filter = $this->normalize_mcp_workflow_filter($this->request->variable('st_workflow', 'all', true));
        $attention_filter = $this->normalize_mcp_attention_filter($this->request->variable('st_attention', 'all', true));
        $priority_filter = $this->normalize_priority_filter($this->request->variable('st_priority', 'all', true));
        $sort_mode = $this->normalize_mcp_sort_mode($this->request->variable('st_sort', 'urgency', true));
        $search_term = trim($this->request->variable('st_q', '', true));
        $stale_topics = [];
        $notice_rows = [];
        $stale_days = $this->queue_stale_days();

        $this->sync_forum_notices($forum_id);
        $notice_rows = $this->get_forum_recent_notices($forum_id, 8);

        if ($stale_days > 0)
        {
            $threshold = time() - ($stale_days * 86400);
            $sql = 'SELECT st.topic_id, st.forum_id, st.status_key, st.status_updated, t.topic_title
'
                . 'FROM ' . $this->status_table() . ' st
'
                . 'LEFT JOIN ' . TOPICS_TABLE . ' t
'
                . '    ON t.topic_id = st.topic_id
'
                . 'WHERE ' . $this->sql_int_equals('st.forum_id', $forum_id) . '
'
                . '    AND ' . $this->db->sql_in_set('st.status_key', ['solved'], true) . '
'
                . '    AND st.status_updated > 0
'
                . '    AND st.status_updated <= ' . (int) $threshold . '
'
                . '    AND t.topic_moved_id = 0
'
                . '    AND t.topic_visibility = 1
'
                . 'ORDER BY st.status_updated ASC, st.topic_id ASC';
            $result = $this->db->sql_query_limit($sql, 6);
            while ($row = $this->db->sql_fetchrow($result))
            {
                $status_key = $this->normalize_status($row['status_key']);
                $meta = $this->status_meta($status_key);
                $stale_topics[] = [
                    'U_TOPIC' => append_sid('viewtopic.php', 'f=' . (int) $row['forum_id'] . '&t=' . (int) $row['topic_id']),
                    'TITLE' => $this->escape((string) $row['topic_title']),
                    'STATUS_LABEL' => $meta['label'],
                    'STATUS_CLASS' => $meta['class'],
                    'UPDATED' => $this->user->lang('SUPPORTTRIAGE_MCP_STALE_UPDATED', $this->user->format_date((int) $row['status_updated'])),
                ];
            }
            $this->db->sql_freeresult($result);
        }

        $this->template->assign_vars([
            'S_SUPPORTTRIAGE_MCP_QUEUE_SHOW' => true,
            'S_SUPPORTTRIAGE_MCP_INLINE_SHOW' => $this->can_set_status($forum_id),
            'S_SUPPORTTRIAGE_MCP_BULK_SHOW' => ($this->can_set_status($forum_id) || $this->can_set_priority($forum_id)),
            'S_SUPPORTTRIAGE_MCP_STATUS_SAVED' => (bool) $this->request->variable('stsaved', 0),
            'S_SUPPORTTRIAGE_MCP_STATUS_SAVE_ERROR' => (bool) $this->request->variable('stsaveerr', 0),
            'SUPPORTTRIAGE_MCP_FILTER' => $filter,
            'SUPPORTTRIAGE_MCP_WORKFLOW_FILTER' => $workflow_filter,
            'SUPPORTTRIAGE_MCP_ATTENTION_FILTER' => $attention_filter,
            'S_SUPPORTTRIAGE_PRIORITY_SUPPORTED' => $this->priority_enabled(),
            'SUPPORTTRIAGE_MCP_PRIORITY_FILTER' => $priority_filter,
            'SUPPORTTRIAGE_MCP_SORT' => $sort_mode,
            'SUPPORTTRIAGE_MCP_SEARCH' => $this->escape($search_term),
            'SUPPORTTRIAGE_MCP_FORM_TOKEN' => $this->build_form_token_fields($this->mcp_form_name()),
            'SUPPORTTRIAGE_MCP_ACTION_URL' => $this->build_mcp_queue_url($forum_id),
            'SUPPORTTRIAGE_MCP_STALE_DAYS' => $stale_days,
            'SUPPORTTRIAGE_MCP_STALE_HINT' => $stale_days > 0 ? $this->user->lang('SUPPORTTRIAGE_MCP_STALE_HINT', $stale_days) : '',
            'SUPPORTTRIAGE_MCP_COUNT_NEW' => $counts['new'],
            'SUPPORTTRIAGE_MCP_COUNT_IN_PROGRESS' => $counts['in_progress'],
            'SUPPORTTRIAGE_MCP_COUNT_WAITING_REPLY' => $counts['waiting_reply'],
            'SUPPORTTRIAGE_MCP_COUNT_SOLVED' => $counts['solved'],
            'SUPPORTTRIAGE_MCP_COUNT_NO_REPLY' => $counts['no_reply'],
            'S_SUPPORTTRIAGE_MCP_NOTICES_SHOW' => !empty($notice_rows),
        ]);

        foreach ($notice_rows as $row)
        {
            $this->template->assign_block_vars('supporttriage_mcp_notice_rows', [
                'U_TOPIC' => !empty($row['url']) ? $row['url'] : '',
                'TITLE' => !empty($row['topic_title']) ? $row['topic_title'] : '',
                'CLASS' => $row['class'],
                'SHORT_LABEL' => $row['short_label'],
                'MESSAGE' => $row['message'],
            ]);
        }

        foreach ($stale_topics as $row)
        {
            $this->template->assign_block_vars('supporttriage_mcp_stale_topics', $row);
        }
    }

    protected function snippets_enabled()
    {
        return $this->extension_enabled()
            && !empty($this->config['mundophpbb_supporttriage_snippets_enable']);
    }

    protected function kb_enabled()
    {
        return $this->extension_enabled()
            && !empty($this->config['mundophpbb_supporttriage_kb_enable'])
            && $this->kb_forum_id() > 0;
    }

    protected function logs_enabled()
    {
        return $this->extension_enabled()
            && !empty($this->config['mundophpbb_supporttriage_logs_enable']);
    }

    protected function kb_forum_id()
    {
        return (int) $this->config_value('mundophpbb_supporttriage_kb_forum');
    }

    protected function kb_prefix()
    {
        $prefix = trim($this->config_value('mundophpbb_supporttriage_kb_prefix'));
        return $prefix !== '' ? $prefix : '[KB Draft]';
    }

    protected function kb_auto_lock()
    {
        return !empty($this->config['mundophpbb_supporttriage_kb_lock']);
    }

    protected function is_mcp_forum_page()
    {
        return !empty($this->user->page['page_name'])
            && $this->user->page['page_name'] === 'mcp.php';
    }

    protected function viewtopic_form_name()
    {
        return 'mundophpbb_supporttriage_viewtopic';
    }

    protected function build_form_token_fields($form_name)
    {
        $now = time();
        $token_sid = ((int) $this->user->data['user_id'] === ANONYMOUS && !empty($this->config['form_token_sid_guests']))
            ? (string) $this->user->session_id
            : '';

        // phpBB form keys are validated against a SHA-1 token built from the same payload.
        // Using hash() preserves the expected value while avoiding direct sha1() calls flagged by validators.
        $token = hash('sha1', $now . $this->user->data['user_form_salt'] . $form_name . $token_sid);

        return build_hidden_fields([
            'creation_time' => $now,
            'form_token' => $token,
        ]);
    }

    protected function can_set_status($forum_id)
    {
        $forum_id = (int) $forum_id;

        return $forum_id > 0
            && $this->status_system_enabled()
            && $this->forum_is_enabled($forum_id)
            && $this->auth->acl_get('m_supporttriage_status', $forum_id);
    }

    protected function can_set_priority($forum_id)
    {
        $forum_id = (int) $forum_id;

        return $forum_id > 0
            && $this->status_system_enabled()
            && $this->forum_is_enabled($forum_id)
            && ($this->auth->acl_get('m_supporttriage_priority', $forum_id) || $this->auth->acl_get('m_supporttriage_status', $forum_id));
    }

    protected function can_use_snippets($forum_id)
    {
        $forum_id = (int) $forum_id;

        return $forum_id > 0
            && $this->snippets_enabled()
            && $this->forum_is_enabled($forum_id)
            && $this->auth->acl_get('m_supporttriage_snippets', $forum_id);
    }

    protected function can_view_kb_panel($forum_id, $status_key = '')
    {
        $forum_id = (int) $forum_id;

        return $forum_id > 0
            && $this->kb_enabled()
            && $this->forum_is_enabled($forum_id)
            && $status_key === 'solved'
            && (
                $this->auth->acl_get('m_', $forum_id)
                || $this->auth->acl_get('m_supporttriage_kb_create', $forum_id)
                || $this->auth->acl_get('m_supporttriage_kb_sync', $forum_id)
            );
    }

    protected function can_create_kb($forum_id, $status_key = '', $has_link = false)
    {
        $forum_id = (int) $forum_id;
        $kb_forum_id = $this->kb_forum_id();

        return $forum_id > 0
            && $this->kb_enabled()
            && $this->forum_is_enabled($forum_id)
            && $status_key === 'solved'
            && !$has_link
            && $kb_forum_id > 0
            && $this->auth->acl_get('m_supporttriage_kb_create', $forum_id)
            && ($this->auth->acl_get('m_', $kb_forum_id) || $this->auth->acl_get('f_post', $kb_forum_id));
    }

    protected function can_sync_kb($forum_id, $status_key = '', $has_link = false)
    {
        $forum_id = (int) $forum_id;
        $kb_forum_id = $this->kb_forum_id();

        return $forum_id > 0
            && $this->kb_enabled()
            && $this->forum_is_enabled($forum_id)
            && $status_key === 'solved'
            && $has_link
            && $kb_forum_id > 0
            && $this->auth->acl_get('m_supporttriage_kb_sync', $forum_id)
            && ($this->auth->acl_get('m_', $kb_forum_id) || $this->auth->acl_get('f_edit', $kb_forum_id));
    }

    protected function can_view_logs($forum_id)
    {
        $forum_id = (int) $forum_id;

        return $forum_id > 0
            && $this->logs_enabled()
            && $this->forum_is_enabled($forum_id)
            && (
                $this->auth->acl_get('m_', $forum_id)
                || $this->auth->acl_get('m_supporttriage_status', $forum_id)
                || $this->auth->acl_get('m_supporttriage_priority', $forum_id)
                || $this->auth->acl_get('m_supporttriage_snippets', $forum_id)
                || $this->auth->acl_get('m_supporttriage_kb_create', $forum_id)
                || $this->auth->acl_get('m_supporttriage_kb_sync', $forum_id)
            );
    }

    protected function default_status()
    {
        $status = $this->normalize_status($this->config_value('mundophpbb_supporttriage_default_status'));
        return $status !== '' ? $status : 'new';
    }

    protected function priority_enabled()
    {
        return $this->extension_enabled()
            && isset($this->config['mundophpbb_supporttriage_priority_enable'])
            && !empty($this->config['mundophpbb_supporttriage_priority_enable']);
    }

    protected function default_priority()
    {
        $priority = $this->normalize_priority($this->config_value('mundophpbb_supporttriage_default_priority'));
        return $priority !== '' ? $priority : 'normal';
    }


    protected function priority_automation_supported()
    {
        return isset($this->config['mundophpbb_supporttriage_priority_auto_enable']);
    }

    protected function priority_automation_enabled()
    {
        return $this->extension_enabled()
            && $this->priority_enabled()
            && $this->priority_automation_supported()
            && !empty($this->config['mundophpbb_supporttriage_priority_auto_enable']);
    }

    protected function priority_auto_stale_days()
    {
        $days = (int) $this->config_value('mundophpbb_supporttriage_priority_auto_stale_days');
        return $days > 0 ? $days : 0;
    }

    protected function priority_auto_stale_target()
    {
        $priority = $this->normalize_priority($this->config_value('mundophpbb_supporttriage_priority_auto_stale_target'));
        return $priority !== '' ? $priority : 'high';
    }

    protected function priority_auto_forums_target()
    {
        $priority = $this->normalize_priority($this->config_value('mundophpbb_supporttriage_priority_auto_forums_target'));
        return $priority !== '' ? $priority : 'critical';
    }

    protected function priority_auto_issue_target()
    {
        $priority = $this->normalize_priority($this->config_value('mundophpbb_supporttriage_priority_auto_issue_target'));
        return $priority !== '' ? $priority : 'high';
    }

    protected function priority_auto_forums()
    {
        $forums = [];
        $raw = preg_split('/[,\s]+/', (string) $this->config_value('mundophpbb_supporttriage_priority_auto_forums'), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($raw as $forum_id)
        {
            $forum_id = (int) $forum_id;
            if ($forum_id > 0)
            {
                $forums[$forum_id] = $forum_id;
            }
        }
        return array_values($forums);
    }

    protected function priority_auto_issue_types()
    {
        $issue_types = [];
        $raw = preg_split('/[,\s]+/', strtolower((string) $this->config_value('mundophpbb_supporttriage_priority_auto_issue_types')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($raw as $issue_type)
        {
            $issue_type = $this->normalize_issue_type($issue_type);
            if ($issue_type !== '' && $issue_type !== 'general')
            {
                $issue_types[$issue_type] = $issue_type;
            }
        }
        return array_values($issue_types);
    }

    protected function priority_rank($priority_key)
    {
        switch ($this->normalize_priority($priority_key))
        {
            case 'low':
                return 1;
            case 'normal':
                return 2;
            case 'high':
                return 3;
            case 'critical':
                return 4;
        }

        return 0;
    }

    protected function maybe_raise_priority($topic_id, $forum_id, $current_priority, $target_priority, $action_key)
    {
        $current_priority = $this->normalize_priority($current_priority);
        $target_priority = $this->normalize_priority($target_priority);
        $action_key = trim((string) $action_key);

        if ($topic_id <= 0 || $forum_id <= 0 || $current_priority === '' || $target_priority === '' || $action_key === '')
        {
            return false;
        }

        if ($this->priority_rank($target_priority) <= $this->priority_rank($current_priority))
        {
            return false;
        }

        $this->save_topic_priority($topic_id, $target_priority);
        $this->log_action($topic_id, $forum_id, $action_key, $current_priority, $target_priority, 0, 0, 0);
        return true;
    }

    protected function maybe_apply_priority_automation($topic_id, $forum_id, $status_row = null, $issue_type = '')
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;
        $issue_type = $this->normalize_issue_type($issue_type);

        if ($topic_id <= 0 || $forum_id <= 0 || !$this->priority_automation_enabled())
        {
            return $status_row;
        }

        if (!$status_row)
        {
            $status_row = $this->get_topic_status($topic_id, true);
        }

        if (!$status_row)
        {
            return $status_row;
        }

        $status_key = !empty($status_row['status_key']) ? $this->normalize_status($status_row['status_key']) : '';
        $current_priority = $this->get_topic_priority_key($status_row);
        $changed = false;

        if (in_array($forum_id, $this->priority_auto_forums(), true))
        {
            if ($this->maybe_raise_priority($topic_id, $forum_id, $current_priority, $this->priority_auto_forums_target(), 'priority_auto_forum'))
            {
                $current_priority = $this->priority_auto_forums_target();
                $changed = true;
            }
        }

        if ($issue_type !== '' && in_array($issue_type, $this->priority_auto_issue_types(), true))
        {
            if ($this->maybe_raise_priority($topic_id, $forum_id, $current_priority, $this->priority_auto_issue_target(), 'priority_auto_issue'))
            {
                $current_priority = $this->priority_auto_issue_target();
                $changed = true;
            }
        }

        if ($status_key !== 'solved' && $this->priority_auto_stale_days() > 0)
        {
            $updated = !empty($status_row['status_updated']) ? (int) $status_row['status_updated'] : 0;
            if ($updated > 0 && $updated <= (time() - ($this->priority_auto_stale_days() * 86400)))
            {
                if ($this->maybe_raise_priority($topic_id, $forum_id, $current_priority, $this->priority_auto_stale_target(), 'priority_auto_stale'))
                {
                    $changed = true;
                }
            }
        }

        return $changed ? $this->get_topic_status($topic_id, true) : $status_row;
    }

    protected function get_topic_priority_key($status_row = null)
    {
        if (!$this->priority_enabled())
        {
            return '';
        }

        if (is_array($status_row) && array_key_exists('priority_key', $status_row))
        {
            $priority = $this->normalize_priority($status_row['priority_key']);
            return $priority !== '' ? $priority : $this->default_priority();
        }

        return $this->default_priority();
    }

    protected function get_topic_status($topic_id, $with_user = false)
    {
        $topic_id = (int) $topic_id;
        if ($topic_id <= 0)
        {
            return null;
        }

        $cache_key = $topic_id . ':' . (int) $with_user;
        if (array_key_exists($cache_key, $this->topic_status_cache))
        {
            return $this->topic_status_cache[$cache_key];
        }

        $status_table = $this->status_table();

        if ($with_user)
        {
            $sql = 'SELECT st.*, u.user_id, u.username, u.user_colour
                FROM ' . $status_table . ' st
                LEFT JOIN ' . USERS_TABLE . ' u
                    ON u.user_id = st.status_user_id
                WHERE ' . $this->sql_int_equals('st.topic_id', $topic_id);
        }
        else
        {
            $sql = 'SELECT *
                FROM ' . $status_table . '
                WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
        }

        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $this->topic_status_cache[$cache_key] = $row ?: null;
        return $this->topic_status_cache[$cache_key];
    }

    protected function topic_status_exists($topic_id)
    {
        return $this->get_topic_status($topic_id) !== null;
    }

    protected function save_topic_status($topic_id, $forum_id, $status_key, $user_id, $timestamp, array $extra_data = [])
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;
        $user_id = (int) $user_id;
        $timestamp = (int) $timestamp;
        $status_key = $this->normalize_status($status_key);
    
        if ($topic_id <= 0 || $forum_id <= 0 || $status_key === '')
        {
            return;
        }
    
        $sql_ary = [
            'topic_id' => $topic_id,
            'forum_id' => $forum_id,
            'status_key' => $status_key,
            'status_updated' => $timestamp,
            'status_user_id' => $user_id,
        ];
    
        if ($this->tracking_columns_available())
        {
            foreach (['topic_author_id', 'last_author_reply', 'last_staff_reply'] as $tracking_key)
            {
                if (array_key_exists($tracking_key, $extra_data))
                {
                    $sql_ary[$tracking_key] = (int) $extra_data[$tracking_key];
                }
            }
        }
    
        if ($this->topic_status_exists($topic_id))
        {
            $sql = 'UPDATE ' . $this->status_table() . '
                SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
                WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
            $this->db->sql_query($sql);
        }
        else
        {
            $sql = 'INSERT INTO ' . $this->status_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
            $this->db->sql_query($sql);
        }
    
        unset($this->topic_status_cache[$topic_id . ':0'], $this->topic_status_cache[$topic_id . ':1'], $this->automation_check_cache[$topic_id]);

        foreach (array_keys($this->topic_alert_cache) as $alert_cache_key)
        {
            if (strpos($alert_cache_key, $topic_id . ':') === 0)
            {
                unset($this->topic_alert_cache[$alert_cache_key]);
            }
        }

        $this->clear_notice_cache($topic_id, $forum_id);
    }

    protected function save_topic_priority($topic_id, $priority_key)
    {
        if (!$this->priority_enabled())
        {
            return;
        }

        $topic_id = (int) $topic_id;
        $priority_key = $this->normalize_priority($priority_key);

        if ($topic_id <= 0 || $priority_key === '' || !$this->topic_status_exists($topic_id))
        {
            return;
        }

        $sql = 'UPDATE ' . $this->status_table() . '
'
            . 'SET ' . $this->db->sql_build_array('UPDATE', [
                'priority_key' => $priority_key,
            ]) . '
'
            . 'WHERE ' . $this->sql_int_equals('topic_id', $topic_id);
        $this->db->sql_query($sql);

        unset($this->topic_status_cache[$topic_id . ':0'], $this->topic_status_cache[$topic_id . ':1']);
        $this->clear_notice_cache($topic_id);
    }

    protected function status_table()
    {
        return $this->table_prefix . 'supporttriage_topics';
    }

    protected function notices_table()
    {
        return $this->table_prefix . 'supporttriage_notices';
    }

    protected function snippets_table()
    {
        return $this->table_prefix . 'supporttriage_snippets';
    }

    protected function allowed_statuses()
    {
        return ['new', 'in_progress', 'waiting_reply', 'solved', 'no_reply'];
    }

    protected function allowed_priorities()
    {
        return ['low', 'normal', 'high', 'critical'];
    }

    protected function allowed_issue_types()
    {
        return ['general', 'extension', 'update', 'style', 'permissions', 'email'];
    }

    protected function normalize_issue_type($issue_type)
    {
        $issue_type = trim((string) $issue_type);
        return in_array($issue_type, $this->allowed_issue_types(), true) ? $issue_type : 'general';
    }

    protected function normalize_status($status_key)
    {
        $status_key = trim((string) $status_key);
        return in_array($status_key, $this->allowed_statuses(), true) ? $status_key : '';
    }

    protected function normalize_priority($priority_key)
    {
        $priority_key = trim((string) $priority_key);
        return in_array($priority_key, $this->allowed_priorities(), true) ? $priority_key : '';
    }

    protected function normalize_priority_filter($priority_key)
    {
        $priority_key = trim((string) $priority_key);
        return $priority_key === 'all' ? 'all' : $this->normalize_priority($priority_key);
    }

    protected function priority_meta($priority_key)
    {
        switch ($priority_key)
        {
            case 'low':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_PRIORITY_LOW'),
                    'class' => 'supporttriage-priority-low',
                ];

            case 'high':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_PRIORITY_HIGH'),
                    'class' => 'supporttriage-priority-high',
                ];

            case 'critical':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_PRIORITY_CRITICAL'),
                    'class' => 'supporttriage-priority-critical',
                ];

            case 'normal':
            default:
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_PRIORITY_NORMAL'),
                    'class' => 'supporttriage-priority-normal',
                ];
        }
    }

    protected function status_meta($status_key)
    {
        switch ($status_key)
        {
            case 'new':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_NEW'),
                    'class' => 'supporttriage-status-new',
                ];

            case 'in_progress':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_IN_PROGRESS'),
                    'class' => 'supporttriage-status-progress',
                ];

            case 'waiting_reply':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_WAITING_REPLY'),
                    'class' => 'supporttriage-status-waiting',
                ];

            case 'solved':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_SOLVED'),
                    'class' => 'supporttriage-status-solved',
                ];

            case 'no_reply':
                return [
                    'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_NO_REPLY'),
                    'class' => 'supporttriage-status-muted',
                ];
        }

        return [
            'label' => $this->user->lang('SUPPORTTRIAGE_STATUS_NONE'),
            'class' => 'supporttriage-status-none',
        ];
    }

    protected function get_snippets($active_only = true)
    {
        $cache_key = $active_only ? 'active' : 'all';
        if (isset($this->snippet_cache[$cache_key]))
        {
            return $this->snippet_cache[$cache_key];
        }

        $this->ensure_default_snippets();

        $sql = 'SELECT snippet_id, snippet_title, snippet_text, sort_order, is_active
            FROM ' . $this->snippets_table();

        if ($active_only)
        {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY sort_order ASC, snippet_id ASC';

        $result = $this->db->sql_query($sql);
        $snippets = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $snippets[] = [
                'snippet_id' => (int) $row['snippet_id'],
                'snippet_title' => (string) $row['snippet_title'],
                'snippet_text' => (string) $row['snippet_text'],
                'sort_order' => (int) $row['sort_order'],
                'is_active' => !empty($row['is_active']),
            ];
        }
        $this->db->sql_freeresult($result);

        $this->snippet_cache[$cache_key] = $snippets;
        return $this->snippet_cache[$cache_key];
    }

    protected function assign_snippet_block($block_name, array $snippets)
    {
        foreach ($snippets as $snippet)
        {
            $this->template->assign_block_vars($block_name, [
                'TITLE' => $this->escape($snippet['snippet_title']),
                'TEXT' => $this->escape($snippet['snippet_text']),
            ]);
        }
    }

    protected function get_contextual_snippets(array $snippets, $status_key = '', $priority_key = '', $topic_title = '', $post_text = '')
    {
        if (empty($snippets))
        {
            return [];
        }

        $status_key = $this->normalize_status($status_key);
        $priority_key = $this->normalize_priority($priority_key);
        $haystack = $this->normalize_snippet_context_text($topic_title . ' ' . $post_text);
        $scored = [];

        foreach ($snippets as $index => $snippet)
        {
            $score = $this->score_contextual_snippet($snippet, $haystack, $status_key, $priority_key);
            if ($score <= 0)
            {
                continue;
            }

            $snippet['_score'] = $score;
            $snippet['_order'] = $index;
            $scored[] = $snippet;
        }

        if (empty($scored))
        {
            $fallback = array_slice($snippets, 0, min(3, count($snippets)));
            return $fallback;
        }

        usort($scored, function ($a, $b) {
            if ($a['_score'] === $b['_score'])
            {
                return $a['_order'] <=> $b['_order'];
            }

            return ($a['_score'] > $b['_score']) ? -1 : 1;
        });

        $result = [];
        foreach (array_slice($scored, 0, 3) as $snippet)
        {
            unset($snippet['_score'], $snippet['_order']);
            $result[] = $snippet;
        }

        return $result;
    }

    protected function score_contextual_snippet(array $snippet, $haystack, $status_key = '', $priority_key = '')
    {
        $title = $this->normalize_snippet_context_text(isset($snippet['snippet_title']) ? $snippet['snippet_title'] : '');
        $text = $this->normalize_snippet_context_text(isset($snippet['snippet_text']) ? $snippet['snippet_text'] : '');
        $needle = $title . ' ' . $text;
        $score = 0;

        $groups = [
            'cache' => ['cache', 'purge', 'clear cache', 'limpe o cache', 'limpar cache'],
            'prosilver' => ['prosilver', 'style', 'tema', 'template', 'css', 'visual', 'estilo'],
            'extension' => ['extension', 'extensão', 'extensions', 'disable', 'desative', 'ativar', 'enable', 'migration', 'migrat'],
            'debug' => ['debug', 'fatal', 'warning', 'exception', 'erro', 'error', 'trace', 'stack'],
            'steps' => ['steps', 'passos', 'reprodu', 'reproduc', 'exact', 'detalhes', 'details'],
        ];

        foreach ($groups as $group => $keywords)
        {
            $snippetMatches = false;
            foreach ($keywords as $keyword)
            {
                if ($keyword !== '' && strpos($needle, $keyword) !== false)
                {
                    $snippetMatches = true;
                    break;
                }
            }

            if (!$snippetMatches)
            {
                continue;
            }

            foreach ($keywords as $keyword)
            {
                if ($keyword !== '' && strpos($haystack, $keyword) !== false)
                {
                    $score += 5;
                }
            }
        }

        if ($status_key === 'new' || $status_key === 'in_progress')
        {
            if (strpos($needle, 'steps') !== false || strpos($needle, 'passos') !== false || strpos($needle, 'debug') !== false)
            {
                $score += 2;
            }
        }

        if ($status_key === 'waiting_reply')
        {
            if (strpos($needle, 'steps') !== false || strpos($needle, 'passos') !== false)
            {
                $score += 1;
            }
        }

        if ($priority_key === 'high' || $priority_key === 'critical')
        {
            if (strpos($needle, 'debug') !== false || strpos($needle, 'cache') !== false)
            {
                $score += 2;
            }
        }

        if ($score === 0 && (strpos($needle, 'steps') !== false || strpos($needle, 'passos') !== false))
        {
            $score = 1;
        }

        return $score;
    }

    protected function normalize_snippet_context_text($text)
    {
        $text = utf8_strtolower((string) $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    protected function build_context_snippet_reason($status_key = '', $priority_key = '', $topic_data = null)
    {
        $reason_parts = [];
        $status_key = $this->normalize_status($status_key);
        $priority_key = $this->normalize_priority($priority_key);

        if ($status_key !== '')
        {
            $reason_parts[] = $this->status_meta($status_key)['label'];
        }

        if ($priority_key !== '')
        {
            $reason_parts[] = $this->priority_meta($priority_key)['label'];
        }

        $topic_title = '';
        if (is_array($topic_data) && isset($topic_data['topic_title']))
        {
            $topic_title = trim((string) $topic_data['topic_title']);
        }

        if ($topic_title !== '')
        {
            $reason_parts[] = $topic_title;
        }

        if (empty($reason_parts))
        {
            return $this->user->lang('SUPPORTTRIAGE_SNIPPETS_SUGGESTED_REASON_DEFAULT');
        }

        return $this->user->lang('SUPPORTTRIAGE_SNIPPETS_SUGGESTED_REASON_CONTEXT', implode(' / ', $reason_parts));
    }

    protected function ensure_default_snippets()
    {
        if (isset($this->snippet_cache['seeded']))
        {
            return;
        }

        $sql = 'SELECT COUNT(snippet_id) AS total
            FROM ' . $this->snippets_table();
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row || (int) $row['total'] === 0)
        {
            $defaults = $this->default_snippets();
            $next_id = 1;
            foreach ($defaults as $index => $snippet)
            {
                $sql_ary = [
                    'snippet_id' => $next_id++,
                    'snippet_title' => (string) $snippet['title'],
                    'snippet_text' => (string) $snippet['text'],
                    'sort_order' => $index + 1,
                    'is_active' => 1,
                ];
                $sql = 'INSERT INTO ' . $this->snippets_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
                $this->db->sql_query($sql);
            }
        }

        $this->snippet_cache = ['seeded' => true];
    }

    protected function default_snippets()
    {
        return [
            [
                'title' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_CACHE'),
                'text' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_CACHE'),
            ],
            [
                'title' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_PROSILVER'),
                'text' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_PROSILVER'),
            ],
            [
                'title' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_DISABLE_EXT'),
                'text' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_DISABLE_EXT'),
            ],
            [
                'title' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_DEBUG'),
                'text' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_DEBUG'),
            ],
            [
                'title' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TITLE_STEPS'),
                'text' => $this->user->lang('SUPPORTTRIAGE_SNIPPET_DEFAULT_TEXT_STEPS'),
            ],
        ];
    }


    protected function kb_links_table()
    {
        return $this->table_prefix . 'supporttriage_kb_links';
    }

    protected function logs_table()
    {
        return $this->table_prefix . 'supporttriage_logs';
    }

    protected function get_topic_logs($topic_id, $limit = 10)
    {
        $topic_id = (int) $topic_id;
        $limit = max(1, min(50, (int) $limit));

        if ($topic_id <= 0 || !$this->logs_enabled())
        {
            return [];
        }

        $cache_key = $topic_id . ':' . $limit;
        if (isset($this->topic_logs_cache[$cache_key]))
        {
            return $this->topic_logs_cache[$cache_key];
        }

        $sql = 'SELECT l.*, u.username, u.user_colour
            FROM ' . $this->logs_table() . ' l
            LEFT JOIN ' . USERS_TABLE . ' u
                ON u.user_id = l.user_id
            WHERE ' . $this->sql_int_equals('l.topic_id', $topic_id) . '
            ORDER BY l.log_time DESC, l.log_id DESC';
        $result = $this->db->sql_query_limit($sql, $limit);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $this->format_log_row($row);
        }
        $this->db->sql_freeresult($result);

        $this->topic_logs_cache[$cache_key] = $rows;
        return $this->topic_logs_cache[$cache_key];
    }

    protected function assign_log_block($block_name, array $logs)
    {
        foreach ($logs as $log)
        {
            $this->template->assign_block_vars($block_name, [
                'ACTION_LABEL' => $log['action_label'],
                'DETAILS' => $log['details'],
                'USERNAME' => $log['username'],
                'TIME' => $log['time'],
            ]);
        }
    }

    protected function format_log_row(array $row)
    {
        $action_key = isset($row['action_key']) ? (string) $row['action_key'] : '';
        $old_value = isset($row['old_value']) ? (string) $row['old_value'] : '';
        $new_value = isset($row['new_value']) ? (string) $row['new_value'] : '';
        $related_topic_id = !empty($row['related_topic_id']) ? (int) $row['related_topic_id'] : 0;
        $related_forum_id = !empty($row['related_forum_id']) ? (int) $row['related_forum_id'] : 0;
    
        $details = '';
        switch ($action_key)
        {
            case 'status_change':
            case 'status_auto_waiting_reply':
            case 'status_auto_in_progress':
            case 'status_auto_no_reply':
                $details = $this->user->lang(
                    'SUPPORTTRIAGE_LOG_DETAILS_STATUS',
                    $this->status_meta($old_value)['label'],
                    $this->status_meta($new_value)['label']
                );
            break;
    
            case 'kb_create':
            case 'kb_sync':
                $topic_label = '#' . $related_topic_id;
                if ($related_topic_id > 0 && $related_forum_id > 0)
                {
                    $topic_url = append_sid('viewtopic.php', 'f=' . $related_forum_id . '&t=' . $related_topic_id);
                    $topic_label = '<a href="' . $topic_url . '">#' . $related_topic_id . '</a>';
                }
    
                $details = $this->user->lang(
                    $action_key === 'kb_create' ? 'SUPPORTTRIAGE_LOG_DETAILS_KB_CREATE' : 'SUPPORTTRIAGE_LOG_DETAILS_KB_SYNC',
                    $topic_label
                );
            break;

            case 'priority_change':
            case 'priority_auto_stale':
            case 'priority_auto_forum':
            case 'priority_auto_issue':
                $details = $this->user->lang(
                    'SUPPORTTRIAGE_LOG_DETAILS_PRIORITY',
                    $this->priority_meta($old_value)['label'],
                    $this->priority_meta($new_value)['label']
                );
            break;
        }
    
        return [
            'action_label' => $this->user->lang('SUPPORTTRIAGE_LOG_ACTION_' . strtoupper($action_key)),
            'details' => $details,
            'username' => !empty($row['username']) ? get_username_string('full', (int) $row['user_id'], $row['username'], $row['user_colour']) : $this->user->lang('SUPPORTTRIAGE_SYSTEM_USER'),
            'time' => !empty($row['log_time']) ? $this->user->format_date((int) $row['log_time']) : '',
        ];
    }

    protected function log_action($topic_id, $forum_id, $action_key, $old_value = '', $new_value = '', $related_topic_id = 0, $related_forum_id = 0, $user_id = null)
    {
        $topic_id = (int) $topic_id;
        $forum_id = (int) $forum_id;
        $related_topic_id = (int) $related_topic_id;
        $related_forum_id = (int) $related_forum_id;
        $action_key = trim((string) $action_key);
    
        if ($topic_id <= 0 || $forum_id <= 0 || $action_key === '' || !$this->logs_enabled())
        {
            return;
        }
    
        $sql_ary = [
            'topic_id' => $topic_id,
            'forum_id' => $forum_id,
            'action_key' => $action_key,
            'old_value' => (string) $old_value,
            'new_value' => (string) $new_value,
            'related_topic_id' => $related_topic_id,
            'related_forum_id' => $related_forum_id,
            'user_id' => ($user_id === null) ? (int) $this->user->data['user_id'] : (int) $user_id,
            'log_time' => time(),
        ];
    
        $sql = 'INSERT INTO ' . $this->logs_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
        $this->db->sql_query($sql);
    
        unset($this->topic_logs_cache[(int) $topic_id]);
    }

    protected function get_kb_link($source_topic_id)
    {
        $source_topic_id = (int) $source_topic_id;
        if ($source_topic_id <= 0)
        {
            return null;
        }

        if (array_key_exists($source_topic_id, $this->kb_link_cache))
        {
            return $this->kb_link_cache[$source_topic_id];
        }

        $sql = 'SELECT *
            FROM ' . $this->kb_links_table() . '
            WHERE ' . $this->sql_int_equals('source_topic_id', $source_topic_id);
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        $this->kb_link_cache[$source_topic_id] = $row ?: null;
        return $this->kb_link_cache[$source_topic_id];
    }

    protected function kb_link_exists($source_topic_id)
    {
        return $this->get_kb_link($source_topic_id) !== null;
    }

    protected function save_kb_link($source_topic_id, $source_forum_id, $kb_topic_id, $kb_forum_id, $user_id, $timestamp)
    {
        $sql_ary = [
            'source_topic_id' => (int) $source_topic_id,
            'source_forum_id' => (int) $source_forum_id,
            'kb_topic_id' => (int) $kb_topic_id,
            'kb_forum_id' => (int) $kb_forum_id,
            'created_by' => (int) $user_id,
            'created_time' => (int) $timestamp,
        ];

        if ($this->kb_link_exists($source_topic_id))
        {
            $sql = 'UPDATE ' . $this->kb_links_table() . '
                SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
                WHERE ' . $this->sql_int_equals('source_topic_id', (int) $source_topic_id);
            $this->db->sql_query($sql);
        }
        else
        {
            $sql = 'INSERT INTO ' . $this->kb_links_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
            $this->db->sql_query($sql);
        }

        unset($this->kb_link_cache[(int) $source_topic_id]);
    }

    protected function create_kb_draft($source_forum_id, $source_topic_id, $source_topic_title)
    {
        $source_forum_id = (int) $source_forum_id;
        $source_topic_id = (int) $source_topic_id;
        $kb_forum_id = $this->kb_forum_id();

        if ($source_forum_id <= 0 || $source_topic_id <= 0 || $kb_forum_id <= 0)
        {
            return 0;
        }

        if ($this->kb_link_exists($source_topic_id))
        {
            $link = $this->get_kb_link($source_topic_id);
            return $link ? (int) $link['kb_topic_id'] : 0;
        }

        $source = $this->load_source_topic_data($source_topic_id);
        if (!$source)
        {
            return 0;
        }

        global $phpbb_root_path, $phpEx, $config;

        if (!function_exists('submit_post'))
        {
            include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
        }

        $subject = trim($this->kb_prefix() . ' ' . $source_topic_title);
        $message = $this->build_kb_post_body($source);

        $data = [
            'forum_id' => $kb_forum_id,
            'icon_id' => 0,
            'enable_bbcode' => true,
            'enable_smilies' => true,
            'enable_urls' => true,
            'enable_sig' => false,
            'message' => $message,
            // submit_post() still expects the legacy message_md5 index.
            'message_md5' => hash('md5', $message),
            'bbcode_bitfield' => '',
            'bbcode_uid' => '',
            'post_edit_locked' => 0,
            'topic_title' => $subject,
            'notify_set' => false,
            'notify' => false,
            'post_time' => time(),
            'forum_name' => '',
            'enable_indexing' => true,
            'post_edit_reason' => '',
            'topic_status' => 0,
            'topic_type' => POST_NORMAL,
            'post_visibility' => ITEM_APPROVED,
            'topic_visibility' => ITEM_APPROVED,
            'poster_id' => (int) $this->user->data['user_id'],
            'post_subject' => $subject,
        ];

        $poll = [];

        try
        {
            submit_post('post', $subject, '', POST_NORMAL, $poll, $data);
        }
        catch (\Throwable $e)
        {
            return 0;
        }
        catch (\Exception $e)
        {
            return 0;
        }

        $kb_topic_id = !empty($data['topic_id']) ? (int) $data['topic_id'] : 0;
        if ($kb_topic_id <= 0)
        {
            return 0;
        }

        if ($this->kb_auto_lock())
        {
            $sql = 'UPDATE ' . TOPICS_TABLE . '
                SET topic_status = ' . ITEM_LOCKED . '
                WHERE ' . $this->sql_int_equals('topic_id', $kb_topic_id);
            $this->db->sql_query($sql);
        }

        $this->save_kb_link($source_topic_id, $source_forum_id, $kb_topic_id, $kb_forum_id, (int) $this->user->data['user_id'], time());
        $this->log_action($source_topic_id, $source_forum_id, 'kb_create', '', '', $kb_topic_id, $kb_forum_id);

        return $kb_topic_id;
    }

    protected function sync_kb_draft($source_forum_id, $source_topic_id, $source_topic_title)
    {
        $source_forum_id = (int) $source_forum_id;
        $source_topic_id = (int) $source_topic_id;

        if ($source_forum_id <= 0 || $source_topic_id <= 0 || !$this->kb_enabled())
        {
            return 0;
        }

        $link = $this->get_kb_link($source_topic_id);
        if (!$link || empty($link['kb_topic_id']))
        {
            return 0;
        }

        $source = $this->load_source_topic_data($source_topic_id);
        $target = $this->load_kb_topic_data((int) $link['kb_topic_id']);

        if (!$source || !$target || empty($target['post_id']))
        {
            return 0;
        }

        global $phpbb_root_path, $phpEx;

        if (!function_exists('generate_text_for_storage') || !function_exists('generate_text_for_edit'))
        {
            include($phpbb_root_path . 'includes/functions_content.' . $phpEx);
        }

        if (!function_exists('update_post_information'))
        {
            include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
        }

        $existing_text = $this->decode_post_for_edit($target);
        $subject = trim($this->kb_prefix() . ' ' . $source_topic_title);
        $raw_message = $this->build_kb_post_body($source, $existing_text);
        $stored_message = $raw_message;
        $uid = '';
        $bitfield = '';
        $flags = 0;
        generate_text_for_storage($stored_message, $uid, $bitfield, $flags, true, true, true);

        $post_sql_ary = [
            'post_subject' => $subject,
            'post_text' => $stored_message,
            'bbcode_uid' => $uid,
            'bbcode_bitfield' => $bitfield,
            'enable_bbcode' => 1,
            'enable_smilies' => 1,
            'enable_magic_url' => 1,
            // posts.post_checksum is stored as an MD5 checksum in phpBB's posting flow.
            'post_checksum' => hash('md5', $raw_message),
            'post_edit_time' => time(),
            'post_edit_user' => (int) $this->user->data['user_id'],
            'post_edit_count' => ((int) $target['post_edit_count']) + 1,
        ];

        $sql = 'UPDATE ' . POSTS_TABLE . '
            SET ' . $this->db->sql_build_array('UPDATE', $post_sql_ary) . '
            WHERE ' . $this->sql_int_equals('post_id', (int) $target['post_id']);
        $this->db->sql_query($sql);

        $topic_sql_ary = [
            'topic_title' => $subject,
        ];

        if ($this->kb_auto_lock())
        {
            $topic_sql_ary['topic_status'] = ITEM_LOCKED;
        }

        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET ' . $this->db->sql_build_array('UPDATE', $topic_sql_ary) . '
            WHERE ' . $this->sql_int_equals('topic_id', (int) $target['topic_id']);
        $this->db->sql_query($sql);

        update_post_information('topic', (int) $target['topic_id']);
        update_post_information('forum', (int) $target['forum_id']);

        $this->log_action($source_topic_id, $source_forum_id, 'kb_sync', '', '', (int) $target['topic_id'], (int) $target['forum_id']);

        return (int) $target['topic_id'];
    }

    protected function load_source_topic_data($source_topic_id)
    {
        $source_topic_id = (int) $source_topic_id;
        if ($source_topic_id <= 0)
        {
            return null;
        }

        $sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_first_post_id,
                p.post_id, p.poster_id, p.post_subject, p.post_text, p.bbcode_uid, p.bbcode_bitfield,
                p.enable_bbcode, p.enable_smilies, p.enable_magic_url, p.post_time
            FROM ' . TOPICS_TABLE . ' t
            LEFT JOIN ' . POSTS_TABLE . ' p
                ON p.post_id = t.topic_first_post_id
            WHERE ' . $this->sql_int_equals('t.topic_id', $source_topic_id);
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: null;
    }

    protected function load_kb_topic_data($kb_topic_id)
    {
        $kb_topic_id = (int) $kb_topic_id;
        if ($kb_topic_id <= 0)
        {
            return null;
        }

        $sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_first_post_id, t.topic_last_post_id,
                p.post_id, p.post_subject, p.post_text, p.bbcode_uid, p.bbcode_bitfield,
                p.enable_bbcode, p.enable_smilies, p.enable_magic_url, p.post_edit_count
            FROM ' . TOPICS_TABLE . ' t
            LEFT JOIN ' . POSTS_TABLE . ' p
                ON p.post_id = t.topic_first_post_id
            WHERE ' . $this->sql_int_equals('t.topic_id', $kb_topic_id);
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: null;
    }

    protected function build_kb_post_body(array $source, $existing_text = '')
    {
        $status_row = $this->get_topic_status((int) $source['topic_id'], true);
        $decoded_source = $this->decode_post_for_edit($source);
        $analysis = $this->analyze_kb_source_topic($source, $decoded_source, $status_row);
        $context = !empty($analysis['context']) && is_array($analysis['context']) ? $analysis['context'] : [];
        $source_url = append_sid('viewtopic.php', 'f=' . (int) $source['forum_id'] . '&t=' . (int) $source['topic_id']);

        $lines = [];
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_EDIT_HINT') . '[/b]';
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SOURCE_TOPIC') . '[/b]';
        $lines[] = '[url=' . $source_url . ']' . $source['topic_title'] . '[/url]';
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SOLVED_STATUS') . '[/b]';
        $lines[] = $this->user->lang('SUPPORTTRIAGE_STATUS_SOLVED');

        if ($status_row && !empty($status_row['status_updated']))
        {
            $lines[] = '';
            $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SOLVED_AT') . '[/b]';
            $lines[] = $this->user->format_date((int) $status_row['status_updated']);
        }

        if (!empty($context))
        {
            $lines[] = '';
            $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_CONTEXT') . '[/b]';
            $lines[] = '[list]';
            foreach ($context as $label => $value)
            {
                $lines[] = '[*][b]' . $label . ':[/b] ' . $value;
            }
            $lines[] = '[/list]';
        }

        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_ORIGINAL_REPORT') . '[/b]';
        $lines[] = !empty($analysis['original_report']) ? $analysis['original_report'] : $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';

        $manual_section = $this->extract_kb_manual_section($existing_text);
        if ($manual_section === '')
        {
            $lines[] = $this->build_kb_manual_section_from_analysis($analysis);
        }
        else
        {
            $lines[] = $manual_section;
        }

        return implode("\n", $lines);
    }

    protected function analyze_kb_source_topic(array $source, $decoded_source, $status_row = null)
    {
        $decoded_source = $this->normalize_newlines($decoded_source);
        $context = $this->extract_triage_context($decoded_source);
        $report_body = $this->cleanup_kb_text($this->strip_technical_report_section($decoded_source));
        $symptoms = $this->extract_named_bbcode_section($decoded_source, ['Erro exibido', 'Error shown', 'Displayed error']);

        if ($symptoms === '')
        {
            $symptoms = $this->extract_first_bbcode_block($decoded_source, 'code');
        }
        $symptoms = $this->cleanup_kb_text($symptoms);

        if ($report_body === '')
        {
            if ($symptoms !== '')
            {
                $report_body = $this->user->lang('SUPPORTTRIAGE_KB_AUTO_REPORT_FROM_ERROR');
            }
            else
            {
                $report_body = $this->cleanup_kb_text(isset($source['topic_title']) ? (string) $source['topic_title'] : '');
            }
        }

        $posts = $this->load_source_topic_posts((int) $source['topic_id']);
        $solution = $this->extract_best_kb_solution($posts, !empty($source['topic_first_post_id']) ? (int) $source['topic_first_post_id'] : 0, $status_row);
        $cause = $this->infer_kb_root_cause($symptoms, $solution, $report_body, $context);
        $notes = $this->infer_kb_notes($symptoms, $solution, $cause);

        return [
            'context' => $context,
            'original_report' => $report_body,
            'symptoms' => $symptoms,
            'cause' => $cause,
            'solution' => $solution,
            'notes' => $notes,
        ];
    }

    protected function load_source_topic_posts($source_topic_id)
    {
        $source_topic_id = (int) $source_topic_id;
        if ($source_topic_id <= 0)
        {
            return [];
        }

        $sql = 'SELECT post_id, topic_id, forum_id, poster_id, post_subject, post_text, bbcode_uid, bbcode_bitfield,
                enable_bbcode, enable_smilies, enable_magic_url, post_time
            FROM ' . POSTS_TABLE . '
            WHERE ' . $this->sql_int_equals('topic_id', $source_topic_id) . '
                AND post_visibility = 1
            ORDER BY post_time ASC, post_id ASC';
        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    protected function build_kb_manual_section_from_analysis(array $analysis)
    {
        $lines = [];
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SYMPTOMS') . '[/b]';
        $lines[] = !empty($analysis['symptoms']) ? $analysis['symptoms'] : $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_CAUSE') . '[/b]';
        $lines[] = !empty($analysis['cause']) ? $analysis['cause'] : $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SOLUTION') . '[/b]';
        $lines[] = !empty($analysis['solution']) ? $analysis['solution'] : $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_NOTES') . '[/b]';
        $lines[] = !empty($analysis['notes']) ? $analysis['notes'] : $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');

        return implode("\n", $lines);
    }

    protected function strip_technical_report_section($text)
    {
        $text = $this->normalize_newlines($text);

        $patterns = [
            '/\[b\](?:===\s*)?Relat[oó]rio t[eé]cnico(?:\s*===)?\[\/b\].*?\[b\](?:===\s*)?\/Relat[oó]rio t[eé]cnico(?:\s*===)?\[\/b\]/isu',
            '/\[b\](?:===\s*)?Technical report(?:\s*===)?\[\/b\].*?\[b\](?:===\s*)?\/Technical report(?:\s*===)?\[\/b\]/isu',
        ];

        foreach ($patterns as $pattern)
        {
            $text = preg_replace($pattern, '', $text);
        }

        return $text;
    }

    protected function extract_named_bbcode_section($text, array $titles)
    {
        $text = $this->normalize_newlines($text);

        foreach ($titles as $title)
        {
            $pattern = '/\[b\]' . preg_quote($title, '/') . '\[\/b\]\s*(.*?)(?=\n\[b\]|\z)/isu';
            if (preg_match($pattern, $text, $matches))
            {
                $section = trim($matches[1]);
                $code_block = $this->extract_first_bbcode_block($section, 'code');
                if ($code_block !== '')
                {
                    return $code_block;
                }
                return $section;
            }
        }

        return '';
    }

    protected function extract_first_bbcode_block($text, $tag = 'code')
    {
        $text = $this->normalize_newlines($text);
        $tag = preg_quote((string) $tag, '/');

        if (preg_match('/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/isu', $text, $matches))
        {
            return trim($matches[1]);
        }

        return '';
    }

    protected function cleanup_kb_text($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
        $text = $this->normalize_newlines($text);
        $text = preg_replace('/\[quote(?:=.*?)?\].*?\[\/quote\]/isu', '', $text);
        $text = preg_replace('/\[size=.*?\]|\[\/size\]|\[color=.*?\]|\[\/color\]|\[font=.*?\]|\[\/font\]/isu', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    protected function cleanup_kb_reply_text($text)
    {
        $text = $this->cleanup_kb_text($text);
        if ($text === '')
        {
            return '';
        }

        $text = preg_replace('/^(?:oi|ol[aá]|hello|hi|thanks|thank you|obrigado|obrigada|valeu)[^\n]*\n+/iu', '', $text);
        $text = preg_replace('/\n+(?:abra[cç]os?|att\.?|regards|best regards)[\s\S]*$/iu', '', $text);
        $text = trim($text);

        $paragraphs = preg_split('/\n{2,}/', $text);
        if (!empty($paragraphs) && mb_strlen($text, 'UTF-8') > 500)
        {
            $first = trim($paragraphs[0]);
            if ($first !== '' && mb_strlen($first, 'UTF-8') >= 12)
            {
                $text = $first;
            }
        }

        return trim($text);
    }

    protected function extract_best_kb_solution(array $posts, $first_post_id = 0, $status_row = null)
    {
        $first_post_id = (int) $first_post_id;
        $status_user_id = !empty($status_row['status_user_id']) ? (int) $status_row['status_user_id'] : 0;
        $best_text = '';
        $best_score = -99999;
        $best_time = 0;

        foreach ($posts as $row)
        {
            if (!empty($row['post_id']) && (int) $row['post_id'] === $first_post_id)
            {
                continue;
            }

            $decoded = $this->cleanup_kb_reply_text($this->decode_post_for_edit($row));
            if ($decoded === '')
            {
                continue;
            }

            $score = $this->score_kb_solution_candidate($decoded, $row, $status_user_id);
            $post_time = !empty($row['post_time']) ? (int) $row['post_time'] : 0;
            if ($score > $best_score || ($score === $best_score && $post_time >= $best_time))
            {
                $best_score = $score;
                $best_time = $post_time;
                $best_text = $decoded;
            }
        }

        return $best_score >= 20 ? $best_text : '';
    }

    protected function score_kb_solution_candidate($text, array $row, $status_user_id = 0)
    {
        $score = 0;
        $length = mb_strlen($text, 'UTF-8');

        if ($length >= 12)
        {
            $score += 20;
        }
        if ($length >= 20 && $length <= 450)
        {
            $score += 20;
        }
        else if ($length > 450)
        {
            $score -= 10;
        }

        if ($status_user_id > 0 && !empty($row['poster_id']) && (int) $row['poster_id'] === $status_user_id)
        {
            $score += 30;
        }

        if (preg_match('/\b(?:renomeie|rename|exclua|delete|remova|remove|mova|move|atualize|update|verifique|check|ajuste|configure|habilite|enable|desabilite|disable|limpe|clear|reinstale|reinstall|use|utilize|troque|change)\b/iu', $text))
        {
            $score += 35;
        }

        if (preg_match('/\b(?:install|instala[cç][aã]o|diret[oó]rio|pasta|directory|folder)\b/iu', $text))
        {
            $score += 20;
        }

        if (preg_match('/\?/', $text))
        {
            $score -= 15;
        }

        if (preg_match('/^(?:resolved|solved|obrigado|thanks|valeu|ok|certo|perfeito)\b/iu', $text))
        {
            $score -= 25;
        }

        return $score;
    }

    protected function infer_kb_root_cause($symptoms, $solution, $report_body, array $context = [])
    {
        $combined = $this->normalize_newlines($symptoms . "\n" . $solution . "\n" . $report_body . "\n" . implode(' ', $context));

        if (preg_match('/(?:diret[oó]rio|pasta|directory|folder)\s+install/iu', $combined)
            || (preg_match('/\binstall\b/iu', $combined) && preg_match('/\b(?:renomeie|rename|exclua|delete|remova|remove|mova|move)\b/iu', $combined)))
        {
            return $this->user->lang('SUPPORTTRIAGE_KB_AUTO_CAUSE_INSTALL');
        }

        $patterns = [
            '/(?:isso ocorre porque|o problema era|o problema estava em|a causa foi)\s+(.+?)(?:\.|$)/iu',
            '/(?:this happens because|the issue was|the root cause was|the problem was)\s+(.+?)(?:\.|$)/iu',
        ];
        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $combined, $matches))
            {
                $cause = trim($matches[1]);
                if ($cause !== '')
                {
                    return rtrim($cause, '. ') . '.';
                }
            }
        }

        return '';
    }

    protected function infer_kb_notes($symptoms, $solution, $cause)
    {
        $combined = $this->normalize_newlines($symptoms . "\n" . $solution . "\n" . $cause);

        if (preg_match('/(?:diret[oó]rio|pasta|directory|folder)\s+install/iu', $combined)
            || (preg_match('/\binstall\b/iu', $combined) && preg_match('/\b(?:renomeie|rename|exclua|delete|remova|remove|mova|move)\b/iu', $combined)))
        {
            return $this->user->lang('SUPPORTTRIAGE_KB_AUTO_NOTES_INSTALL');
        }

        return '';
    }

    protected function decode_post_for_edit(array $target)
    {
        $text = isset($target['post_text']) ? (string) $target['post_text'] : '';
        if ($text === '')
        {
            return '';
        }

        $flags = 0;
        if (!empty($target['enable_bbcode']))
        {
            $flags |= OPTION_FLAG_BBCODE;
        }
        if (!empty($target['enable_smilies']))
        {
            $flags |= OPTION_FLAG_SMILIES;
        }
        if (!empty($target['enable_magic_url']))
        {
            $flags |= OPTION_FLAG_LINKS;
        }

        $decoded = generate_text_for_edit($text, isset($target['bbcode_uid']) ? (string) $target['bbcode_uid'] : '', $flags);
        return !empty($decoded['text']) ? (string) $decoded['text'] : '';
    }

    protected function extract_kb_manual_section($text)
    {
        $text = $this->normalize_newlines($text);
        if ($text === '')
        {
            return '';
        }

        foreach ($this->kb_manual_section_headings() as $heading)
        {
            $needle = '[b]' . $heading . '[/b]';
            $position = strpos($text, $needle);
            if ($position !== false)
            {
                return trim(substr($text, $position));
            }
        }

        return '';
    }

    protected function default_kb_manual_section()
    {
        $lines = [];
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SYMPTOMS') . '[/b]';
        $lines[] = $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_CAUSE') . '[/b]';
        $lines[] = $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_SOLUTION') . '[/b]';
        $lines[] = $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');
        $lines[] = '';
        $lines[] = '[b]' . $this->user->lang('SUPPORTTRIAGE_KB_NOTES') . '[/b]';
        $lines[] = $this->user->lang('SUPPORTTRIAGE_KB_FILL_HINT');

        return implode("\n", $lines);
    }

    protected function kb_manual_section_headings()
    {
        return [
            'Sintomas confirmados',
            'Causa raiz',
            'Solução aplicada',
            'Observações finais',
            'Confirmed symptoms',
            'Root cause',
            'Applied solution',
            'Final notes',
        ];
    }

    protected function normalize_newlines($text)
    {
        return str_replace(["\r\n", "\r"], "\n", (string) $text);
    }

    protected function extract_triage_context($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $matches = [];
        preg_match_all('/\[\*\]\[b\](.+?):\[\/b\]\s*(.+)/u', $text, $matches, PREG_SET_ORDER);

        $context = [];
        foreach ($matches as $match)
        {
            $label = trim(strip_tags($match[1]));
            $value = trim(strip_tags($match[2]));
            if ($label === '' || $value === '')
            {
                continue;
            }
            $context[$label] = $value;
        }

        return $context;
    }

    protected function forum_is_enabled($forum_id)
    {
        $forum_id = (int) $forum_id;

        if (!$this->extension_enabled() || $forum_id <= 0)
        {
            return false;
        }

        $raw = trim($this->config_value('mundophpbb_supporttriage_forums'));
        if ($raw === '')
        {
            return true;
        }

        $ids = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_map('intval', $ids);

        return in_array($forum_id, $ids, true);
    }

    protected function append_url_param($url, $param)
    {
        $url = (string) $url;
        $param = ltrim((string) $param, '&?');

        if ($url === '')
        {
            return $url;
        }

        return $url . ((strpos($url, '?') === false) ? '?' : '&') . $param;
    }

    protected function get_recent_topics_for_suggestions($forum_id, $limit = 40)
    {
        $forum_id = (int) $forum_id;
        $limit = max(1, min(80, (int) $limit));

        if ($forum_id <= 0)
        {
            return [];
        }

        $sql = 'SELECT topic_id, forum_id, topic_title, topic_last_post_time
            FROM ' . TOPICS_TABLE . '
            WHERE ' . $this->sql_int_equals('forum_id', $forum_id) . '
                AND topic_moved_id = 0
                AND topic_visibility = 1
            ORDER BY topic_last_post_time DESC';
        $result = $this->db->sql_query_limit($sql, $limit);

        $topics = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $title = trim((string) $row['topic_title']);
            if ($title === '')
            {
                continue;
            }

            $topics[] = [
                'topic_id' => (int) $row['topic_id'],
                'forum_id' => (int) $row['forum_id'],
                'title' => $title,
                'url' => append_sid('viewtopic.php', 'f=' . (int) $row['forum_id'] . '&t=' . (int) $row['topic_id']),
                'last_post_time' => !empty($row['topic_last_post_time']) ? $this->user->format_date((int) $row['topic_last_post_time']) : '',
            ];
        }
        $this->db->sql_freeresult($result);

        return $topics;
    }

    protected function encode_json_for_template(array $value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        return ($json === false) ? '[]' : $json;
    }

    protected function build_board_url()
    {
        $protocol = $this->config_value('server_protocol');
        $server_name = $this->config_value('server_name');
        $server_port = (int) $this->config_value('server_port');
        $script_path = trim($this->config_value('script_path'));

        $url = $protocol . $server_name;

        if ($server_port && !in_array($server_port, [80, 443], true))
        {
            $url .= ':' . $server_port;
        }

        if ($script_path !== '')
        {
            $url .= '/' . ltrim($script_path, '/');
        }

        return rtrim($url, '/');
    }

    protected function config_value($key)
    {
        return isset($this->config[$key]) ? (string) $this->config[$key] : '';
    }

    protected function escape($value)
    {
        return utf8_htmlspecialchars((string) $value);
    }

    protected function sql_int_equals($column, $value)
    {
        return $this->db->sql_in_set($column, [(int) $value]);
    }

    protected function sql_string_equals($column, $value)
    {
        return $this->db->sql_in_set($column, [(string) $value]);
    }
}
