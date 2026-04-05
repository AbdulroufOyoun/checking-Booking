<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundPolicy\DestroyRefundPolicyRequest;
use App\Http\Requests\RefundPolicy\StoreRefundPolicyRequest;
use App\Http\Requests\RefundPolicy\UpdateRefundPolicyRequest;


use App\Models\RefundPolicy;

class RefundPolicyController extends Controller
{
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $query = RefundPolicy::query();

            $policies = $query->paginate($perPage);
            return \Pagination($policies);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function store(StoreRefundPolicyRequest $request)
    {
        try {
            $policy = RefundPolicy::create($request->validated());
            return \SuccessData('Refund policy created', $policy);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function show(RefundPolicy $policy)
    {
        return \SuccessData('Refund policy found', $policy);
    }

    public function update(UpdateRefundPolicyRequest $request, RefundPolicy $policy)
    {
        try {
            $policy->update($request->validated());
            return \SuccessData('Refund policy updated', $policy->fresh());
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function destroy(DestroyRefundPolicyRequest $request)
    {
        $policy = RefundPolicy::findOrFail($request->id);
        $policy->delete();
        return \Success('Refund policy deleted');
    }
}

