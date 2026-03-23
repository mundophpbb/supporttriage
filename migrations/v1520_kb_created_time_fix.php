<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1520_kb_created_time_fix extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return false;
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1500_kb'];
    }

    public function update_schema()
    {
        return [
            'change_columns' => [
                $this->table_prefix . 'supporttriage_kb_links' => [
                    'created_time' => ['TIMESTAMP', 0],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        // Do not revert created_time back to UINT on uninstall.
        // The parent migration drops the table entirely, and downgrading
        // large UNIX timestamps before the drop can fail on MySQL/MariaDB.
        return [];
    }
}
