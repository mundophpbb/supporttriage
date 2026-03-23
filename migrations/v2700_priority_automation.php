<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v2700_priority_automation extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_priority_auto_enable'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_stale_days'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_stale_target'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_forums'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_forums_target'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_issue_types'])
            && isset($this->config['mundophpbb_supporttriage_priority_auto_issue_target']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v2600_notice_feed'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_priority_auto_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_stale_days', 3]],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_stale_target', 'high']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_forums', '']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_forums_target', 'critical']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_issue_types', 'permissions,email']],
            ['config.add', ['mundophpbb_supporttriage_priority_auto_issue_target', 'high']],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_enable']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_stale_days']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_stale_target']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_forums']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_forums_target']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_issue_types']],
            ['config.remove', ['mundophpbb_supporttriage_priority_auto_issue_target']],
        ];
    }
}
