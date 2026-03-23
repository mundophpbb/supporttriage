<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v2200_notifications extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_notifications_enable'])
            && isset($this->config['mundophpbb_supporttriage_alert_author_return'])
            && isset($this->config['mundophpbb_supporttriage_alert_no_reply'])
            && isset($this->config['mundophpbb_supporttriage_alert_sla_warning'])
            && isset($this->config['mundophpbb_supporttriage_alert_sla_hours'])
            && isset($this->config['mundophpbb_supporttriage_alert_kb_linked']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v2000_queue'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_notifications_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_author_return', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_no_reply', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_sla_warning', 1]],
            ['config.add', ['mundophpbb_supporttriage_alert_sla_hours', 24]],
            ['config.add', ['mundophpbb_supporttriage_alert_kb_linked', 1]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_notifications_enable']],
            ['config.remove', ['mundophpbb_supporttriage_alert_author_return']],
            ['config.remove', ['mundophpbb_supporttriage_alert_no_reply']],
            ['config.remove', ['mundophpbb_supporttriage_alert_sla_warning']],
            ['config.remove', ['mundophpbb_supporttriage_alert_sla_hours']],
            ['config.remove', ['mundophpbb_supporttriage_alert_kb_linked']],
        ];
    }
}
