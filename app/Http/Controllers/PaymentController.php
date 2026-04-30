<?php

namespace STS\Http\Controllers;

use Illuminate\Http\Request;
use STS\Contracts\WebpayNormalFlowClient;
use STS\Models\Passenger;
use STS\Services\Logic\TripsManager;

// [TODO] Transbank

class PaymentController extends Controller
{
    public function __construct(private readonly WebpayNormalFlowClient $webpay) {}

    public function transbank(Request $request, TripsManager $tripLogic)
    {
        if (! $request->has('tp_id')) {
            return response('No transaction id', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $tpId = $request->get('tp_id');
        $passengerRequest = $tripLogic->getTripByTripPassenger($tpId);
        if (! $passengerRequest) {
            return response('', 200);
        }

        // Identificador que será retornado en el callback de resultado:
        $sessionId = $passengerRequest->id;
        // Identificador único de orden de compra:
        $buyOrder = $tpId;
        $amount = $passengerRequest->trip->seat_price_cents;
        $returnUrl = $baseUrl.'/transbank-respuesta';
        $finalUrl = $baseUrl.'/transbank-final';
        $initResult = $this->webpay->initTransaction(intval($amount), (string) $buyOrder, (string) $sessionId, $returnUrl, $finalUrl);
        $formAction = $initResult->url;
        $tokenWs = $initResult->token;

        return view('transbank', [
            'formAction' => $formAction,
            'tokenWs' => $tokenWs,
        ]);
    }

    public function transbankResponse(Request $request, TripsManager $tripLogic)
    {
        $transactionResultOutput = $this->webpay->getTransactionResult($request->input('token_ws'));
        if (! is_object($transactionResultOutput)) {
            return view('transbank-final', [
                'message' => 'Transbank ouput empty.',
            ]);
        }
        $output = $transactionResultOutput->detailOutput;
        $passengerRequest = $tripLogic->getTripByTripPassenger($transactionResultOutput->buyOrder);
        if ($passengerRequest) {
            if ($output->responseCode == 0) {
                // Transaccion exitosa, puedes procesar el resultado con el contenido de
                // las variables result y output.
                $passengerRequest->request_state = Passenger::STATE_ACCEPTED;
                $passengerRequest->payment_status = 'ok';
                $passengerRequest->payment_info = json_encode($transactionResultOutput);
                $passengerRequest->save();

                return view('transbank', [
                    'formAction' => $transactionResultOutput->urlRedirection,
                    'tokenWs' => $request->input('token_ws'),
                ]);
            } else {
                $responseMessage = '';
                /*
                    -1 = Rechazo de transacción.
                    -2 = Transacción debe reintentarse.
                    -3 = Error en transacción.
                    -4 = Rechazo de transacción.
                    -5 = Rechazo por error de tasa.
                    -6 = Excede cupo máximo mensual.
                    -7 = Excede límite diario por transacción.
                    -8 = Rubro no autorizado.
                */
                switch ($output->responseCode) {
                    case -1:
                        $responseMessage = 'Rechazo de transacción.';
                        break;
                    case -2:
                        $responseMessage = 'Transacción debe reintentarse.';
                        break;
                    case -3:
                        $responseMessage = 'Error en transacción.';
                        break;
                    case -4:
                        $responseMessage = 'Rechazo de transacción.';
                        break;
                    case -5:
                        $responseMessage = 'Rechazo por error de tasa.';
                        break;
                    case -6:
                        $responseMessage = 'Excede cupo máximo mensual.';
                        break;
                    case -7:
                        $responseMessage = 'Excede límite diario por transacción.';
                        break;
                    case -8:
                        $responseMessage = 'Excede límite diario por transacción.';
                        break;
                    default:
                        // code...
                        break;
                }
                $passengerRequest->payment_status = 'error:'.$output->responseCode.':'.$responseMessage;
                $passengerRequest->payment_info = json_encode($transactionResultOutput);
                $passengerRequest->save();

                return view('transbank-final', [
                    'message' => 'Ocurrió un error al procesar la operación.',
                ]);
            }
        } else {
            return view('transbank-final', [
                'message' => 'Operación no encontrada',
            ]);
        }
    }

    public function transbankFinal(Request $request)
    {
        return view('transbank-final', [
            'message' => 'Transacción realizada con éxito.',
        ]);
    }
}
