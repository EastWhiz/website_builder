<?php

if (!function_exists('simpleValidate')) {
    function simpleValidate($validator)
    {
        $error = $validator->errors()->first();
        return response()->json([
            'success' => false,
            'message' => $error,
        ], 422);
    }
}

if (!function_exists('sendResponse')) {
    function sendResponse($success, $message = '', $data = null, $data2 = null, $data3 = null, $code = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'data2' => $data2,
            'data3' => $data3,
        ], $code);
    }
}
