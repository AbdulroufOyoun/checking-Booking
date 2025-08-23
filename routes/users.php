<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users;


Route::post('login', [Users::class, 'loginStudent']);

// Route::group(['middleware' => 'auth:sanctum'], function () {
//=====================================Discounts=================================================
Route::post('addDiscount', [Users::class, 'addDiscount']);
Route::post('updateDiscount', [Users::class, 'updateDiscount']);
Route::post('deleteDiscount', [Users::class, 'deleteDiscount']);
Route::get('getDiscounts', [Users::class, 'getDiscounts']);
//=====================================GuestClassification=======================================
Route::post('addGuestClassification', [Users::class, 'addGuestClassification']);
Route::post('updateGuestClassification', [Users::class, 'updateGuestClassification']);
Route::get('getGuestClassification', [Users::class, 'getGuestClassification']);
Route::post('deleteGuestClassification', [Users::class, 'deleteGuestClassification']);
//=====================================GuestFeature==============================================
Route::post('addGuestFeature', [Users::class, 'addGuestFeature']);
Route::post('updateGuestFeature', [Users::class, 'updateGuestFeature']);
Route::post('deleteGuestFeature', [Users::class, 'deleteGuestFeature']);
Route::get('getGuestFeature', [Users::class, 'getGuestFeature']);
//=====================================GuestClassiFicationFeature================================
Route::post('addGuestClassificationFeature', [Users::class, 'addGuestClassificationFeature']);
Route::post('deleteGuestClassificationFeature', [Users::class, 'deleteGuestClassificationFeature']);
Route::get('getGuestClassificationFeature', [Users::class, 'getGuestClassificationFeature']);
//=====================================StayReason================================================
Route::post('/addStayReason', [Users::class, 'addStayReason']);
Route::post('/updateStayReason', [Users::class, 'updateStayReason']);
Route::post('/deleteStayReason', [Users::class, 'deleteStayReason']);
Route::get('/getStayReasons', [Users::class, 'getStayReasons']);
//=====================================Features===================================================
Route::post('/addFeature', [Users::class, 'addFeature']);
Route::post('/deleteFeature', [Users::class, 'deleteFeature']);
Route::get('/getFeature', [Users::class, 'getFeature']);
//=====================================RoomFeature================================================
Route::post('/addRoomFeature', [Users::class, 'addRoomFeature']);
Route::post('/deleteRoomFeature', [Users::class, 'deleteRoomFeature']);
Route::get('/getRoomFeature', [Users::class, 'getRoomFeature']);
//=====================================Penaltie===================================================
Route::post('/addPenaltie', [Users::class, 'addPenaltie']);
Route::post('/deletePenaltie', [Users::class, 'deletePenaltie']);
Route::get('/getPenalties', [Users::class, 'getPenalties']);
//=====================================ReservationPenalties=======================================
Route::post('/addReservationPenalty', [Users::class, 'addReservationPenalty']);
Route::get('/getPenaltiesByReservationId', [Users::class, 'getPenaltiesByReservationId']);
//=====================================Tax========================================================
Route::post('/addTax', [Users::class, 'addTax']);
Route::get('/getTax', [Users::class, 'getTax']);
Route::post('/deleteTax', [Users::class, 'deleteTax']);
//=====================================ReservationSources=========================================
Route::post('/addReservationSource', [Users::class, 'addReservationSource']);
Route::post('/updateReservationSource', [Users::class, 'updateReservationSource']);
Route::post('/deleteReservationSource', [Users::class, 'deleteReservationSource']);
Route::get('/getReservationSource', [Users::class, 'getReservationSource']);
//=====================================Clients====================================================
Route::post('/addClient', [Users::class, 'addClient']);
Route::post('/getClientByMobile', [Users::class, 'getClientByMobile']);
//=====================================Department=================================================
Route::post('/addDepartment', [Users::class, 'addDepartment']);
Route::get('/getDepartment', [Users::class, 'getDepartment']);
Route::post('/getDepartmentById', [Users::class, 'getDepartmentById']);
Route::post('/deleteDepartment', [Users::class, 'deleteDepartment']);
//=====================================JobTitle===================================================
Route::post('/addJobTitle', [Users::class, 'addJobTitle']);
Route::get('/getJobTitle', [Users::class, 'getJobTitle']);
Route::post('/getJobTitlesByDepartment', [Users::class, 'getJobTitlesByDepartment']);
//=====================================Users======================================================
Route::post('/addUser', [Users::class, 'addUser']);
Route::post('/updateUser', [Users::class, 'updateUser']);
Route::post('/deleteUser', [Users::class, 'deleteUser']);
Route::get('/getUsersByStatus', [Users::class, 'getUsersByStatus']);
//=====================================Permissions=================================================
Route::get('/addPermissions', [Users::class, 'addPermissions']);
//=====================================PermissionUser==============================================
Route::get('/addPermissionUser', [Users::class, 'addPermissionUser']);
//=====================================PricingPlan=================================================
Route::post('/addPricingPlan', [Users::class, 'addPricingPlan']);
Route::post('/getPricingplansByDate', [Users::class, 'getPricingplansByDate']);
Route::post('/getRoomtypePricingByDate', [Users::class, 'getRoomtypePricingByDate']);
Route::post('/addRoomtypePricingplan', [Users::class, 'addRoomtypePricingplan']);
//=====================================PeakDaysCheck===============================================
Route::post('/updatePeakDaysCheck', [Users::class, 'updatePeakDaysCheck']);


// });
