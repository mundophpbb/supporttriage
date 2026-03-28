<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1000_install extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_enable']);
    }

    static public function depends_on()
    {
        return [];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                // Tabela de status dos tópicos
                $this->table_prefix . 'supporttriage_topics' => [
                    'COLUMNS' => [
                        'topic_id'          => ['UINT', 0],
                        'forum_id'          => ['UINT', 0],
                        'status_key'        => ['VCHAR:32', 'new'],
                        'status_updated'    => ['TIMESTAMP', 0],
                        'status_user_id'    => ['UINT', 0],
                        'priority_key'      => ['VCHAR:20', 'normal'],   // adicionado na v2400
                        'topic_author_id'   => ['UINT', 0],              // v1900
                        'last_author_reply' => ['TIMESTAMP', 0],
                        'last_staff_reply'  => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'topic_id',
                    'KEYS' => [
                        'forum_id'    => ['INDEX', 'forum_id'],
                        'status_key'  => ['INDEX', 'status_key'],
                    ],
                ],

                // Tabela de snippets
                $this->table_prefix . 'supporttriage_snippets' => [
                    'COLUMNS' => [
                        'snippet_id'    => ['UINT', 0],
                        'snippet_title' => ['VCHAR:255', ''],
                        'snippet_text'  => ['MTEXT_UNI', ''],
                        'sort_order'    => ['UINT', 0],
                        'is_active'     => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'snippet_id',
                    'KEYS' => [
                        'sort_order' => ['INDEX', 'sort_order'],
                        'is_active'  => ['INDEX', 'is_active'],
                    ],
                ],

                // Tabela de KB links
                $this->table_prefix . 'supporttriage_kb_links' => [
                    'COLUMNS' => [
                        'source_topic_id' => ['UINT', 0],
                        'source_forum_id' => ['UINT', 0],
                        'kb_topic_id'     => ['UINT', 0],
                        'kb_forum_id'     => ['UINT', 0],
                        'created_by'      => ['UINT', 0],
                        'created_time'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'source_topic_id',
                    'KEYS' => [
                        'kb_topic_id' => ['INDEX', 'kb_topic_id'],
                        'kb_forum_id' => ['INDEX', 'kb_forum_id'],
                    ],
                ],

                // Tabela de logs
                $this->table_prefix . 'supporttriage_logs' => [
                    'COLUMNS' => [
                        'log_id'          => ['UINT', null, 'auto_increment'],
                        'topic_id'        => ['UINT', 0],
                        'forum_id'        => ['UINT', 0],
                        'action_key'      => ['VCHAR:32', ''],
                        'old_value'       => ['VCHAR:100', ''],
                        'new_value'       => ['VCHAR:100', ''],
                        'related_topic_id'=> ['UINT', 0],
                        'related_forum_id'=> ['UINT', 0],
                        'user_id'         => ['UINT', 0],
                        'log_time'        => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => [
                        'topic_id'  => ['INDEX', 'topic_id'],
                        'forum_id'  => ['INDEX', 'forum_id'],
                        'log_time'  => ['INDEX', 'log_time'],
                    ],
                ],

                // Tabela de notices
                $this->table_prefix . 'supporttriage_notices' => [
                    'COLUMNS' => [
                        'notice_id'     => ['UINT', null, 'auto_increment'],
                        'topic_id'      => ['UINT', 0],
                        'forum_id'      => ['UINT', 0],
                        'notice_key'    => ['VCHAR:32', ''],
                        'actor_user_id' => ['UINT', 0],
                        'notice_time'   => ['TIMESTAMP', 0],
                        'is_active'     => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'notice_id',
                    'KEYS' => [
                        'topic_id'    => ['INDEX', 'topic_id'],
                        'forum_id'    => ['INDEX', 'forum_id'],
                        'notice_key'  => ['INDEX', 'notice_key'],
                        'is_active'   => ['INDEX', 'is_active'],
                        'notice_time' => ['INDEX', 'notice_time'],
                    ],
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Configurações básicas
            ['config.add', ['mundophpbb_supporttriage_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_forums', '']],
            ['config.add', ['mundophpbb_supporttriage_auto_insert', 1]],
            ['config.add', ['mundophpbb_supporttriage_prefix', '[SUPORTE]']],

            // Status
            ['config.add', ['mundophpbb_supporttriage_status_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_default_status', 'new']],

            // Priority
            ['config.add', ['mundophpbb_supporttriage_priority_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_default_priority', 'normal']],

            // Snippets
            ['config.add', ['mundophpbb_supporttriage_snippets_enable', 1]],

            // KB
            ['config.add', ['mundophpbb_supporttriage_kb_enable', 0]],
            ['config.add', ['mundophpbb_supporttriage_kb_forum', '']],
            ['config.add', ['mundophpbb_supporttriage_kb_prefix', '[KB Draft]']],
            ['config.add', ['mundophpbb_supporttriage_kb_lock', 1]],

            // Logs
            ['config.add', ['mundophpbb_supporttriage_logs_enable', 1]],

            // Automation
            ['config.add', ['mundophpbb_supporttriage_automation_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_waiting_reply', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_in_progress', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_no_reply_days', 7]],

            // Queue
            ['config.add', ['mundophpbb_supporttriage_queue_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_queue_stale_days', 5]],

            // Notifications
            ['config.add', ['mundophpbb_supporttriage_notifications_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_author_return', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_no_reply', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_sla_warning', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_sla_hours', 24]],
            ['config.add', ['mundophpbb_supporttriage_alert_kb_linked', 1]],

            // Priority Automation
            ['config.add', ['mundophpbb_supporttriage_priority_auto_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_stale_days', 3]],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_stale_target', 'high']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_forums', '']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_forums_target', 'critical']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_issue_types', 'permissions,email']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_issue_target', 'high']],

            // Notice Feed
            ['config.add', ['mundophpbb_supporttriage_notice_feed_enable', 1]],

            // ==================== MÓDULOS ACP ====================
            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_SUPPORTTRIAGE_TITLE',
            ]],

            ['module.add', [
                'acp',
                'ACP_SUPPORTTRIAGE_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\supporttriage\\acp\\main_module',
                    'modes' => ['dashboard', 'general', 'automation', 'content', 'diagnostics'],
                ],
            ]],

            // Permissões
            ['permission.add', ['a_supporttriage_manage']],
            ['permission.add', ['m_supporttriage_status', false]],
            ['permission.add', ['m_supporttriage_snippets', false]],
            ['permission.add', ['m_supporttriage_kb_create', false]],
            ['permission.add', ['m_supporttriage_kb_sync', false]],
            ['permission.add', ['m_supporttriage_priority', false]],

            ['permission.permission_set', ['ROLE_ADMIN_FULL', 'a_supporttriage_manage']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_status']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_snippets']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_kb_create']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_kb_sync']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_priority']],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_topics',
                $this->table_prefix . 'supporttriage_snippets',
                $this->table_prefix . 'supporttriage_kb_links',
                $this->table_prefix . 'supporttriage_logs',
                $this->table_prefix . 'supporttriage_notices',
            ],
        ];
    }

    public function revert_data()
    {
        return [
            // Remove módulo completamente
            ['module.remove', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_SUPPORTTRIAGE_TITLE',
            ]],

            // Remove permissões nativamente
            ['permission.remove', ['a_supporttriage_manage']],
            ['permission.remove', ['m_supporttriage_status']],
            ['permission.remove', ['m_supporttriage_snippets']],
            ['permission.remove', ['m_supporttriage_kb_create']],
            ['permission.remove', ['m_supporttriage_kb_sync']],
            ['permission.remove', ['m_supporttriage_priority']],

            // Remove todas as configurações
            ['config.remove', ['mundophpbb_supporttriage_enable']],
            ['config.remove', ['mundophpbb_supporttriage_forums']],
            ['config.remove', ['mundophpbb_supporttriage_auto_insert']],
            ['config.remove', ['mundophpbb_supporttriage_prefix']],
            ['config.remove', ['mundophpbb_supporttriage_status_enable']],
            ['config.remove', ['mundophpbb_supporttriage_default_status']],
            ['config.remove', ['mundophpbb_supporttriage_priority_enable']],
            ['config.remove', ['mundophpbb_supporttriage_default_priority']],
            ['config.remove', ['mundophpbb_supporttriage_snippets_enable']],
            ['config.remove', ['mundophpbb_supporttriage_kb_enable']],
            ['config.remove', ['mundophpbb_supporttriage_kb_forum']],
            ['config.remove', ['mundophpbb_supporttriage_kb_prefix']],
            ['config.remove', ['mundophpbb_supporttriage_kb_lock']],
            ['config.remove', ['mundophpbb_supporttriage_logs_enable']],
            ['config.remove', ['mundophpbb_supporttriage_automation_enable']],
            ['config.remove', ['mundophpbb_supporttriage_auto_waiting_reply']],
            ['config.remove', ['mundophpbb_supporttriage_auto_in_progress']],
            ['config.remove', ['mundophpbb_supporttriage_auto_no_reply_days']],
            ['config.remove', ['mundophpbb_supporttriage_queue_enable']],
            ['config.remove', ['mundophpbb_supporttriage_queue_stale_days']],
            ['config.remove', ['mundophpbb_supporttriage_notifications_enable']],
            ['config.remove', ['mundophpbb_supporttriage_alert_author_return']],
            ['config.remove', ['mundophpbb_supporttriage_alert_no_reply']],
            ['config.remove', ['mundophpbb_supporttriage_alert_sla_warning']],
            ['config.remove', ['mundophpbb_supporttriage_alert_sla_hours']],
            ['config.remove', ['mundophpbb_supporttriage_alert_kb_linked']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_enable']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_stale_days']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_stale_target']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_forums']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_forums_target']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_issue_types']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_issue_target']],
            ['config.remove', ['mundophpbb_supporttriage_notice_feed_enable']],
        ];
    }

    public function remove_all_permissions()
    {
        $permissions = [
            'a_supporttriage_manage',
            'm_supporttriage_status',
            'm_supporttriage_snippets',
            'm_supporttriage_kb_create',
            'm_supporttriage_kb_sync',
            'm_supporttriage_priority',
        ];

        $migrator_tool = $this->container->get('migrator.tool.permission');

        foreach ($permissions as $perm) {
            if ($this->auth_option_exists($perm)) {
                $is_global = ($perm === 'a_supporttriage_manage');
                $migrator_tool->remove($perm, $is_global);
            }
        }
    }

    protected function auth_option_exists($auth_option)
    {
        $sql = 'SELECT auth_option_id
                FROM ' . ACL_OPTIONS_TABLE . "
                WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'";
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return !empty($row['auth_option_id']);
    }
}