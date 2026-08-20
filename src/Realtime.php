<?php

namespace WOWSQL;

/**
 * WowSQL Realtime — postgres changes, broadcast, presence.
 * Auth: wss://project/realtime/v1/websocket?apikey=...
 *
 * Requires textalk/websocket (`composer require textalk/websocket`).
 */
class WowSQLRealtime
{
    private $projectUrl;
    private $apiKey;
    private $client;
    private $subs = [];
    private $channels = [];

    public function __construct($projectUrl, $apiKey)
    {
        $this->projectUrl = rtrim($projectUrl, '/');
        $this->apiKey = $apiKey;
    }

    public static function buildUrl($projectUrl, $apiKey)
    {
        $origin = rtrim($projectUrl, '/');
        $ws = preg_replace('/^http/', 'ws', $origin, 1);
        return $ws . '/realtime/v1/websocket?apikey=' . rawurlencode($apiKey);
    }

    public function url()
    {
        return self::buildUrl($this->projectUrl, $this->apiKey);
    }

    public function channel($name)
    {
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = new RealtimeChannel($this, $name);
        }
        return $this->channels[$name];
    }

    public function subscribe($table, callable $callback, $schema = 'public', $event = '*')
    {
        $this->subs[] = compact('schema', 'table', 'event', 'callback');
        $this->ensureConnected();
        $this->sendJson(['type' => 'subscribe', 'schema' => $schema, 'table' => $table, 'event' => $event]);
        return function () use ($schema, $table) {
            $this->sendJson(['type' => 'unsubscribe', 'schema' => $schema, 'table' => $table]);
        };
    }

    public function disconnect()
    {
        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }
    }

    public function sendJson(array $msg)
    {
        if ($this->client) {
            $this->client->send(json_encode($msg));
        }
    }

    public function ensureConnected()
    {
        if ($this->client) {
            return;
        }
        if (!class_exists(\WebSocket\Client::class)) {
            throw new \RuntimeException('Realtime requires textalk/websocket. Run: composer require textalk/websocket');
        }
        $this->client = new \WebSocket\Client($this->url());
    }

    /**
     * Blocking receive loop. Call from a worker process.
     */
    public function listen(callable $onChange = null)
    {
        $this->ensureConnected();
        while (true) {
            $raw = $this->client->receive();
            $message = json_decode($raw, true);
            if (!is_array($message)) {
                continue;
            }
            $name = $message['channel'] ?? null;
            if (is_string($name) && isset($this->channels[$name])) {
                $this->channels[$name]->handleServer($message);
            }
            if (($message['type'] ?? '') !== 'broadcast') {
                continue;
            }
            if (!empty($message['channel']) && empty($message['table'])) {
                continue;
            }
            $nested = is_array($message['payload'] ?? null) ? $message['payload'] : [];
            $event = strtoupper($message['event'] ?? $nested['type'] ?? '');
            $schema = $message['schema'] ?? $nested['schema'] ?? 'public';
            $table = $message['table'] ?? $nested['table'] ?? '';
            if ($table === '' || !in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true)) {
                continue;
            }
            $change = ['event' => $event, 'schema' => $schema, 'table' => $table, 'payload' => $nested];
            if (isset($nested['new'])) {
                $change['new'] = $nested['new'];
            }
            if (isset($nested['old'])) {
                $change['old'] = $nested['old'];
            }
            foreach ($this->subs as $sub) {
                if ($sub['schema'] === $schema && $sub['table'] === $table
                    && ($sub['event'] === '*' || strcasecmp($sub['event'], $event) === 0)) {
                    ($sub['callback'])($change);
                }
            }
            if ($onChange) {
                $onChange($change);
            }
        }
    }

    public function dropChannel($name)
    {
        unset($this->channels[$name]);
    }
}
