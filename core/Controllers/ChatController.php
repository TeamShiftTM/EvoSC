<?php

namespace EvoSC\Controllers;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Log;
use EvoSC\Classes\Server;
use EvoSC\Interfaces\ControllerInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;

/**
 * Class ChatController
 *
 * Handle chat-messages and commands.
 *
 * @package EvoSC\Controllers
 */
class ChatController implements ControllerInterface
{
    /** @var boolean */
    private static bool $routingEnabled;

    /**
     * Initialize ChatController.
     */
    public static function init()
    {
        AccessRight::add('player_mute', 'Mute/unmute player.');
        AccessRight::add('admin_echoes', 'Receive admin messages.');

        if ((self::$routingEnabled = (bool)config('server.enable-chat-routing', true))) {
            Log::info('Enabling manual chat routing.', isVerbose());

            try {
                Server::chatEnableManualRouting();
                Log::info('Chat router started.');
            } catch (FaultException $e) {
                Log::warning($e->getMessage(), isVerbose());
            }
        } else {
            Server::chatEnableManualRouting(false);
        }
    }

    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot)
    {
        ChatCommand::add('//mute', [self::class, 'cmdMute'], 'Mutes a player by given nickname', 'player_mute');
        ChatCommand::add('//unmute', [self::class, 'cmdUnmute'], 'Unmute a player by given nickname', 'player_mute');
    }

    /**
     * Mute a player
     *
     * @param Player $admin
     * @param Player $target
     */
    public static function mute(Player $admin, Player $target)
    {
        Server::ignore($target->Login);
        infoMessage($admin, ' muted ', $target)->sendAll();
    }

    /**
     * Unmute a player
     *
     * @param Player $player
     * @param Player $target
     */
    public static function unmute(Player $player, Player $target)
    {
        Server::unIgnore($target->Login);
        infoMessage($player, ' unmuted ', $target)->sendAll();
    }

    /**
     * Chat-command: unmute player.
     *
     * @param Player $player
     * @param                    $nick
     */
    public static function cmdUnmute(Player $player, $nick)
    {
        $target = PlayerController::findPlayerByName($player, $nick);

        if ($target) {
            self::unmute($player, $target);
        }
    }

    /**
     * Chat-command: mute player.
     *
     * @param Player $admin
     * @param                    $nick
     */
    public static function cmdMute(Player $admin, $nick)
    {
        $target = PlayerController::findPlayerByName($admin, $nick);

        if (!$target) {
            //No target found
            return;
        }

        self::mute($admin, $target);
    }

    public static function isPlayerMuted(Player $player)
    {
        return collect(Server::getIgnoreList())->contains('login', '=', $player->Login);
    }

    public static function pmTo(Player $player, $login, $message)
    {
        $target = player($login);

        if ($target->id == $player->id) {
            warningMessage('You can\'t PM yourself.')->send($player);

            return;
        }

        $from = sprintf(secondary('[from:') . $player . secondary('] '));
        $to = sprintf(secondary('[to:') . $target . secondary('] '));

        chatMessage($from . $message)->setIcon('')->send($target);
        chatMessage($to . $message)->setIcon('')->send($player);
    }

    /**
     * Process chat-message and detect commands.
     *
     * @param Player $player
     * @param string $text
     */
    public static function playerChat(Player $player, $text)
    {
        Log::write('<fg=yellow>[' . $player . '] ' . $text . '</>', true);

        $name = preg_replace('/(?:(?<=[^$])\$s|^\$s)/i', '', $player->NickName);
        $text = preg_replace('/(?:(?<=[^$])\$s|^\$s)/i', '', $text);

        if (strlen(trim(stripAll($text))) == 0) {
            return;
        }

        if ($player->isSpectator()) {
            $name = '$<$eee$> $fff' . $name;
        }

        if (preg_match('/\$l(https?:\/\/[^ ]+) ?/', $text, $matches)) {
            $link = sprintf('[%s]%s', $matches[1], $matches[1]);
            $text = str_replace($matches[1], $link, $text);
        }

        $groupIcon = $player->group->chat_prefix ?? '';
        $groupColor = $player->group->color;
        $chatText = sprintf('$z$s$%s%s[$<%s$>]$z$s %s $z', $groupColor, $groupIcon, secondary($name), $text);

        Server::ChatSendServerMessage($chatText);
    }

    /**
     * @return mixed
     */
    public static function getRoutingEnabled()
    {
        return self::$routingEnabled;
    }
}