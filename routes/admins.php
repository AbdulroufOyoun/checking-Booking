<?php

use App\Http\Controllers\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admins;
use App\Http\Controllers\Buildings;
use App\Http\Controllers\Reservations;
use App\SMS;

Route::post('create_admin', [Admins::class, 'createNewAdmin']);
Route::post('login', [Admins::class, 'loginAdmin']);
Route::post('setLockDataValue', [Admins::class, 'setLockDataValue']);
Route::post('sendConfermationSMSToClient', [SMS::class, 'sendConfermationSMSToClient']);
//=========================================User====================================================
Route::post('loginStudent', [Users::class, 'loginStudent']);

// Route::group(['middleware' => 'auth:sanctum'], function () {
//=========================================Admin=============================================
Route::post('createStudent', [Admins::class, 'createNewStudent']);
Route::get('getAllStudents', [Admins::class, 'getAllStudents']);
Route::post('searchStudentByName', [Admins::class, 'searchStudentByName']);
Route::post('makeReservation', [Admins::class, 'makeReservation']);
Route::post('serachStudentBy', [Admins::class, 'serachStudentBy']);
Route::post('getAllAdmin', [Admins::class, 'getAllAdmin']);
Route::post('updateAdmin', [Admins::class, 'updateAdmin']);
Route::post('inActiveAdmin', [Admins::class, 'inActiveAdmin']);
Route::post('updateInfoStudent', [Admins::class, 'updateInfoStudent']);
Route::post('deleteStudent', [Admins::class, 'deleteStudent']);
Route::post('addCollege', [Admins::class, 'addCollege']);
Route::get('getAllCollege', [Admins::class, 'getAllCollege']);
Route::post('checkReservation', [Admins::class, 'checkReservation']);
Route::get('getAllRoomType', [Admins::class, 'getAllRoomType']);
Route::post('addRoomType', [Admins::class, 'addRoomType']);
Route::post('addFacilitie', [Admins::class, 'addFacilitie']);
Route::get('getFacilitie', [Admins::class, 'getFacilitie']);
Route::post('getFacilitieByBuilding', [Admins::class, 'getFacilitieByBuilding']);
Route::post('getFacilitieByRoom', [Admins::class, 'getFacilitieByRoom']);
Route::post('updateReservation', [Admins::class, 'updateReservation']);
//=========================================Reservation=============================================
Route::post('getReservationByDate', [Reservations::class, 'getReservationByDate']);
Route::get('getReservation', [Reservations::class, 'getReservation']);
Route::post('getReservationByStudent', [Reservations::class, 'getReservationByStudent']);
Route::post('setReservationUnavailable', [Reservations::class, 'setReservationUnavailable']);
Route::post('getReservationByRoom', [Reservations::class, 'getReservationByRoom']);
//=========================================User====================================================
Route::post('getInfoUser', [Users::class, 'getInfoUser']);
Route::post('getFacilitieByRoomForApp', [Users::class, 'getFacilitieByRoomForApp']);
Route::post('checkReservationAndUser', [Users::class, 'checkReservationAndUser']);
Route::post('recordOpeningDoor', [Users::class, 'recordOpeningDoor']);
Route::post('getRecordOpeningDoor', [Users::class, 'getRecordOpeningDoor']);
// });
