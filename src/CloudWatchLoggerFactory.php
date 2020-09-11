<?php

namespace Dneey\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class CloudWatchLoggerFactory
{
    /**
     * Custom Monolog instance.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        // @session_start;
        $_SESSION['requestId'] = uniqid('', true);
        $sdkParams = $config["sdk"];
        $tags = $config["tags"] ?? [];
        $name = $config["name"];

        $level = strtoupper($config['level']) ?? 'API';
        // Instantiate AWS SDK CloudWatch Logs Client
        $client = new CloudWatchLogsClient($sdkParams);

        // Log group name, will be created if none is provided
        $groupName = $config['group_name'];

        // Log stream name, will be created if none
        $streamName = $config['stream_name'];

        // Days to keep logs, 14 days by default. Set to `null` to keep logs forever on cloudwatch
        $retentionDays = $config["retention"];

        $batch = $config["batch"];

        // Instantiate handler (tags are optional)
        $handler = new CloudWatch($client, $groupName, $streamName, $retentionDays, $batch, $tags);
        $handler->setFormatter(new JsonFormatter());
        $handler->pushProcessor(new IntrospectionProcessor(Logger::$level, ["Illuminate\\"]));
        $handler->pushProcessor(new WebProcessor());
        $handler->pushProcessor(function ($entry) use ($config) {
            $entry['extra']['requestId'] = @$_SESSION['requestId'];
            $entry['extra']['requestBody'] = $config['log_requests'] ? app('Illuminate\Http\Request')->except($config['log_requests_except']) : [];
            return $entry;
        });

        // Create a log channel
        $logger = new Logger($name);
        // Set handler
        $logger->pushHandler($handler);

        return $logger;
    }
}
