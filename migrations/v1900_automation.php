<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1900_automation extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_automation_enable'])
            && isset($this->config['mundophpbb_supporttriage_auto_waiting_reply'])
            && isset($this->config['mundophpbb_supporttriage_auto_in_progress'])
            && isset($this->config['mundophpbb_supporttriage_auto_no_reply_days']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1800_logs'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'supporttriage_topics' => [
                    'topic_author_id' => ['UINT', 0],
                    'last_author_reply' => ['TIMESTAMP', 0],
                    'last_staff_reply' => ['TIMESTAMP', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'supporttriage_topics' => [
                    'topic_author_id',
                    'last_author_reply',
                    'last_staff_reply',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_automation_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_waiting_reply', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_in_progress', 1]],
            ['config.add', ['mundophpbb_supporttriage_auto_no_reply_days', 7]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_automation_enable']],
            ['config.remove', ['mundophpbb_supporttriage_auto_waiting_reply']],
            ['config.remove', ['mundophpbb_supporttriage_auto_in_progress']],
            ['config.remove', ['mundophpbb_supporttriage_auto_no_reply_days']],
        ];
    }
}
