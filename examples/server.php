<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

$connection = new AMQPStreamConnection('127.0.0.1', '5672', 'guest', 'guest', '/');
$channel = $connection->channel();

$channel->exchange_declare('job task', 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
    "x-delayed-type" => "fanout"
)));

$channel->queue_declare('job queue', false, false, false, false, false, new AMQPTable(array(
    "x-dead-letter-exchange" => "delayed"
)));

$channel->queue_bind('job queue', 'job task');

function process_message(AMQPMessage $message)
{
    var_dump($message->getBody());
}
/*
    queue: Queue from where to get the messages
    consumer_tag: Consumer identifier
    no_local: Don't receive messages published by this consumer.
    no_ack: Tells the server if the consumer will acknowledge the messages.
    exclusive: Request exclusive consumer access, meaning only this consumer can access the queue
    nowait:
    callback: A PHP Callback
*/
$channel->basic_consume('job queue', '', false, true, false, false, 'process_message');


function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}
register_shutdown_function('shutdown', $channel, $connection);

while (count($channel->callbacks)) {
    $channel->wait();
}