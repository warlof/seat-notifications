<?php
/**
 * MIT License.
 *
 * Copyright (c) 2019. Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Herpaderpaldent\Seat\SeatNotifications\Notifications;

use Herpaderpaldent\Seat\SeatNotifications\Channels\Discord\DiscordChannel;
use Herpaderpaldent\Seat\SeatNotifications\Channels\Discord\DiscordMessage;
use Herpaderpaldent\Seat\SeatNotifications\Channels\Slack\SlackChannel;
use Herpaderpaldent\Seat\SeatNotifications\Channels\Slack\SlackMessage;
use Seat\Eveapi\Models\Killmails\KillmailDetail;
use Seat\Eveapi\Models\Killmails\KillmailVictimItem;
use Seat\Eveapi\Models\Market\Price;
use Seat\Eveapi\Models\Sde\InvType;

class KillMailNotification extends BaseNotification
{
    /**
     * @var array
     */
    protected $tags = ['kill_mail'];

    /**
     * @var
     */
    private $killmail_detail;

    /**
     * @var
     */
    private $image;

    /**
     * KillMailNotification constructor.
     *
     * @param \Seat\Eveapi\Models\Killmails\CorporationKillmail $corporation_killmail
     */
    public function __construct(int $killmail_id)
    {

        parent::__construct();

        $this->killmail_detail = KillmailDetail::find($killmail_id);
        $this->image = sprintf('https://imageserver.eveonline.com/Type/%d_64.png',
            $this->killmail_detail->victims->ship_type_id);

        array_push($this->tags, 'killmail_id:' . $killmail_id);
    }

    public function via($notifiable)
    {
        array_push($this->tags, $notifiable->type === 'private' ? $notifiable->recipient() : 'channel');

        switch ($notifiable->notification_channel) {
            case 'discord':
                array_push($this->tags, 'discord');

                return [DiscordChannel::class];
                break;
            case 'slack':
                array_push($this->tags, 'slack');

                return [SlackChannel::class];
                break;
            default:
                return [''];

        }
    }

    public function toDiscord($notifiable)
    {

        return (new DiscordMessage)
            ->embed(function ($embed) use ($notifiable) {

                $embed->title($this->getNotificationString('discord'))
                    ->thumbnail($this->image)
                    ->color($this->is_loss($notifiable) ? '14502713' : '42586')
                    ->field('Value', $this->getValue($this->killmail_detail->killmail_id))
                    ->field('Involved Pilots', $this->getNumberOfAttackers(), true)
                    ->field('System', $this->getSystem('discord'), true)
                    ->field('Link', $this->zKillBoardToLink('kill', $this->killmail_detail->killmail_id), true)
                    ->footer('zKillboard ' . $this->killmail_detail->killmail_time, 'https://zkillboard.com/img/wreck.png');
            });
    }

    private function getNotificationString(string $channel) : string
    {
        return sprintf('%s just killed %s %s',
            $this->getAttacker($channel),
            $this->getVictim($channel),
            $this->getNumberOfAttackers($channel) === 1 ? 'solo.' : ''
        );
    }

    private function getAttacker($channel) :string
    {
        $killmail_attacker = $this->killmail_detail
            ->attackers
            ->where('final_blow', 1)
            ->first();

        if($channel === 'discord')
            return $this->getDiscordKMStringPartial(
                $killmail_attacker->character_id,
                $killmail_attacker->corporation_id,
                $killmail_attacker->ship_type_id,
                $killmail_attacker->alliance_id
            );

        if($channel === 'slack')
            return $this->getSlackKMStringPartial(
                $killmail_attacker->character_id,
                $killmail_attacker->corporation_id,
                $killmail_attacker->ship_type_id,
                $killmail_attacker->alliance_id
            );

        return '';
    }

    private function getDiscordKMStringPartial($character_id, $corporation_id, $ship_type_id, $alliance_id) : string
    {
        $character = is_null($character_id) ? null : $this->resolveID($character_id);
        $corporation = is_null($corporation_id) ? null : $this->resolveID($corporation_id);
        $alliance = is_null($alliance_id) ? null : strtoupper('<' . $this->resolveID($alliance_id, true) . '>');
        $ship_type = optional(InvType::find($ship_type_id))->typeName;

        if(is_null($character_id))
            return sprintf('**%s** [%s] %s)',
                $ship_type,
                $corporation,
                $alliance
            );

        if (! is_null($character_id))
            return sprintf('**%s** [%s] %s flying a **%s**',
                $character,
                $corporation,
                $alliance,
                $ship_type
            );

        return '';
    }

    private function resolveID($id, $is_alliance = false)
    {
        $cached_entry = cache('name_id:' . $id);

        if(! is_null($cached_entry))
            return $cached_entry;

        if($is_alliance)
            return $this->getAllianceTicker($id);

        // Resolve the Esi client library from the IoC
        $eseye = app('esi-client')->get();
        $eseye->setBody([$id]);
        $names = $eseye->invoke('post', '/universe/names/');

        $name = collect($names)->first()->name;

        return $name;
    }

    private function getAllianceTicker($id)
    {
        $cached_entry = cache('alliance_ticker:' . $id);

        if(! is_null($cached_entry))
            return $cached_entry;

        // Resolve the Esi client library from the IoC
        $eseye = app('esi-client')->get();
        $ticker = $eseye->invoke('get', '/alliances/' . $id)->ticker;

        cache(['alliance_ticker:' . $id => $ticker], carbon()->addCentury());

        return $ticker;
    }

    private function getSlackKMStringPartial($character_id, $corporation_id, $ship_type_id, $alliance_id) : string
    {
        $character = is_null($character_id) ? null : $this->resolveID($character_id);
        $corporation = is_null($corporation_id) ? null : $this->resolveID($corporation_id);
        $alliance = is_null($alliance_id) ? null : strtoupper('<' . $this->resolveID($alliance_id, true) . '>');
        $ship_type = optional(InvType::find($ship_type_id))->typeName;

        if(is_null($character_id))
            return sprintf('*%s* [%s] %s)',
                $ship_type,
                $corporation,
                $alliance
            );

        if (! is_null($character_id))
            return sprintf('*%s* [%s] %s flying a *%s*',
                $character,
                $corporation,
                $alliance,
                $ship_type
            );

        return '';
    }

    /**
     * @param int $killmail_id
     *
     * @return string
     */
    private function getVictim($channel) :string
    {
        $killmail_victim = $this->killmail_detail->victims;

        if($channel === 'discord')
            return $this->getDiscordKMStringPartial(
                $killmail_victim->character_id,
                $killmail_victim->corporation_id,
                $killmail_victim->ship_type_id,
                $killmail_victim->alliance_id
            );

        if($channel === 'slack')
            return $this->getSlackKMStringPartial(
                $killmail_victim->character_id,
                $killmail_victim->corporation_id,
                $killmail_victim->ship_type_id,
                $killmail_victim->alliance_id
            );

        return '';
    }

    private function getNumberOfAttackers() : int
    {

        return $this->killmail_detail->attackers->count();
    }

    private function is_loss($notifiable) : bool
    {
        return $notifiable
            ->notifications
            ->firstwhere('name', 'kill_mail')
            ->hasAffiliation('corp', $this->killmail_detail->victims->corporation_id);
    }

    private function getValue(int $killmail_id) :string
    {
        $value = KillmailVictimItem::where('killmail_id', $killmail_id)
            ->get()
            ->map(function ($item) {
                return Price::find($item->item_type_id);
            })
            ->push(Price::find($this->killmail_detail->victims->ship_type_id))
            ->sum('average_price');

        return number($value) . ' ISK';
    }

    private function getSystem($channel) : string
    {
        $solar_system = $this->killmail_detail->solar_system;

        if ($channel === 'discord')
            return sprintf('[%s (%s)](%s)',
                $solar_system->itemName,
                number($solar_system->security, 2),
                $this->zKillBoardToLink('system', $solar_system->itemID)
            );

        if ($channel === 'slack')
            return sprintf('<%s|%s (%s)>',
                $this->zKillBoardToLink('system', $solar_system->itemID),
                $solar_system->itemName,
                number($solar_system->security, 2)
            );

        return $this->zKillBoardToLink('system', $solar_system->itemID);
    }

    /**
     * Build a link to zKillboard using Slack message formatting.
     *
     * @param string $type (must be ship, character, corporation or alliance)
     * @param int    $id   the type entity ID
     * @param string $name the type name
     *
     * @return string
     */
    private function zKillBoardToLink(string $type, int $id)
    {

        if (! in_array($type, ['ship', 'character', 'corporation', 'alliance', 'kill', 'system']))
            return '';

        return sprintf('https://zkillboard.com/%s/%d/', $type, $id);
    }

    /**
     * @param $notifiable
     *
     * @return \Herpaderpaldent\Seat\SeatNotifications\Channels\Slack\SlackMessage
     */
    public function toSlack($notifiable)
    {

        return (new SlackMessage)
            ->attachment(function ($attachment) use ($notifiable) {
                $attachment->content($this->getNotificationString('slack'))
                    ->thumb($this->image)
                    ->fields([
                        'Value' => $this->getValue($this->killmail_detail->killmail_id),
                        'Involved Pilots' => $this->getNumberOfAttackers(),
                        'System' => $this->getSystem('slack'),
                        'Link' => $this->zKillBoardToLink('kill', $this->killmail_detail->killmail_id),
                    ])
                    ->markdown(['System'])
                    ->color($this->is_loss($notifiable) ? '#DD4B39' : '#00A65A')
                    ->footerIcon('https://zkillboard.com/img/wreck.png')
                    ->footer('zKillboard ' . $this->killmail_detail->killmail_time);
            });
    }
}
