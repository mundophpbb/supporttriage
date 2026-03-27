<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage;

class ext extends \phpbb\extension\base
{
    const ACP_CLASS = 'acp';
    const ACP_PARENT_LANG = 'ACP_CAT_DOT_MODS';
    const ACP_CATEGORY_LANG = 'ACP_SUPPORTTRIAGE_TITLE';
    const ACP_MODULE_BASENAME = '\\mundophpbb\\supporttriage\\acp\\main_module';
    const ACP_MODULE_AUTH = 'acl_a_supporttriage_manage';

    protected static $acp_modes = [
        'dashboard' => 'ACP_SUPPORTTRIAGE_DASHBOARD_TAB',
        'general' => 'ACP_SUPPORTTRIAGE_GENERAL_TAB',
        'automation' => 'ACP_SUPPORTTRIAGE_AUTOMATION_TAB',
        'content' => 'ACP_SUPPORTTRIAGE_CONTENT_TAB',
        'diagnostics' => 'ACP_SUPPORTTRIAGE_DIAGNOSTICS_TAB',
    ];

    public function enable_step($old_state)
    {
        $parent_state = $this->unwrap_state($old_state, 'supporttriage_enable_parent');
        $parent_state = parent::enable_step($parent_state);

        if ($parent_state === false)
        {
            $this->sync_acp_modules(true);
            return false;
        }

        return ['supporttriage_enable_parent' => $parent_state];
    }

    public function disable_step($old_state)
    {
        if ($old_state === false)
        {
            $this->sync_acp_modules(false);
        }

        $parent_state = $this->unwrap_state($old_state, 'supporttriage_disable_parent');
        $parent_state = parent::disable_step($parent_state);

        if ($parent_state === false)
        {
            return false;
        }

        return ['supporttriage_disable_parent' => $parent_state];
    }

    protected function unwrap_state($old_state, $key)
    {
        if (is_array($old_state) && array_key_exists($key, $old_state))
        {
            return $old_state[$key];
        }

        return $old_state;
    }

    protected function sync_acp_modules($enable)
    {
        try
        {
            /** @var \phpbb\db\driver\driver_interface $db */
            $db = $this->container->get('dbal.conn');
            /** @var \phpbb\module\module_manager $module_manager */
            $module_manager = $this->container->get('module.manager');

            if ($enable)
            {
                $this->install_acp_modules($db, $module_manager);
            }
            else
            {
                $this->remove_acp_modules($db, $module_manager);
            }

            $module_manager->remove_cache_file(self::ACP_CLASS);
        }
        catch (\Throwable $e)
        {
            // Do not block enable/disable if ACP cleanup/sync cannot run.
        }
    }

    protected function install_acp_modules($db, $module_manager)
    {
        $mods_parent_id = $this->find_module_id($db, self::ACP_PARENT_LANG, '', 0);
        if (!$mods_parent_id)
        {
            return;
        }

        $category_id = $this->find_module_id($db, self::ACP_CATEGORY_LANG, '', $mods_parent_id);

        if (!$category_id)
        {
            $module_manager->update_module_data([
                'module_basename' => '',
                'module_enabled' => 1,
                'module_display' => 1,
                'parent_id' => (int) $mods_parent_id,
                'module_class' => self::ACP_CLASS,
                'module_langname' => self::ACP_CATEGORY_LANG,
                'module_mode' => '',
                'module_auth' => '',
            ]);

            $category_id = $this->find_module_id($db, self::ACP_CATEGORY_LANG, '', $mods_parent_id);
        }

        if (!$category_id)
        {
            return;
        }

        $this->remove_modules_by_mode($db, $module_manager, ['settings']);

        foreach (self::$acp_modes as $mode => $langname)
        {
            if ($this->find_module_id($db, $langname, $mode, $category_id))
            {
                continue;
            }

            $module_manager->update_module_data([
                'module_basename' => self::ACP_MODULE_BASENAME,
                'module_enabled' => 1,
                'module_display' => 1,
                'parent_id' => (int) $category_id,
                'module_class' => self::ACP_CLASS,
                'module_langname' => $langname,
                'module_mode' => $mode,
                'module_auth' => self::ACP_MODULE_AUTH,
            ]);
        }
    }

    protected function remove_acp_modules($db, $module_manager)
    {
        $this->remove_modules_by_mode($db, $module_manager, array_merge(['settings'], array_keys(self::$acp_modes)));

        $category_ids = $this->find_module_ids_by_langname($db, self::ACP_CATEGORY_LANG);
        rsort($category_ids);

        foreach ($category_ids as $module_id)
        {
            try
            {
                $module_manager->delete_module((int) $module_id, self::ACP_CLASS);
            }
            catch (\Throwable $e)
            {
                // Ignore leftover/orphan cases and continue cleanup.
            }
        }
    }

    protected function remove_modules_by_mode($db, $module_manager, array $modes)
    {
        if (empty($modes))
        {
            return;
        }

        $escaped_modes = [];
        foreach ($modes as $mode)
        {
            $escaped_modes[] = "'" . $db->sql_escape($mode) . "'";
        }

        $sql = 'SELECT module_id
            FROM ' . MODULES_TABLE . '
            WHERE module_class = \'' . $db->sql_escape(self::ACP_CLASS) . '\'
                AND module_basename = \'' . $db->sql_escape(self::ACP_MODULE_BASENAME) . '\'
                AND module_mode IN (' . implode(', ', $escaped_modes) . ')
            ORDER BY right_id DESC, module_id DESC';
        $result = $db->sql_query($sql);

        $module_ids = [];
        while ($module_id = (int) $db->sql_fetchfield('module_id'))
        {
            $module_ids[] = $module_id;
        }
        $db->sql_freeresult($result);

        foreach ($module_ids as $module_id)
        {
            try
            {
                $module_manager->delete_module($module_id, self::ACP_CLASS);
            }
            catch (\Throwable $e)
            {
                // Ignore leftover/orphan cases and continue cleanup.
            }
        }
    }

    protected function find_module_id($db, $langname, $mode = '', $parent_id = null)
    {
        $sql = 'SELECT module_id
            FROM ' . MODULES_TABLE . '
            WHERE module_class = \'' . $db->sql_escape(self::ACP_CLASS) . '\'
                AND module_langname = \'' . $db->sql_escape($langname) . '\'
                AND module_mode = \'' . $db->sql_escape($mode) . '\'';

        if ($parent_id !== null)
        {
            $sql .= ' AND parent_id = ' . (int) $parent_id;
        }

        $sql .= ' ORDER BY module_id ASC';

        $result = $db->sql_query_limit($sql, 1);
        $module_id = (int) $db->sql_fetchfield('module_id');
        $db->sql_freeresult($result);

        return $module_id;
    }

    protected function find_module_ids_by_langname($db, $langname)
    {
        $sql = 'SELECT module_id
            FROM ' . MODULES_TABLE . '
            WHERE module_class = \'' . $db->sql_escape(self::ACP_CLASS) . '\'
                AND module_langname = \'' . $db->sql_escape($langname) . '\'
            ORDER BY right_id DESC, module_id DESC';
        $result = $db->sql_query($sql);

        $module_ids = [];
        while ($module_id = (int) $db->sql_fetchfield('module_id'))
        {
            $module_ids[] = $module_id;
        }
        $db->sql_freeresult($result);

        return $module_ids;
    }
}
