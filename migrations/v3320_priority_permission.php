<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v3320_priority_permission extends \phpbb\db\migration\container_aware_migration
{
    public function effectively_installed()
    {
        return $this->auth_option_exists('m_supporttriage_priority');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1700_permissions'];
    }

    public function update_data()
    {
        return [
            ['permission.add', ['m_supporttriage_priority', false]],
            ['permission.permission_set', ['ROLE_MOD_FULL', 'm_supporttriage_priority']],
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

        if ($this->auth_option_exists('m_supporttriage_priority'))
        {
            $migrator_tool_permission->remove('m_supporttriage_priority', false);
        }
    }
}
