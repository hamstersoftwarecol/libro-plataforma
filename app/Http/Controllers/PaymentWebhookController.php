<?php

namespace App\Http\Controllers;

use App\Services\Payments\InvalidWebhookException;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    /**
     * Receive gateway notifications (HU-018, HU-030).
     */
    public function __invoke(Request $request, string $gateway, WebhookProcessor $processor): JsonResponse
    {
        try {
            $result = $processor->handle($gateway, $request);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Pasarela desconocida.'], 404);
        } catch (InvalidWebhookException $exception) {
            report($exception);

            return response()->json(['message' => 'Webhook inválido.'], 400);
        }

        return response()->json(['status' => $result]);
    }
}
