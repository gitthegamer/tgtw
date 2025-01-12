<?php

namespace App\Modules;

use App\Models\ProviderLog;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class _FunhouseController
{

    protected $secureToken;
    protected $secret;
    protected $external_player_id;
    protected $currency;
    protected $provider_id;
    protected $player_id;
    protected $external_transaction_id;
    protected $amount;
    protected $sign;
    protected $game_id;
    protected $language;
    protected $game_mode;
    protected $secureKey;
    protected $timepoint_start;
    protected $timepoint_end;
    protected $page_number;


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->sign = $this->encypt_to_token($function);
    }

    public function make_params($function)
    {
        if ($function == "player/add") {
            return [
                'secureToken' => $this->secureToken,
                'external_player_id' => $this->external_player_id,
                'currency' => $this->currency,
                'hash' => $this->sign
            ];
        }

        if ($function == "games") {
            return [
                'secureToken' => $this->secureToken,
                'hash' => $this->sign
            ];
        }

        if ($function == "player/balance") {
            return [
                'secureToken' => $this->secureToken,
                'external_player_id' => $this->external_player_id,
                'hash' => $this->sign
            ];
        }

        if ($function == "balance/transfer") {
            return [
                'secureToken' => $this->secureToken,
                'external_player_id' => $this->external_player_id,
                'external_transaction_id' => $this->external_transaction_id,
                'amount' => $this->amount,
                'hash' => $this->sign
            ];
        }

        if ($function == "startGame") {
            return [
                'secureToken' => $this->secureToken,
                'external_player_id' => $this->external_player_id,
                'game_id' => $this->game_id,
                'language' => $this->language,
                'game_mode' => $this->game_mode,
                'hash' => $this->sign,
                'currency' => $this->currency
            ];
        }

        if ($function == "balance/transferStatus") {
            return [
                'secureToken' => $this->secureToken,
                'external_transaction_id' => $this->external_transaction_id,
                'hash' => $this->sign
            ];
        }

        if ($function == 'game/history') {
            return [
                'secureToken' => $this->secureToken,
                'secureKey' => $this->secret,
                'timepoint_start' => $this->timepoint_start,
                'timepoint_end' => $this->timepoint_end,
                'page_number' => $this->page_number,
                'hash' => $this->sign
            ];
        }
    }


    public function get_url($function)
    {
        return env('FH_LINK_LIVE') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _FunhouseController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();

        $log = 'funhouse_api_records';
        if ($function == "game/history") {
            $log = 'funhouse_api_ticket_records';
        }
        if ($function == "balance/transfer" || $function == "balance/transferStatus") {
            $log = 'funhouse_api_transfer_records';
        }
        if ($function == "player/balance") {
            $log = 'funhouse_api_balance_records';
        }

        $productName = 'Funhouse';
        $this->create_param($function, $params);


        try {
            $client = new Client(["base_uri" => $this->get_url($function), 'verify' => false]);
            $response = $client->request("POST", "", $request = [
                'http_errors' => false,
                'headers' => ['Content-Type' => 'application/json'],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {



                },
                'body' => json_encode($this->make_params($function)),
            ]);

            $status_code = $response->getStatusCode();
            $response = @json_decode($response->getBody(), true);


        } catch (\Exception $e) {
   
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => [],
            ];
        }

        if (!$response) {

            return [
                'status' => false,
                'status_message' => $response['description'] ?? "Unknown ERROR",
                'data' => null,
            ];
        }

     

        return [
            'status' => (isset($response['error']) && empty($response['error'])) ? true : false,
            'status_message' => $response['description'] ?? "Unknown Error",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return md5($this->encypt_string($function));
    }

    public function encypt_string($function)
    {
        if ($function == "player/add") {
            return 'currency=' . $this->currency . '&external_player_id=' . $this->external_player_id . '&secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }
        if ($function == "player/balance") {
            return 'external_player_id=' . $this->external_player_id . '&secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }
        if ($function == "balance/transfer") {
            return 'amount=' . $this->amount . '&external_player_id=' . $this->external_player_id . '&external_transaction_id=' . $this->external_transaction_id . '&secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }
        if ($function == "startGame") {
            return 'currency=' . $this->currency . '&external_player_id=' . $this->external_player_id . '&game_id=' . $this->game_id . '&game_mode=' . $this->game_mode . '&language=' . $this->language . '&secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }
        if ($function == "games") {
            return 'secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }
        if ($function == "balance/transferStatus") {
            return 'external_transaction_id=' . $this->external_transaction_id . '&secureToken=' . $this->secureToken . '&secret_key=' . $this->secret;
        }

        if ($function == "game/history") {
            return 'page_number=' . $this->page_number . '&secureKey=' . $this->secret . '&secureToken=' . $this->secureToken . '&timepoint_end=' . $this->timepoint_end . '&timepoint_start=' . $this->timepoint_start . '&secret_key=' . $this->secret;
        }
    }
}