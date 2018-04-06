<?php

use esc\Classes\File;
use esc\Classes\ManiaLinkEvent;
use esc\Classes\Template;
use esc\Controllers\ChatController;
use esc\Controllers\MapController;
use esc\Models\Map;
use esc\Models\Player;
use Illuminate\Support\Collection;

class MapList
{
    public function __construct()
    {
        Template::add('maplist.show', File::get(__DIR__ . '/Templates/map-list.latte.xml'));

        ManiaLinkEvent::add('maplist.show', 'MapList::showMapList');
        ManiaLinkEvent::add('maplist.queue', 'MapList::queueMap');
        ManiaLinkEvent::add('maplist.filter.author', 'MapList::filterAuthor');
        ManiaLinkEvent::add('maplist.delete', 'MapList::deleteMap', 'map.delete');

        ChatController::addCommand('list', 'MapList::list', 'Display list of maps');
    }

    public static function list(Player $player, $cmd, $filter = null)
    {
        self::showMapList($player, 1, $filter);
    }

    private static function getRecordsForPlayer(Player $player): Collection
    {
        $records = collect([])
            ->concat($player->locals)
            ->concat($player->dedis);

        return $records;
    }

    private static function getRecordsForMapsAndPlayer($maps, Player $player): ?array
    {
        $mapIds = array_keys($maps);

        try{
            $records = [
                'locals' => LocalRecord::whereIn('Map', $mapIds)->wherePlayer($player->id)->get()->keyBy('Map')->all(),
                'dedis' => Dedi::whereIn('Map', $mapIds)->wherePlayer($player->id)->get()->keyBy('Map')->all()
            ];
        }catch(\Exception $e){
            \esc\Classes\Log::error('Failed to load records for player ' . $player->Login . "\n" . $e->getTrace());
            return null;
        }

        return $records;
    }

    public static function showMapList(Player $player, $page = null, $filter = null)
    {
        $perPage = 23;

        if ($filter) {

            if ($filter == 'worst') {

                $mapIds = self::getRecordsForPlayer($player)
                    ->sortByDesc('Rank')
                    ->pluck('map.id')
                    ->toArray();

            } elseif ($filter == 'best') {

                $mapIds = self::getRecordsForPlayer($player)
                    ->sortBy('Rank')
                    ->pluck('map.id')
                    ->toArray();

            } elseif ($filter == 'nofinish') {

                $records = self::getRecordsForPlayer($player)
                    ->pluck('map.id')
                    ->toArray();

                $mapIds = Map::whereNotIn('id', $records)
                    ->pluck('id')
                    ->toArray();

            } else {

                $mapIds = maps()
                    ->filter(function (Map $map) use ($filter) {
                        $nameMatch = strpos(strtolower(stripAll($map->Name)), strtolower($filter));
                        return (is_int($nameMatch) || $map->Author == $filter);
                    })
                    ->pluck('id')
                    ->toArray();

            }

        } else {

            $mapIds = maps()
                ->pluck('id')
                ->toArray();

        }

        $pages = ceil(Map::count() / $perPage);

        $maps = Map::whereIn('id', $mapIds)->get();
        $maps = $maps->forPage($page ?? 0, $perPage)->keyBy('id')->all();

        $records = self::getRecordsForMapsAndPlayer($maps, $player);

        $queuedMaps = MapController::getQueue()->sortBy('timeRequested')->take($perPage);

        $mapList = Template::toString('maplist.show', ['maps' => $maps, 'player' => $player, 'queuedMaps' => $queuedMaps, 'locals' => $records['locals'], 'dedis' => $records['dedis']]);
        $pagination = Template::toString('esc.pagination', ['pages' => $pages, 'action' => 'maplist.show', 'page' => $page]);

        Template::show($player, 'esc.modal', [
            'id' => 'MapList',
            'width' => 180,
            'height' => 97,
            'content' => $mapList,
            'pagination' => $pagination,
            'showAnimation' => isset($page) ? false : true
        ]);
    }

    public static function filterAuthor(Player $player, $authorLogin, $page = 1)
    {
        self::showMapList($player, $page, $authorLogin);
    }

    public static function closeMapList(Player $player)
    {
        Template::hide($player, 'MapList');
    }

    public static function queueMap(Player $player, $mapId)
    {
        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::queueMap($player, $map);
            Template::hide($player, 'maplist.show');
        } else {
            ChatController::message($player, 'Invalid map selected');
        }

        self::closeMapList($player);
    }

    public static function deleteMap(Player $player, $mapId)
    {
        if (!$player->isAdmin()) {
            ChatController::message($player, 'You do not have access to that command');
            return;
        }

        $map = Map::where('id', intval($mapId))->first();

        if ($map) {
            MapController::deleteMap($map);
            self::closeMapList($player);
        }
    }
}