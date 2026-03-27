<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v3450_acp_split extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return false;
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v3320_priority_permission'];
    }

    public function update_data()
    {
        return [
            ['module.remove', [
                'acp',
                'ACP_SUPPORTTRIAGE_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\supporttriage\\acp\\main_module',
                    'modes' => ['settings'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_SUPPORTTRIAGE_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\supporttriage\\acp\\main_module',
                    'modes' => ['dashboard', 'general', 'automation', 'content', 'diagnostics'],
                ],
            ]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', [
                'acp',
                'ACP_SUPPORTTRIAGE_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\supporttriage\\acp\\main_module',
                    'modes' => ['dashboard', 'general', 'automation', 'content', 'diagnostics'],
                ],
            ]],
        ];
    }
}
