<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users;
use App\Http\Controllers\Buildings;


Route::post('login', [Users::class, 'login']);

// Route::group(['middleware' => 'auth:sanctum'], function () {
//=========================================Buildings=============================================
Route::post('addBuilding', [Buildings::class, 'addBuilding']);
Route::post('addFloor', [Buildings::class, 'addFloor']);
Route::post('addSuite', [Buildings::class, 'addSuite']);
Route::post('addRoom', [Buildings::class, 'addRoom']);
Route::post('deleteRoom', [Buildings::class, 'deleteRoom']);
Route::post('deleteFloor', [Buildings::class, 'deleteFloor']);
Route::post('deleteSuite', [Buildings::class, 'deleteSuite']);
Route::post('deleteBuilding', [Buildings::class, 'deleteBuilding']);
Route::post('updateRoom', [Buildings::class, 'updateRoom']);
Route::post('updateSuite', [Buildings::class, 'updateSuite']);
Route::post('updateFloor', [Buildings::class, 'updateFloor']);
Route::post('updateBuilding', [Buildings::class, 'updateBuilding']);
Route::post('getBuildingData', [Buildings::class, 'getBuildingData']);
Route::post('addMultiRoom', [Buildings::class, 'addMultiRoom']);
//=====================================Discounts=================================================
Route::post('addDiscount', [Users::class, 'addDiscount']);
Route::post('updateDiscount', [Users::class, 'updateDiscount']);
Route::post('deleteDiscount', [Users::class, 'deleteDiscount']);
Route::get('getDiscounts', [Users::class, 'getDiscounts']);
//=====================================GuestClassification=======================================
Route::post('addGuestClassification', [Users::class, 'addGuestClassification']);
Route::post('updateGuestClassification', [Users::class, 'updateGuestClassification']);
Route::post('deleteGuestClassification', [Users::class, 'deleteGuestClassification']);
//=====================================GuestFeature==============================================
Route::post('addGuestFeature', [Users::class, 'addGuestFeature']);
Route::post('updateGuestFeature', [Users::class, 'updateGuestFeature']);
Route::post('deleteGuestFeature', [Users::class, 'deleteGuestFeature']);
Route::get('getGuestFeature', [Users::class, 'getGuestFeature']);
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
Route::post('/getClientBy', [Users::class, 'getClientBy']);
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
Route::post('/inActiveUser', [Users::class, 'inActiveUser']);
Route::get('/getInfoUsers', [Users::class, 'getInfoUsers']);
//=====================================Permissions=================================================
Route::get('/addPermissions', [Users::class, 'addPermissions']);
//=====================================PermissionUser==============================================
Route::get('/addUserPermission', [Users::class, 'addUserPermission']);
//=====================================PricingPlan=================================================
Route::get('/getRoomtypePricing', [Users::class, 'getRoomtypePricing']);
Route::post('/addRoomtypePricing', [Users::class, 'addRoomtypePricing']);
Route::post('/updateRoomtypePricing', [Users::class, 'updateRoomtypePricing']);
Route::post('/deleteRoomtypePricing', [Users::class, 'deleteRoomtypePricing']);
//=====================================PeakDaysCheck===============================================
Route::post('/updatePeakDaysCheck', [Users::class, 'updatePeakDaysCheck']);
Route::get('/seedWeekDays', [Users::class, 'seedWeekDays']);
//=====================================PeakMonthsCheck===============================================
Route::post('/updatePeakMonthsCheck', [Users::class, 'updatePeakMonthsCheck']);
Route::get('/seedMonths', [Users::class, 'seedMonths']);
//=====================================RoomType====================================================
Route::post('/addRoomType', [Users::class, 'addRoomType']);
Route::post('/updateRoomType', [Users::class, 'updateRoomType']);
Route::post('/deleteRoomType', [Users::class, 'deleteRoomType']);


// });
