<?php
/**
 * Created by PhpStorm.
 * User: felix
 * Date: 26.01.2019
 * Time: 09:18
 */

namespace Herpaderpaldent\Seat\SeatNotifications\Models;


use Illuminate\Database\Eloquent\Model;

class SeatNotification extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'herpaderp_seat_notification_notification_recipients';

    public $incrementing = false;

    protected $fillable = ['channel_id', 'name'];

    public function recipients()
    {
        return $this->belongsTo(SeatNotificationRecipient::class, 'channel_id', 'channel_id');
    }


}