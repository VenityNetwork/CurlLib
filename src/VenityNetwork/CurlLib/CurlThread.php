<?php

declare(strict_types=1);

namespace VenityNetwork\CurlLib;

use pocketmine\Server;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\utils\Internet;
use pocketmine\utils\InternetException;
use Threaded;
use function gc_collect_cycles;
use function gc_enable;
use function gc_mem_caches;
use function ini_set;
use function json_encode;
use function serialize;
use function unserialize;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;

class CurlThread extends Thread{

    private const GC_CODE = "gc";

    public bool $running = false;
    private Threaded $requests;
    private Threaded $responses;
    private SleeperNotifier $notifier;
    private int $lastHandleId = -1;
    private bool $lastHandleSuccess = true;

    public function __construct(private AttachableThreadSafeLogger $logger, private SleeperHandlerEntry $sleeperEntry) {
        $this->requests = new Threaded();
        $this->responses = new Threaded();

        if(!CurlLib::isPackaged()){
            if(($virion = Server::getInstance()->getPluginManager()->getPlugin("DEVirion")) !== null){
                $cl = $virion->getVirionClassLoader();
                $this->setClassLoaders([Server::getInstance()->getLoader(), $cl]);
            }
        }
    }

    private function isSafeRunning() : bool {
        return $this->synchronized(function() : bool {
           return $this->running;
        });
    }

    public function onRun(): void{
        ini_set("memory_limit", "256M");
        gc_enable();
        $this->notifier = $this->sleeperEntry->createNotifier();
        $this->synchronized(function() {
            $this->running = true;
        });
        while($this->isSafeRunning()) {
            try{
                $this->processRequests();
            }catch(\Throwable $e) {
                $this->logger->logException($e);
                if(!$this->lastHandleSuccess){
                    // Prevent memory leak, if the last request failed, we need to notify the main thread
                    // so the callback removed
                    $this->sendResponse(new CurlResponse($this->lastHandleId, exception: $e));
                    $this->lastHandleId = -1;
                    $this->lastHandleSuccess = true;
                }
            }
            $this->wait();
        }
    }

    private function readRequests() : ?string{
        return $this->synchronized(function() : ?string {
            return $this->requests->shift();
        });
    }

    private function processRequests(): void{
        while(($request = $this->readRequests()) !== null) {
            $request = unserialize($request);
            if($request instanceof CurlRequest) {
                $this->lastHandleId = $request->getId();
                $this->lastHandleSuccess = false;
                $opts = $request->getCurlOpts();
                if($request->isPost()) {
                    $opts += [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $request->getPostField()];
                }
                try{
                    $result = Internet::simpleCurl($request->getUrl(), $request->getTimeout(), $request->getHeaders(), $opts);
                    $this->sendResponse(new CurlResponse($request->getId(), $result->getCode(), $result->getHeaders(), $result->getBody()));
                } catch(InternetException $e) {
                    $this->logger->error("CURL Error " . json_encode($request));
                    $this->logger->logException($e);
                    $this->sendResponse(new CurlResponse($request->getId(), exception: $e));
                }
                $this->lastHandleSuccess = true;
            }elseif($request === self::GC_CODE) {
                gc_enable();
                gc_collect_cycles();
                gc_mem_caches();
            }
        }
    }

    public function sendRequest(CurlRequest $request): void{
        $this->synchronized(function() use ($request) {
            $this->requests[] = serialize($request);
            $this->notifyOne();
        });
    }

    private function sendResponse(CurlResponse $response): void{
        $this->synchronized(function() use ($response) {
            $this->responses[] = serialize($response);
            $this->notifier->wakeupSleeper();
        });
    }

    public function fetchResponse() : ?CurlResponse {
        return $this->synchronized(function() {
            $response = $this->responses->shift();
            return $response !== null ? unserialize($response) : null;
        });
    }

    public function triggerGarbageCollector(): void{
        $this->synchronized(function() {
            $this->requests[] = serialize(self::GC_CODE);
            $this->notifyOne();
        });
    }

    public function close(): void{
        $this->synchronized(function() {
            $this->running = false;
            $this->notify();
        });
    }

    public function getSleeperEntry(): SleeperHandlerEntry{
        return $this->sleeperEntry;
    }
}
