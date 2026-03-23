<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v2400_priority extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_priority_enable'])
            && isset($this->config['mundophpbb_supporttriage_default_priority']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v2200_notifications'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'supporttriage_topics' => [
                    'priority_key' => ['VCHAR:20', 'normal'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'supporttriage_topics' => [
                    'priority_key',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_priority_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_default_priority', 'normal']],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_priority_enable']],
            ['config.remove', ['mundophpbb_supporttriage_default_priority']],
        ];
    }
}
