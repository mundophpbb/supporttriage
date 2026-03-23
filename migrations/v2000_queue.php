<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage\migrations;

class v2000_queue extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['mundophpbb_supporttriage_queue_enable'])
            && isset($this->config['mundophpbb_supporttriage_queue_stale_days']);
    }

    static public function depends_on()
    {
        return ['\mundophpbb\supporttriage\migrations\v1900_automation'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['mundophpbb_supporttriage_queue_enable', 1]],
            ['config.add', ['mundophpbb_supporttriage_queue_stale_days', 5]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['mundophpbb_supporttriage_queue_enable']],
            ['config.remove', ['mundophpbb_supporttriage_queue_stale_days']],
        ];
    }
}
