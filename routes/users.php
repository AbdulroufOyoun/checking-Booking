<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users;
use App\Http\Controllers\Buildings;
use App\Http\Controllers\BuildingsController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\SuiteController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypesController;
use App\Http\Controllers\FacilitiesController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\StayReasonsController;


Route::post('login', [Users::class, 'login']);

// Building
// Route::group(['middleware' => 'auth:sanctum'], function () {
//=========================================Buildings=============================================
//Route::post('getBuildingData', [Buildings::class, 'getBuildingData']);
Route::get('buildings', [BuildingsController::class, 'index']);
Route::post('updateBuilding', [BuildingsController::class, 'update']);
Route::delete('building', [BuildingsController::class, 'destroy']);

//=========================================Floor=============================================
Route::get('floors', [FloorController::class, 'index']);
Route::post('addFloor', [FloorController::class, 'addFloor']);
Route::delete('deleteFloor', [FloorController::class, 'deleteFloor']);
Route::post('updateFloor', [FloorController::class, 'updateFloor']);

//=========================================Suite=============================================
Route::get('suites', [SuiteController::class, 'index']);
Route::post('addSuite', [SuiteController::class, 'addSuite']);
Route::delete('deleteSuite', [SuiteController::class, 'deleteSuite']);
Route::post('updateSuite', [SuiteController::class, 'updateSuite']);

//=========================================Room=============================================
Route::get('rooms', [RoomController::class, 'index']);
Route::post('addRoom', [RoomController::class, 'addRoom']);
Route::delete('deleteRoom', [RoomController::class, 'deleteRoom']);
Route::post('updateRoom', [RoomController::class, 'updateRoom']);
Route::post('addMultiRoom', [RoomController::class, 'addMultiRoom']);

//=====================================RoomTypesController====================================================
Route::post('/addRoomType', [RoomTypesController::class, 'addRoomType']);
Route::get('/getRoomType', [RoomTypesController::class, 'getRoomType']);
Route::post('/updateRoomType', [RoomTypesController::class, 'updateRoomType']);
Route::post('/deleteRoomType', [RoomTypesController::class, 'deleteRoomType']);
Route::get('/getRoomtypePricing', [RoomTypesController::class, 'getRoomtypePricing']);
Route::post('/addRoomtypePricing', [RoomTypesController::class, 'addRoomtypePricing']);
Route::post('/updateRoomtypePricing', [RoomTypesController::class, 'updateRoomtypePricing']);
Route::post('/deleteRoomtypePricing', [RoomTypesController::class, 'deleteRoomtypePricing']);

//=====================================Facilities=================================================
Route::get('/getFacilities', [FacilitiesController::class, 'index']);
Route::post('/addFacilities', [FacilitiesController::class, 'store']);
Route::post('/updateFacilities', [FacilitiesController::class, 'update']);
Route::post('/deleteFacilities', [FacilitiesController::class, 'destroy']);
Route::get('/getFacilitiesByBuilding', [FacilitiesController::class, 'getByBuilding']);
Route::get('/getFacilitiesByFloor', [FacilitiesController::class, 'getByFloor']);

//=====================================FacilitiesType==============================================
Route::get('/getFacilitiesType', [FacilitiesController::class, 'typeIndex']);
Route::post('/addFacilitiesType', [FacilitiesController::class, 'typeStore']);
Route::post('/updateFacilitiesType', [FacilitiesController::class, 'typeUpdate']);
Route::delete('/deleteFacilitiesType', [FacilitiesController::class, 'typeDestroy']);
Route::get('/getFacilitiesTypeById', [FacilitiesController::class, 'typeShow']);

//=====================================Features=================================================
Route::get('/getFeature', [FeaturesController::class, 'index']);
Route::post('/addFeature', [FeaturesController::class, 'store']);
Route::post('/updateFeature', [FeaturesController::class, 'update']);
Route::delete('/deleteFeature', [FeaturesController::class, 'destroy']);
//=====================================RoomFeature================================================
Route::get('/getRoomFeature', [FeaturesController::class, 'roomIndex']);
Route::post('/addRoomFeature', [FeaturesController::class, 'roomStore']);
Route::post('/updateRoomFeature', [FeaturesController::class, 'roomUpdate']);
Route::delete('/deleteRoomFeature', [FeaturesController::class, 'roomDestroy']);
Route::get('/getRoomFeatureByRoom', [FeaturesController::class, 'getByRoom']);

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
Route::get('/getStayReasons', [StayReasonsController::class, 'index']);
Route::post('/addStayReason', [StayReasonsController::class, 'store']);
Route::post('/updateStayReason', [StayReasonsController::class, 'update']);
Route::post('/deleteStayReason', [StayReasonsController::class, 'destroy']);
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
//=====================================PeakDaysCheck===============================================
Route::post('/updatePeakDaysCheck', [Users::class, 'updatePeakDaysCheck']);
Route::get('/seedWeekDays', [Users::class, 'seedWeekDays']);
//=====================================PeakMonthsCheck===============================================
Route::post('/updatePeakMonthsCheck', [Users::class, 'updatePeakMonthsCheck']);
Route::get('/seedMonths', [Users::class, 'seedMonths']);
