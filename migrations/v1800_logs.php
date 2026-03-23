<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1800_logs extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_logs_enable']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1700_permissions'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'supporttriage_logs' => [
                    'COLUMNS' => [
                        'log_id' => ['UINT', null, 'auto_increment'],
                        'topic_id' => ['UINT', 0],
                        'forum_id' => ['UINT', 0],
                        'action_key' => ['VCHAR:32', ''],
                        'old_value' => ['VCHAR:100', ''],
                        'new_value' => ['VCHAR:100', ''],
                        'related_topic_id' => ['UINT', 0],
                        'related_forum_id' => ['UINT', 0],
                        'user_id' => ['UINT', 0],
                        'log_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => [
                        'topic_id' => ['INDEX', 'topic_id'],
                        'forum_id' => ['INDEX', 'forum_id'],
                        'log_time' => ['INDEX', 'log_time'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_logs',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_logs_enable', 1]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_logs_enable']],
        ];
    }
}
