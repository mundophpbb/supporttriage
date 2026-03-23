<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v1700_permissions extends \phpbb\db\migration\container_aware_migration
{
    public function effectively_installed()
    {
        return $this->auth_option_exists('a_supporttriage_manage')
            && $this->auth_option_exists('m_supporttriage_status')
            && $this->auth_option_exists('m_supporttriage_snippets')
            && $this->auth_option_exists('m_supporttriage_kb_create')
            && $this->auth_option_exists('m_supporttriage_kb_sync');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1520_kb_created_time_fix'];
    }

    public function update_data()
    {
        return [
            ['permission.add', ['a_supporttriage_manage']],
            ['permission.add', ['m_supporttriage_status', false]],
            ['permission.add', ['m_supporttriage_snippets', false]],
            ['permission.add', ['m_supporttriage_kb_create', false]],
            ['permission.add', ['m_supporttriage_kb_sync', false]],

            ['permission.permission_set', ['ROLE_ADMIN_FULL', 'a_supporttriage_manage']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_status']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_snippets']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_kb_create']],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_kb_sync']],
        ];
    }

    public function revert_data()
    {
        return [
            ['custom', [[$this, 'remove_permissions']]],
        ];
    }

    protected function auth_option_exists($auth_option)
    {
        $sql = 'SELECT auth_option_id
            FROM ' . ACL_OPTIONS_TABLE . "
            WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'";
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return !empty($row['auth_option_id']);
    }

    public function remove_permissions()
    {
        $migrator_tool_permission = $this->container->get('migrator.tool.permission');
        $permissions = [
            'a_supporttriage_manage' => true,
            'm_supporttriage_status' => false,
            'm_supporttriage_snippets' => false,
            'm_supporttriage_kb_create' => false,
            'm_supporttriage_kb_sync' => false,
        ];

        foreach ($permissions as $auth_option => $is_global)
        {
            if ($this->auth_option_exists($auth_option))
            {
                $migrator_tool_permission->remove($auth_option, $is_global);
            }
        }
    }
}
