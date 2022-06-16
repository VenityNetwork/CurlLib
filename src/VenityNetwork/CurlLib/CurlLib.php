<?php

declare(strict_types=1);

namespace VenityNetwork\CurlLib;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use function usleep;
use const PTHREADS_INHERIT_NONE;

class CurlLib{

    /** @var bool */
    private static bool $packaged;

    public static function isPackaged() : bool{
        return self::$packaged;
    }

    public static function detectPackaged() : void{
        self::$packaged = __CLASS__ !== 'VenityNetwork\\CurlLib\\CurlLib';
    }

    public static function init(PluginBase $plugin, int $threads = 1) {
        return new self($plugin, $threads);
    }

    /** @var CurlThread[] */
    private array $thread = [];
    private array $threadTasksCount = [];
    private array $onSuccess = [];
    private array $onFail = [];
    private int $nextId = 0;
    private int $previousThread = -1;

    private function __construct(
        private PluginBase $plugin, int $threads) {
        for($i = 0; $i < $threads; $i++){
            $notifier = new SleeperNotifier();
            Server::getInstance()->getTickSleeper()->addNotifier($notifier, function() use ($i) {
                $this->handleResponse($i);
            });
            $t = new CurlThread(Server::getInstance()->getLogger(), $notifier);
            $t->start(PTHREADS_INHERIT_NONE);
            while(!$t->running) {
                usleep(1000);
            }
            Server::getInstance()->getLogger()->debug("Started CurlThread (".($i+1) . "/" . $threads . ")");
            $this->thread[$i] = $t;
            $this->threadTasksCount[$i] = 0;
        }
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void {
            foreach($this->thread as $t) {
                $t->triggerGarbageCollector();
            }
        }), 20 * 1800);
    }

    private function handleResponse(int $thread) {
        while(($response = $this->thread[$thread]->fetchResponse()) !== null) {
            $this->threadTasksCount[$thread]--;
            $id = $response->getId();
            if($response->getException() !== null) {
                if(isset($this->onFail[$id])) {
                    ($this->onFail[$id])($response);
                }
            } else {
                if(isset($this->onSuccess[$id])) {
                    ($this->onSuccess[$id])($response);
                }
            }
            unset($this->onSuccess[$id]);
            unset($this->onFail[$id]);
        }
    }

    public function waitAll() {
        foreach($this->thread as $k => $thread) {
            while(($this->threadTasksCount[$k]) > 0) {
                $this->handleResponse($k);
                usleep(1000);
            }
        }
    }

    private function selectThread() : int {
        $thread = null;
        $currentTask = -1;
        foreach($this->threadTasksCount as $k => $v) {
            if($v >= $currentTask && $this->previousThread !== $k) {
                $thread = $k;
                $currentTask = $v;
            }
        }
        if($thread === null) {
            foreach($this->threadTasksCount as $k => $v) {
                if($v > $currentTask) {
                    $thread = $k;
                    $currentTask = $v;
                }
            }
        }
        $this->previousThread = $thread;
        return $thread;
    }

    public function post(string $url, string $body, array $headers = [], array $curlOpts = [], ?callable $onSuccess = null, ?callable $onFail = null) {
        $this->nextId++;
        $id = $this->nextId;
        if($onSuccess !== null) {
            $this->onSuccess[$this->nextId] = $onSuccess;
        }
        if($onFail !== null) {
            $this->onFail[$this->nextId] = $onFail;
        }
        $request = new CurlRequest($id, $url, $headers, true, $body, $curlOpts);
        $thread = $this->selectThread();
        $this->thread[$thread]->sendRequest($request);
        $this->threadTasksCount[$thread]++;
    }

    public function get(string $url, array $headers = [], array $curlOpts = [], ?callable $onSuccess = null, ?callable $onFail = null) {
        $this->nextId++;
        $id = $this->nextId;
        if($onSuccess !== null) {
            $this->onSuccess[$this->nextId] = $onSuccess;
        }
        if($onFail !== null) {
            $this->onFail[$this->nextId] = $onFail;
        }
        $request = new CurlRequest($id, $url, $headers, false, "", $curlOpts);
        $thread = $this->selectThread();
        $this->thread[$thread]->sendRequest($request);
        $this->threadTasksCount[$thread]++;
    }

    public function close() {
        $this->waitAll();
        foreach($this->thread as $thread){
            $thread->close();
        }
    }
}

CurlLib::detectPackaged();