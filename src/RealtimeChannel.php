<?php

namespace WOWSQL;

class RealtimeChannel
{
    private $rt;
    public $name;
    private $joined = false;
    private $state = [];
    private $broadcast = [];
    private $presence = [];
    private $tracked;

    public function __construct(WowSQLRealtime $rt, $name)
    {
        $this->rt = $rt;
        $this->name = $name;
    }

    public function on($kind, $filter, callable $cb)
    {
        if ($kind === 'broadcast') {
            $ev = $filter['event'] ?? '*';
            $this->broadcast[] = function ($msg) use ($ev, $cb) {
                if ($ev === '*' || $ev === ($msg['event'] ?? null)) {
                    $cb($msg);
                }
            };
        } else {
            $this->presence[] = $cb;
        }
        return $this;
    }

    public function subscribe(callable $onStatus = null)
    {
        $this->rt->ensureConnected();
        $this->rt->sendJson(['type' => 'join', 'channel' => $this->name]);
        $this->joined = true;
        if ($onStatus) {
            $onStatus('SUBSCRIBED');
        }
        return $this;
    }

    public function send($event, $payload = [])
    {
        $this->rt->ensureConnected();
        if (!$this->joined) {
            $this->rt->sendJson(['type' => 'join', 'channel' => $this->name]);
            $this->joined = true;
        }
        $this->rt->sendJson([
            'type' => 'broadcast',
            'channel' => $this->name,
            'event' => $event,
            'payload' => $payload ?: new \stdClass(),
        ]);
    }

    public function track($payload)
    {
        $this->tracked = $payload;
        $this->rt->ensureConnected();
        if (!$this->joined) {
            $this->rt->sendJson(['type' => 'join', 'channel' => $this->name]);
            $this->joined = true;
        }
        $this->rt->sendJson([
            'type' => 'presence',
            'event' => 'track',
            'channel' => $this->name,
            'payload' => $payload,
        ]);
    }

    public function presenceState()
    {
        return $this->state;
    }

    public function unsubscribe()
    {
        $this->rt->sendJson(['type' => 'leave', 'channel' => $this->name]);
        $this->joined = false;
        $this->rt->dropChannel($this->name);
    }

    public function handleServer(array $message)
    {
        $type = $message['type'] ?? '';
        if ($type === 'joined') {
            $this->joined = true;
        } elseif ($type === 'presence') {
            $event = $message['event'] ?? '';
            if ($event === 'sync' && is_array($message['state'] ?? null)) {
                $this->state = $message['state'];
            } elseif ($event === 'join' && isset($message['key'])) {
                $this->state[$message['key']] = $message['payload'] ?? null;
            } elseif ($event === 'leave' && isset($message['key'])) {
                unset($this->state[$message['key']]);
            }
            foreach ($this->presence as $cb) {
                $cb($message);
            }
        } elseif ($type === 'broadcast') {
            $wrapped = [
                'event' => $message['event'] ?? '',
                'payload' => $message['payload'] ?? [],
                'channel' => $this->name,
            ];
            foreach ($this->broadcast as $cb) {
                $cb($wrapped);
            }
        }
    }
}
