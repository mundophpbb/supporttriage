<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1100_statuses extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_status_enable'])
            && isset($this->config['mundophpbb_supporttriage_default_status']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1000_install'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'supporttriage_topics' => [
                    'COLUMNS' => [
                        'topic_id' => ['UINT', 0],
                        'forum_id' => ['UINT', 0],
                        'status_key' => ['VCHAR:32', 'new'],
                        'status_updated' => ['TIMESTAMP', 0],
                        'status_user_id' => ['UINT', 0],
                    ],
                    'PRIMARY_KEY' => 'topic_id',
                    'KEYS' => [
                        'forum_id' => ['INDEX', 'forum_id'],
                        'status_key' => ['INDEX', 'status_key'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_topics',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_status_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_default_status', 'new']],
        ];
    }
}
