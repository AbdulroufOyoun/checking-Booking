<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;


// Route::group(['middleware' => 'auth:sanctum'], function () {
//=========================================Reservation=============================================
Route::post('makeReservation', [ReservationController::class, 'makeReservation']);
Route::post('checkReservation', [ReservationController::class, 'checkReservation']);
Route::post('getRoomPrice', [ReservationController::class, 'getRoomPrice']);
// Route::get('getReservation', [Reservations::class, 'getReservation']);
// Route::post('getReservationByStudent', [Reservations::class, 'getReservationByStudent']);
// Route::post('setReservationUnavailable', [Reservations::class, 'setReservationUnavailable']);
// Route::post('getReservationByRoom', [Reservations::class, 'getReservationByRoom']);
// });
