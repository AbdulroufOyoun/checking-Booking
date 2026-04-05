<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuildingsController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\SuiteController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypesController;
use App\Http\Controllers\FacilitiesController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\StayReasonsController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\DiscountsController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\JobTitlesController;
use App\Http\Controllers\PeakDaysController;
use App\Http\Controllers\PeakMonthsController;
use App\Http\Controllers\DepartmentsController;
use App\Http\Controllers\PenaltiesController;
use App\Http\Controllers\ReservationSourcesController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\GuestClassificationsController;
use App\Http\Controllers\GuestFeaturesController;
use App\Http\Controllers\GuestClassificationFeaturesController;
use App\Http\Controllers\ClientClassificationsController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\EarningController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\RefundPolicyController;

Route::post('login', [UsersController::class, 'login']);

// Route::middleware(['can_do:manage_settings'])->group(function () {
// });
Route::get('login_error',[UsersController::class , 'loginError'])->name('login');

Route::group(['middleware' => ['auth:api']], function () {
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
Route::post('/addRoomtypePricingPlan', [RoomTypesController::class, 'addRoomtypePricingPlan']);
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
//******//
//=====================================StayReason================================================
Route::get('/getStayReasons', [StayReasonsController::class, 'index']);
Route::post('/addStayReason', [StayReasonsController::class, 'store']);
Route::post('/updateStayReason', [StayReasonsController::class, 'update']);
Route::delete('/deleteStayReason', [StayReasonsController::class, 'destroy']);

//=====================================Permissions=================================================
Route::get('/getPermissions', [PermissionsController::class, 'index']);
Route::post('/updatePermission', [PermissionsController::class, 'update']);
Route::delete('/deletePermission', [PermissionsController::class, 'destroy']);

Route::post('/addUserPermission', [PermissionsController::class, 'addUserPermission']);

//=====================================Discounts=================================================
Route::get('getDiscounts', [DiscountsController::class, 'index']);
Route::post('addDiscount', [DiscountsController::class, 'store']);
Route::post('updateDiscount', [DiscountsController::class, 'update']);
Route::delete('deleteDiscount', [DiscountsController::class, 'destroy']);

//=====================================JobTitle===================================================
Route::get('/getJobTitle', [JobTitlesController::class, 'index']);
Route::get('/getJobTitlesByDepartment', [JobTitlesController::class, 'getByDepartment']);
Route::post('/addJobTitle', [JobTitlesController::class, 'store']);
Route::post('/updateJobTitle', [JobTitlesController::class, 'update']);
Route::delete('/deleteJobTitle', [JobTitlesController::class, 'destroy']);

//=====================================Tax========================================================
Route::get('/getTax', [TaxesController::class, 'index']);
Route::post('/addTax', [TaxesController::class, 'store']);
Route::post('/updateTax', [TaxesController::class, 'update']);
Route::delete('/deleteTax', [TaxesController::class, 'destroy']);

//=====================================PeakDaysCheck===============================================
Route::get('/getPeakDays', [PeakDaysController::class, 'index']);
Route::post('/updatePeakDaysCheck', [PeakDaysController::class, 'updateCheck']);
//=====================================PeakMonthsCheck===============================================
Route::get('/getPeakMonths', [PeakMonthsController::class, 'index']);
Route::post('/updatePeakMonthsCheck', [PeakMonthsController::class, 'updateCheck']);

//=====================================Penaltie===================================================
Route::get('/getPenalties', [PenaltiesController::class, 'index']);
Route::post('/addPenaltie', [PenaltiesController::class, 'store']);
Route::delete('/deletePenaltie', [PenaltiesController::class, 'destroy']);
//=====================================ReservationPenalties=======================================
Route::post('/addReservationPenalty', [PenaltiesController::class, 'addReservationPenalty']);
Route::get('/getPenaltiesByReservationId', [PenaltiesController::class, 'getByReservation']);
//=====================================ReservationSources=========================================
Route::get('/getReservationSource', [ReservationSourcesController::class, 'index']);
Route::post('/addReservationSource', [ReservationSourcesController::class, 'store']);
Route::post('/updateReservationSource', [ReservationSourcesController::class, 'update']);
Route::delete('/deleteReservationSource', [ReservationSourcesController::class, 'destroy']);
//=====================================Clients====================================================
Route::post('/addClient', [ClientsController::class, 'store']);
Route::get('/getClientBy', [ClientsController::class, 'getBy']);
//=====================================Department=================================================
Route::get('/getDepartment', [DepartmentsController::class, 'index']);
Route::post('/addDepartment', [DepartmentsController::class, 'store']);
Route::get('/getDepartmentById', [DepartmentsController::class, 'show']);
Route::post('/updateDepartment', [DepartmentsController::class, 'update']);
Route::delete('/deleteDepartment', [DepartmentsController::class, 'destroy']);
//=====================================Users======================================================
Route::post('/addUser', [UsersController::class, 'store']);
Route::post('/updateUser', [UsersController::class, 'update']);
Route::get('/inActiveUser', [UsersController::class, 'inActive']);
Route::get('/getInfoUsers', [UsersController::class, 'index']);
//=====================================GuestClassification=======================================
Route::get('/getGuestClassification', [GuestClassificationsController::class, 'index']);
Route::post('addGuestClassification', [GuestClassificationsController::class, 'store']);
Route::post('updateGuestClassification', [GuestClassificationsController::class, 'update']);
Route::delete('deleteGuestClassification', [GuestClassificationsController::class, 'destroy']);

//=====================================GuestFeature==============================================
Route::get('getGuestFeature', [GuestFeaturesController::class, 'index']);
Route::post('addGuestFeature', [GuestFeaturesController::class, 'store']);
Route::post('updateGuestFeature', [GuestFeaturesController::class, 'update']);
Route::delete('deleteGuestFeature', [GuestFeaturesController::class, 'destroy']);

//=====================================GuestClassificationFeature==================================
Route::get('getGuestClassificationFeature', [GuestClassificationFeaturesController::class, 'index']);
Route::get('getGuestClassificationFeatureByClassification', [GuestClassificationFeaturesController::class, 'getFeaturesByClassification']);
Route::post('addGuestClassificationFeature', [GuestClassificationFeaturesController::class, 'store']);
Route::delete('deleteGuestClassificationFeature', [GuestClassificationFeaturesController::class, 'destroy']);

//=====================================ClientClassification=======================
Route::get('getAllClientsWithClassification', [ClientClassificationsController::class, 'getAllClientsWithClassification']);
Route::post('assignClientClassification', [ClientClassificationsController::class, 'assignClassification']);
Route::get('getClientClassification', [ClientClassificationsController::class, 'getClientClassification']);
Route::delete('removeClientClassification', [ClientClassificationsController::class, 'removeClassification']);


//=========================================Reservation=============================================
Route::post('makeReservation', [ReservationController::class, 'makeReservation']);
Route::get('checkReservation', [ReservationController::class, 'checkReservation']);
Route::post('getRoomPrice', [ReservationController::class, 'getRoomPrice']);

Route::get('all-earnings', [EarningController::class, 'allEarnings']);
Route::get('earnings-list', [EarningController::class, 'earningsList']);
Route::get('earnings-summary', [EarningController::class, 'earningsSummary']);
Route::get('payments', [EarningController::class, 'payments']);
Route::get('refunds', [EarningController::class, 'refunds']);
Route::post('refund', [ReservationController::class, 'refund']);

// Revenue APIs
Route::get('revenue/total', [RevenueController::class, 'getTotalRevenue']);
Route::get('revenue/room/{entity_id}', [RevenueController::class, 'getRoomRevenue']);
Route::get('revenue/suite/{entity_id}', [RevenueController::class, 'getSuiteRevenue']);
Route::get('revenue/floor/{entity_id}', [RevenueController::class, 'getFloorRevenue']);
Route::get('revenue/building/{entity_id}', [RevenueController::class, 'getBuildingRevenue']);
Route::get('revenue/roomtype/{entity_id}', [RevenueController::class, 'getRoomTypeRevenue']);

Route::apiResource('refund-policies', RefundPolicyController::class);
Route::delete('refund-policies', [RefundPolicyController::class, 'destroy']);

    });




