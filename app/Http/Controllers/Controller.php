<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function fetchFromOdds($url, $query)
    {
        $api_key = env("ODD_API_KEY", '');
        $api_url = env("ODD_API_URL", '');

        $url = $api_url.'/v4'.$url.'/?'.$query.'&apiKey='.$api_key;

        $response = Http::get($url);

        Log::channel('odds')->info($url, json_decode($response->body(), true));

        if ($response->successful()) {
            return $response->body();
        }

        return null;
    }
}
