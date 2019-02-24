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

namespace Herpaderpaldent\Seat\SeatNotifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSubscription extends Model
{
    /**
     * The table associated with the model.
     * herpaderp_seat_notification_notification_recipients.
     * @var string
     */
    protected $table = 'herpaderp_seat_notification_subscriptions';

    protected $primaryKey = 'recipient_id';

    protected $fillable = ['notification', 'affiliation'];

    public $incrementing = false;

    public function recipient()
    {
        return $this->belongsTo(NotificationRecipient::class, 'recipient_id', 'id');
    }

    public function affiliations()
    {
        return json_decode($this->affiliation);
    }

    public function hasAffiliation(string $type, int $id) : bool
    {
        if($type === 'corp')
            return in_array($id, $this->affiliations()->corporations);

        return false;
    }
}