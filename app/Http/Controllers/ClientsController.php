<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Http\Requests\Client\AddClientRequest;
use App\Http\Requests\Client\GetClientByRequest;

class ClientsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = \returnPerPage();
            $search = $request->search;
            $excludeAssigned = $request->exclude_assigned;

            $query = Client::latest();

            if ($excludeAssigned) {
                $query->whereDoesntHave('classifications');
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                      ->orWhere('last_name', 'like', "%$search%")
                      ->orWhere('mobile', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('IdNumber', 'like', "%$search%")
                      ->orWhere('id', 'like', "%$search%");
                });
            }

            $clients = $query->paginate($perPage);
            return \Pagination($clients);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

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

    public function update(AddClientRequest $request)
    {
        try {
            $client = Client::find($request->id);

            if (!$client) {
                return \Failed('Client not found');
            }

            $client->update([
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

            return \SuccessData('Client updated successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return \Failed('Client not found');
            }

            return \SuccessData('Client retrieved successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function getBy(GetClientByRequest $request)
    {
        try {
            $client = Client::where('mobile', $request->mobile)
                ->orWhere('email', $request->email)
                ->orWhere('IdNumber', $request->IdNumber)
                ->first();

            if (!$client) {
                return \Failed('Client not found');
            }

            return \SuccessData('Client retrieved successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function getClientById($id)
    {
        try {
            $client = Client::find($id);

            if (!$client) {
                return \Failed('Client not found');
            }

            return \SuccessData('Client retrieved successfully', $client);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
