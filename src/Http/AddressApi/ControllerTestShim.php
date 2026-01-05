<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Http\AddressApi;

final class ControllerTestShim extends Controller
{
    public function validateTest(array $payload): string
    {
        ob_start();
        $this->validateVia($payload);
        return ob_get_clean();
    }

    public function parseTest(array $payload): string
    {
        ob_start();
        $this->parseVia($payload);
        return ob_get_clean();
    }

    private function validateVia(array $in): void
    {
        // copy of validate() but taking $in instead of php://input
        $line1 = (string)($in['line1'] ?? '');
        $line2 = isset($in['line2']) ? (string)$in['line2'] : null;
        $city = (string)($in['city'] ?? '');
        $region = (string)($in['region'] ?? '');
        $postal = (string)($in['postal'] ?? '');
        $country = (string)($in['country'] ?? 'US');
        $norm = (new \App\Service\Normalize\Normalizer())->normalize($line1, $line2, $city, $region, $postal, $country);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'normalized'=>[
            'line1'=>(string)$norm['line1'],
            'line2'=>$norm['line2'] !== null ? (string)$norm['line2'] : null,
            'city'=>(string)$norm['city'],
            'region'=>(string)$norm['region'],
            'postal'=>(string)$norm['postal'],
            'country'=>$norm['country']->value(),
            'digest'=>$norm['digest'],
        ]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    private function parseVia(array $in): void
    {
        $text = (string)($in['text'] ?? '');
        $hint = isset($in['countryHint']) ? (string)$in['countryHint'] : null;
        $parsed = (new \App\Service\Parse\Parser())->parse($text, $hint);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'normalized'=>[
            'line1'=>(string)$parsed['line1'],
            'line2'=>$parsed['line2'] !== null ? (string)$parsed['line2'] : null,
            'city'=>(string)$parsed['city'],
            'region'=>(string)$parsed['region'],
            'postal'=>(string)$parsed['postal'],
            'country'=>$parsed['country']->value(),
            'digest'=>$parsed['digest'],
        ]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
