<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1400_snippets extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_snippets_enable']);
    }

    static public function depends_on()
    {
        return ['\\mundophpbb\\supporttriage\\migrations\\v1100_statuses'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'supporttriage_snippets' => [
                    'COLUMNS' => [
                        'snippet_id' => ['UINT', 0],
                        'snippet_title' => ['VCHAR:255', ''],
                        'snippet_text' => ['MTEXT_UNI', ''],
                        'sort_order' => ['UINT', 0],
                        'is_active' => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'snippet_id',
                    'KEYS' => [
                        'sort_order' => ['INDEX', 'sort_order'],
                        'is_active' => ['INDEX', 'is_active'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'supporttriage_snippets',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_snippets_enable', 1]],
        ];
    }
}
