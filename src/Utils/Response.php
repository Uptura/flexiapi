<?php

namespace FlexiAPI\Utils;

class Response
{
    public static function json(bool $success, string $message = '', $data = [], int $httpCode = 200, array $pagination = null)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        $out = [
            'status' => $success,
            'message' => $message,
            'data' => $data,
            'error' => $success ? null : $message
        ];
        if ($pagination) $out['pagination'] = $pagination;
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
