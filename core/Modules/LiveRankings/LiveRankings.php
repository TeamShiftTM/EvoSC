<?php

namespace EvoSC\Modules\LiveRankings;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\PointsController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class LiveRankings extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $originalPointsLimit = PointsController::getOriginalPointsLimit();

        Template::show($player, 'LiveRankings.widget', compact('originalPointsLimit'));
    }
}