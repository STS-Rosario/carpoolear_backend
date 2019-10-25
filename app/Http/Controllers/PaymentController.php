<?php

namespace STS\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use STS\Contracts\Logic\Trip as TripLogic;

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;
use Illuminate\Support\Facades\Redirect;

class PaymentController extends Controller
{
    public function transbank (Request $request, TripLogic $tripLogic)
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        if ($request->has('tp_id')) {
            $tpId = $request->get('tp_id');
            $passengerRequest = $tripLogic->getTripByTripPassenger($tpId);
            if ($passengerRequest) {
                // Identificador que será retornado en el callback de resultado:
                $sessionId = $passengerRequest->id;
                // Identificador único de orden de compra:
                $buyOrder = $tpId;
                // TODO como calculo precio?
                $amount = floatval($passengerRequest->price);
                $returnUrl = $baseUrl . '/transbank-respuesta';
                $finalUrl = $baseUrl . '/transbank-final';
                // Transbank work
                $transaction = (new Webpay(Configuration::forTestingWebpayPlusNormal()))->getNormalTransaction();
                $initResult = $transaction->initTransaction($amount, $buyOrder, $sessionId, $returnUrl, $finalUrl);
                $formAction = $initResult->url;
                $tokenWs = $initResult->token;
                // var_dump($initResult);die;
                return view('transbank', [
                    'formAction' => $formAction,
                    'tokenWs' => $tokenWs
                ]);
            }
        } else {
            echo 'No transaction id';
        }
    }

    public function transbankResponse (Request $request, TripLogic $tripLogic) {
        $transaction = (new Webpay(Configuration::forTestingWebpayPlusNormal()))->getNormalTransaction();
        $transactionResultOutput = $transaction->getTransactionResult($request->input("token_ws"));
        /* object(Transbank\Webpay\transactionResultOutput)#419 (8) { 
            ["accountingDate"]=> string(4) "0904" 
            ["buyOrder"]=> string(9) "119027553" 
            ["cardDetail"]=> object(Transbank\Webpay\cardDetail)#425 (2) { 
                ["cardNumber"]=> string(4) "6623" 
                ["cardExpirationDate"]=> NULL 
            } 
            ["detailOutput"]=> object(Transbank\Webpay\wsTransactionDetailOutput)#421 (7) { 
                ["authorizationCode"]=> string(4) "1213" 
                ["paymentTypeCode"]=> string(2) "VN" 
                ["responseCode"]=> int(0) 
                ["sharesNumber"]=> int(0) 
                ["amount"]=> string(4) "1000" 
                ["commerceCode"]=> string(12) "597020000540" 
                ["buyOrder"]=> string(9) "119027553" 
            } 
            ["sessionId"]=> string(15) "mi-id-de-sesion" 
            ["transactionDate"]=> string(29) "2019-09-04T12:04:35.719-04:00" 
            ["urlRedirection"]=> string(57) "https://webpay3gint.transbank.cl/webpayserver/voucher.cgi" 
            ["VCI"]=> string(3) "TSY" 
        }  */
        $output = $transactionResultOutput->detailOutput;
        $passengerRequest = $tripLogic->getTripByTripPassenger($transactionResultOutput->buyOrder);
        if ($passengerRequest) {
            if ($output->responseCode == 0) {
                // Transaccion exitosa, puedes procesar el resultado con el contenido de
                // las variables result y output.
                $passengerRequest->payment_status = 'ok';
                $passengerRequest->payment_info = json_encode($transactionResultOutput);
                $passengerRequest->save();
                return view('transbank', [
                    'formAction' => $transactionResultOutput->urlRedirection,
                    'tokenWs' => $request->input("token_ws")
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
                        # code...
                        break;
                }
                $passengerRequest->payment_status = 'error:' . $output->responseCode . ':' . $responseMessage;
                $passengerRequest->payment_info = json_encode($transactionResultOutput);
                $passengerRequest->save();
                return view('transbank-final', [
                    'message' => 'Ocurrió un error al procesar la operación.'
                ]);
            }
        } else {
            return view('transbank-final', [
                'message' => 'Operación no encontrada'
            ]);
        }
    }
    public function transbankFinal (Request $request) {
        return view('transbank-final', [
            'message' => 'Transacción realizada con éxito.'
        ]);
    }

}
