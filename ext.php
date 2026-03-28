<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\supporttriage;

class ext extends \phpbb\extension\base
{
    public function is_enableable()
    {
        return phpbb_version_compare(PHPBB_VERSION, '3.3.0', '>=')
            && phpbb_version_compare(PHPBB_VERSION, '4.0.0-dev', '<');
    }
}
