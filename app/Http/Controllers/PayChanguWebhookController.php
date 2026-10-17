<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ConfirmPayChanguPaymentJob;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/paychangu
 *
 * The body is only used to find which charge changed. The charge is always
 * re-verified with PayChangu before access is granted.
 */
class PayChanguWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $chargeId = $request->input('charge_id')
            ?? $request->input('data.charge_id')
            ?? $request->input('reference')
            ?? $request->input('tx_ref');

        $payment = is_string($chargeId)
            ? Payment::where('paychangu_tx_id', $chargeId)->first()
            : null;

        // Unknown charges are acknowledged so PayChangu does not keep retrying.
        if ($payment && $payment->status === 'pending') {
            ConfirmPayChanguPaymentJob::dispatch($payment->id);
        }

        return response()->json(['ok' => true]);
    }
}
