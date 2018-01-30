<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;


class JobTaskQueue
{

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    /**
     * @var  AMQPChannel
     */
    private $channel;

    /**
     * JobTaskQueue constructor.
     * @param AMQPStreamConnection $connection
     */
    public function __construct(AMQPStreamConnection $connection)
    {
        $this->connection = $connection;
        $this->channel = $this->connection->channel();
    }

    /**
     * @param string $exchange
     * @param string $queue
     * @param string $message
     * @param int $delay delay time (ms)
     * @return string
     */
    public function add($exchange, $queue, $message, $delay = 0)
    {
        $this->channel->exchange_declare($exchange, 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
            "x-delayed-type" => "fanout"
        )));

        $this->channel->queue_declare($queue, false, false, false, false, false, new AMQPTable(array(
            "x-dead-letter-exchange" => "delayed"
        )));

        $this->channel->queue_bind($queue, $exchange);

        $headers = new AMQPTable(array("x-delay" => $delay));
        $message = new AMQPMessage($message, array('delivery_mode' => 2));
        $message->set('application_headers', $headers);
        $this->channel->basic_publish($message, $exchange);

        return 'success';
    }
}

$server = new Zend\Json\Server\Server();

/**
 * @param string $exchange
 * @param string $queue
 * @param string $message
 * @param int $delay
 * @return string
 */
function add($exchange, $queue, $message, $delay = 0) {
    $AMQPConnection = new AMQPStreamConnection('127.0.0.1', '5672', 'guest', 'guest', '/');
    $jobQueue = new JobTaskQueue($AMQPConnection);
    return $jobQueue->add($exchange, $queue, $message, $delay);
}

$server->addFunction('add');

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

