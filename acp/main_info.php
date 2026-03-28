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
            'title'    => 'ACP_SUPPORTTRIAGE_TITLE',
            'modes'    => [
                'dashboard' => [
                    'title' => 'ACP_SUPPORTTRIAGE_DASHBOARD_TAB',
                    'auth'  => 'ext_mundophpbb/supporttriage && acl_a_supporttriage_manage',
                    'cat'   => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
                'general' => [
                    'title' => 'ACP_SUPPORTTRIAGE_GENERAL_TAB',
                    'auth'  => 'ext_mundophpbb/supporttriage && acl_a_supporttriage_manage',
                    'cat'   => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
                'automation' => [
                    'title' => 'ACP_SUPPORTTRIAGE_AUTOMATION_TAB',
                    'auth'  => 'ext_mundophpbb/supporttriage && acl_a_supporttriage_manage',
                    'cat'   => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
                'content' => [
                    'title' => 'ACP_SUPPORTTRIAGE_CONTENT_TAB',
                    'auth'  => 'ext_mundophpbb/supporttriage && acl_a_supporttriage_manage',
                    'cat'   => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
                'diagnostics' => [
                    'title' => 'ACP_SUPPORTTRIAGE_DIAGNOSTICS_TAB',
                    'auth'  => 'ext_mundophpbb/supporttriage && acl_a_supporttriage_manage',
                    'cat'   => ['ACP_SUPPORTTRIAGE_TITLE'],
                ],
            ],
        ];
    }
}