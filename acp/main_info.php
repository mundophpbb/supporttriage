<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\\mundophpbb\\supporttriage\\acp\\main_module',
            'title' => 'ACP_SUPPORTTRIAGE_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'ACP_SUPPORTTRIAGE_SETTINGS',
                    'auth' => 'acl_a_supporttriage_manage',
                    'cat' => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
            ],
        ];
    }
}
