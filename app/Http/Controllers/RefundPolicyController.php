<?php

namespace App\Http\Controllers;

use App\Exceptions\RefundNotAllowedException;
use App\Http\Requests\RefundPolicy\DestroyRefundPolicyRequest;
use App\Http\Requests\RefundPolicy\PreviewRefundPolicyRequest;
use App\Http\Requests\RefundPolicy\StoreRefundPolicyRequest;
use App\Http\Requests\RefundPolicy\UpdateRefundPolicyRequest;
use App\Models\RefundPolicy;
use App\Models\Reservation;
use App\Services\RefundPolicyService;

class RefundPolicyController extends Controller
{
    public function __construct(private RefundPolicyService $refundPolicyService)
    {
    }

    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $policies = RefundPolicy::query()->orderBy('rent_type')->orderBy('days_threshold')->paginate($perPage);

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

    /**
     * Preview refund amount for a reservation before processing.
     */
    public function preview(PreviewRefundPolicyRequest $request)
    {
        try {
            $reservation = Reservation::with(['payments', 'client'])->findOrFail($request->reservation_id);
            $result = $this->refundPolicyService->preview($reservation);

            return \SuccessData('Refund preview', [
                'policy' => $result['policy'],
                'refund_amount' => $result['refund_amount'],
                'breakdown' => $result['breakdown'],
            ]);
        } catch (RefundNotAllowedException $e) {
            $payload = ['message' => $e->getMessage()];
            if (str_contains($e->getMessage(), 'No refund policy')) {
                $reservation = Reservation::with('payments')->find($request->reservation_id);
                if ($reservation) {
                    $payload['context'] = $this->refundPolicyService->buildContext($reservation);
                    $payload['hint'] = $this->refundPolicyHint($reservation, $payload['context']);
                }
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
                'data' => $payload,
            ], 422);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function refundPolicyHint(Reservation $reservation, array $context): string
    {
        if ($context['during_stay'] ?? false) {
            $days = (int) ($context['days_since_start'] ?? 0);
            $pay = (int) ($context['payment_status'] ?? 0);
            $payLabel = $pay === 2 ? 'full payment' : ($pay === 1 ? 'partial payment' : 'no payment');

            return "Guest is in-house (day {$days} of stay), {$payLabel}. "
                . 'Add a policy with timing "During stay", days threshold ≤ ' . $days
                . ', and payment status "Full payment" or "Any".';
        }

        $days = (int) ($context['days_until_start'] ?? 0);

        return "Before check-in ({$days} days until arrival). "
            . 'Add a policy with timing "Before check-in" and days threshold ≤ ' . $days . '.';
    }
}
