<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Http\Requests\Client\AddClientRequest;
use App\Http\Requests\Client\GetClientByRequest;
use Exception;

class ClientsController extends Controller
{
    /**
     * Get all clients with pagination
     */
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $clients = Client::paginate($perPage);
            return \Pagination($clients);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add a new client
     */
    public function store(AddClientRequest $request)
    {
        try {
            $client = Client::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email ?? null,
                'international_code' => $request->international_code ?? null,
                'mobile' => $request->mobile,
                'IdType' => $request->IdType ?? null,
                'IdNumber' => $request->IdNumber ?? null,
                'birth_date' => $request->birth_date ?? null,
                'gender' => $request->gender ?? null,
                'guest_type' => $request->guest_type ?? null,
                'nationality' => $request->nationality ?? null,
            ]);

            return \SuccessData('Client added successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Get client by mobile or email
     */
    public function getBy(GetClientByRequest $request)
    {
        try {
            $client = Client::where('mobile', $request->mobile)
                ->orWhere('email', $request->email)
                ->first();

            if (!$client) {
                return \Failed('Client not found');
            }

            return \SuccessData('Client retrieved successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
