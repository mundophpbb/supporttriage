<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1500_kb extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_kb_enable']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1400_snippets'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'supporttriage_kb_links' => [
                    'COLUMNS' => [
                        'source_topic_id' => ['UINT', 0],
                        'source_forum_id' => ['UINT', 0],
                        'kb_topic_id' => ['UINT', 0],
                        'kb_forum_id' => ['UINT', 0],
                        'created_by' => ['UINT', 0],
                        'created_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'source_topic_id',
                    'KEYS' => [
                        'kb_topic_id' => ['INDEX', 'kb_topic_id'],
                        'kb_forum_id' => ['INDEX', 'kb_forum_id'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_kb_links',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_kb_enable', 0]],
            ['config.add', ['mundophpbb_supporttriage_kb_forum', '']],
            ['config.add', ['mundophpbb_supporttriage_kb_prefix', '[KB Draft]']],
            ['config.add', ['mundophpbb_supporttriage_kb_lock', 1]],
        ];
    }
}
