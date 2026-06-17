<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;

class ReservationRoomStatusService
{
    /** roomStatus: 1 = available, 2 = occupied, 3 = maintenance */
    public const ROOM_AVAILABLE = 1;
    public const ROOM_OCCUPIED = 2;

    public function syncForReservation(Reservation $reservation): void
    {
        $reservation->loadMissing('reservationRooms');

        foreach ($reservation->reservationRooms as $resRoom) {
            if (!$resRoom->room_id) {
                continue;
            }

            if ((int) $reservation->logedin === Reservation::LOGEDIN_IN_HOUSE
                && (int) $reservation->reservation_status === 1) {
                Room::where('id', $resRoom->room_id)->update(['roomStatus' => self::ROOM_OCCUPIED]);
                continue;
            }

            $this->setAvailableIfNoActiveStay($resRoom->room_id, $reservation->id);
        }
    }

    public function setAvailableIfNoActiveStay(int $roomId, ?int $excludeReservationId = null): void
    {
        $hasOverlap = ReservationRoom::where('room_id', $roomId)
            ->whereHas('reservation', function ($query) use ($excludeReservationId) {
                $query->where('reservation_status', 1)
                    ->where('logedin', Reservation::LOGEDIN_IN_HOUSE);
                if ($excludeReservationId) {
                    $query->where('id', '!=', $excludeReservationId);
                }
            })
            ->exists();

        if (!$hasOverlap) {
            Room::where('id', $roomId)
                ->where('active', 1)
                ->whereNotIn('roomStatus', [3, 4])
                ->update(['roomStatus' => self::ROOM_AVAILABLE]);
        }
    }
}
