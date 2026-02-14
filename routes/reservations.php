<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Reservations;


// Route::group(['middleware' => 'auth:sanctum'], function () {
//=========================================Reservation=============================================
Route::post('makeReservation', [Reservations::class, 'makeReservation']);
Route::post('checkReservation', [Reservations::class, 'checkReservation']);
Route::post('getRoomPrice', [Reservations::class, 'getRoomPrice']);
// Route::get('getReservation', [Reservations::class, 'getReservation']);
// Route::post('getReservationByStudent', [Reservations::class, 'getReservationByStudent']);
// Route::post('setReservationUnavailable', [Reservations::class, 'setReservationUnavailable']);
// Route::post('getReservationByRoom', [Reservations::class, 'getReservationByRoom']);
// });
