<?php

namespace App\Http\Controllers;

use App\Models\Client_Classifications;
use App\Models\Guest_classification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ClientClassificationsController extends Controller
{
    /**
     * إضافة تصنيف جديد لعميل (يعمل بين قاعدتي البيانات)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignClassification(Request $request)
    {
        $request->validate([
            'client_id' => 'required|numeric',
            'classifications_id' => 'required|numeric|exists:mysql.guest_classifications,id',
        ]);

        try {
            // التحقق من وجود العميل في قاعدة البيانات الثانية
            $client = DB::connection('mysql2')
                ->table('clients')
                ->where('id', $request->client_id)
                ->first();

            if (!$client) {
                return response()->json([
                    'result' => 'failed',
                    'error' => 'Client not found in database'
                ], 404);
            }

            // التحقق من وجود التصنيف
            $classification = Guest_classification::find($request->classifications_id);
            if (!$classification) {
                return response()->json([
                    'result' => 'failed',
                    'error' => 'Classification not found'
                ], 404);
            }

            // حذف أي تصنيف سابق للعميل
            Client_Classifications::where('client_id', $request->client_id)->delete();

            // إضافة التصنيف الجديد
            $clientClassification = Client_Classifications::create([
                'client_id' => $request->client_id,
                'classifications_id' => $request->classifications_id,
            ]);

            return response()->json([
                'result' => 'success',
                'data' => [
                    'client_id' => $clientClassification->client_id,
                    'classification_id' => $clientClassification->classifications_id,
                    'classification_name' => $classification->name_ar,
                ],
                'message' => 'Classification assigned to client successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب تصنيفات عميل معين
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientClassification(Request $request)
    {
        $request->validate([
            'client_id' => 'required|numeric',
        ]);

        try {
            // جلب العميل من قاعدة البيانات الثانية
            $client = DB::connection('mysql2')
                ->table('clients')
                ->where('id', $request->client_id)
                ->first();

            if (!$client) {
                return response()->json([
                    'result' => 'failed',
                    'error' => 'Client not found'
                ], 404);
            }

            // جلب التصنيف من قاعدة البيانات الرئيسية
            $clientClassification = Client_Classifications::where('client_id', $request->client_id)
                ->with('guestClassification')
                ->first();

            return response()->json([
                'result' => 'success',
                'client' => $client,
                'classification' => $clientClassification ? $clientClassification->guestClassification : null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف تصنيف من عميل
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeClassification(Request $request)
    {
        $request->validate([
            'client_id' => 'required|numeric',
        ]);

        try {
            $deleted = Client_Classifications::where('client_id', $request->client_id)->delete();

            if ($deleted) {
                return response()->json([
                    'result' => 'success',
                    'message' => 'Classification removed from client successfully'
                ]);
            }

            return response()->json([
                'result' => 'failed',
                'error' => 'No classification found for this client'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'result' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب جميع العملاء مع تصنيفاتهم
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllClientsWithClassification(Request $request)
    {
        try {
            // جلب جميع العملاء من قاعدة البيانات الثانية
            $clients = DB::connection('mysql2')
                ->table('clients')
                ->get();

            // جلب جميع التصنيفات
            $classifications = Client_Classifications::with('guestClassification')->get();

            // دمج البيانات
            $result = $clients->map(function ($client) use ($classifications) {
                $classification = $classifications->where('client_id', $client->id)->first();
                return [
                    'client' => $client,
                    'classification' => $classification ? $classification->guestClassification : null,
                ];
            });

            return response()->json([
                'result' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن عميل وتصنيفه
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchClientWithClassification(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:3',
        ]);

        try {
            // البحث في قاعدة البيانات الثانية
            $clients = DB::connection('mysql2')
                ->table('clients')
                ->where('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('mobile', 'like', '%' . $request->search . '%')
                ->orWhere('IdNumber', 'like', '%' . $request->search . '%')
                ->limit(20)
                ->get();

            // جلب التصنيفات
            $classifications = Client_Classifications::with('guestClassification')->get();

            // دمج البيانات
            $result = $clients->map(function ($client) use ($classifications) {
                $classification = $classifications->where('client_id', $client->id)->first();
                return [
                    'client' => $client,
                    'classification' => $classification ? $classification->guestClassification : null,
                ];
            });

            return response()->json([
                'result' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => 'failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
