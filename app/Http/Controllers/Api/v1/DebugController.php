<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Helpers\ClientLogSanitizer;
use STS\Http\Controllers\Controller;

class DebugController extends Controller
{
    public function log(Request $request)
    {
        $log = ClientLogSanitizer::sanitizeString($request->input('log'));
        $source = ClientLogSanitizer::sanitizeString($request->input('source'), 200);
        $context = ClientLogSanitizer::sanitizeContext($request->input('context'));

        if ($log === null && $context === null) {
            return response()->json(['data' => 'ok']);
        }

        $message = 'ERROR IN APP';
        if ($source !== null) {
            $message .= ' ['.$source.']';
        }
        if ($log !== null) {
            $message .= ': '.$log;
        }

        $logContext = array_filter([
            'context' => $context,
        ]);

        \Log::info($message, $logContext);

        return response()->json(['data' => 'ok']);
    }
}
