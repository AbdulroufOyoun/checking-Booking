<?php

namespace App\Http\Controllers;

use App\Models\Client_Classifications;
use App\Models\Guest_classification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Http\Requests\ClientClassification\AssignClassificationRequest;
use App\Http\Requests\ClientClassification\GetClientClassificationRequest;
use App\Http\Requests\ClientClassification\RemoveClassificationRequest;
use App\Http\Requests\ClientClassification\SearchClientWithClassificationRequest;

class ClientClassificationsController extends Controller
{

    public function assignClassification(AssignClassificationRequest $request)
    {
        try {
            $client = DB::connection('mysql2')
                ->table('clients')
                ->where('id', $request->client_id)
                ->first();

            if (!$client) {
                return \Failed('Client not found in database');
            }

            $classification = Guest_classification::find($request->classifications_id);
            if (!$classification) {
                return \Failed('Classification not found');
            }

            Client_Classifications::where('client_id', $request->client_id)->delete();


            $clientClassification = Client_Classifications::create([
                'client_id' => $request->client_id,
                'classifications_id' => $request->classifications_id,
            ]);

            $data = [
                'client_id' => $clientClassification->client_id,
                'classification_id' => $clientClassification->classifications_id,
                'classification_name' => $classification->name_ar,
            ];

            return \SuccessData('Classification assigned to client successfully', $data);
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }


    public function getClientClassification(GetClientClassificationRequest $request)
    {
        try {
            $client = DB::connection('mysql2')
                ->table('clients')
                ->where('id', $request->client_id)
                ->first();

            if (!$client) {
                return \Failed('Client not found');
            }

            $clientClassification = Client_Classifications::where('client_id', $request->client_id)
                ->with('guestClassification')
                ->first();

            $data = [
                'client' => $client,
                'classification' => $clientClassification ? $clientClassification->guestClassification : null,
            ];

            return \SuccessData('Client classification retrieved successfully', $data);
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }


    public function removeClassification(RemoveClassificationRequest $request)
    {
        try {
            $deleted = Client_Classifications::where('client_id', $request->client_id)->delete();

            if ($deleted) {
                return \Success('Classification removed from client successfully');
            }

            return \Failed('No classification found for this client');
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function getAllClientsWithClassification()
    {
        try {
    $classificationsData = Client_Classifications::with('guestClassification')->get();

    $clientIds = $classificationsData->pluck('client_id')->unique()->filter()->toArray();

    $clients = DB::connection('mysql2')
        ->table('clients')
        ->whereIn('id', $clientIds)
        ->get()
        ->keyBy('id');

    $classificationsData->transform(function ($item) use ($clients) {
        $item->client_details = $clients->get($item->client_id) ?? null;
        return $item;
    });
            return \SuccessData('All clients with classifications fetched successfully', $classificationsData);

        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
