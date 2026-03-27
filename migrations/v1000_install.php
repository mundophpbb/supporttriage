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

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_forums', '']],
            ['config.add', ['mundophpbb_supporttriage_auto_insert', 1]],
            ['config.add', ['mundophpbb_supporttriage_prefix', '[SUPORTE]']],

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
                    'modes' => ['settings'],
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
                    'modes' => ['settings'],
                ],
            ]],
            ['module.remove', [
                'acp',
                'ACP_SUPPORTTRIAGE_TITLE',
            ]],
            ['config.remove', ['mundophpbb_supporttriage_prefix']],
            ['config.remove', ['mundophpbb_supporttriage_auto_insert']],
            ['config.remove', ['mundophpbb_supporttriage_forums']],
            ['config.remove', ['mundophpbb_supporttriage_enable']],
        ];
    }
}
