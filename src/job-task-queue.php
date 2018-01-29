<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class JobTaskQueue
{
    /**
     * @param string $exchange
     * @param string $queue
     * @param string $message
     * @param int $delay delay time (ms)
     * @return string
     */
    public function add($exchange, $queue, $message, $delay = 0)
    {
        $connection = new AMQPStreamConnection('127.0.0.1', '5672', 'guest', 'guest', '/');
        $channel = $connection->channel();

        $channel->exchange_declare($exchange, 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
            "x-delayed-type" => "fanout"
        )));

        $channel->queue_declare($queue, false, false, false, false, false, new AMQPTable(array(
            "x-dead-letter-exchange" => "delayed"
        )));

        $channel->queue_bind($queue, $exchange);

        $headers = new AMQPTable(array("x-delay" => $delay));
        $message = new AMQPMessage($message, array('delivery_mode' => 2));
        $message->set('application_headers', $headers);
        $channel->basic_publish($message, $exchange);

        return 'success';
    }
}

$server = new Zend\Json\Server\Server();
$server->setClass(JobTaskQueue::class);

if ('GET' == $_SERVER['REQUEST_METHOD']) {

    $server->setTarget('job-task-queue.php')
        ->setEnvelope(Zend\Json\Server\Smd::ENV_JSONRPC_2);

    $smd = $server->getServiceMap();
    $smd->setDescription("A generic job task queue.");

    header('Content-Type: application/json');
    echo $smd;

} else if ('POST' == $_SERVER['REQUEST_METHOD']) {

    $server->handle();

} else {

    header('Method Not Allowed', true, 405);
}

