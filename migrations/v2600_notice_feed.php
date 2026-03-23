<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v2600_notice_feed extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_notice_feed_enable']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v2400_priority'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'supporttriage_notices' => [
                    'COLUMNS' => [
                        'notice_id' => ['UINT', null, 'auto_increment'],
                        'topic_id' => ['UINT', 0],
                        'forum_id' => ['UINT', 0],
                        'notice_key' => ['VCHAR:32', ''],
                        'actor_user_id' => ['UINT', 0],
                        'notice_time' => ['TIMESTAMP', 0],
                        'is_active' => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'notice_id',
                    'KEYS' => [
                        'topic_id' => ['INDEX', 'topic_id'],
                        'forum_id' => ['INDEX', 'forum_id'],
                        'notice_key' => ['INDEX', 'notice_key'],
                        'is_active' => ['INDEX', 'is_active'],
                        'notice_time' => ['INDEX', 'notice_time'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_notices',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_notice_feed_enable', 1]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_notice_feed_enable']],
        ];
    }
}
