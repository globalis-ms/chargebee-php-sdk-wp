<?php

namespace Globalis\Chargebee;

class QMCollector extends \QM_Collector
{
    public $id = 'chargebee';

    public function __construct()
    {
        parent::__construct();
        add_action('globalis/chargebee_api_response', [$this, 'log_chargebee_response'], 90, 1);
        add_action('globalis/chargebee_api_error', [$this, 'log_chargebee_error'], 90, 1);
    }

    public static function register(array $collectors, \QueryMonitor $qm)
    {
        $collectors['chargebee'] = new self();
        return $collectors;
    }

    public function name()
    {
        return __('Chargebee', 'query-monitor');
    }

    public function get_concerned_actions()
    {
        $actions = [
            'globalis/chargebee_api_response',
            'globalis/chargebee_api_error',
        ];

        return $actions;
    }

    public function qm_backtrace_to_ignore()
    {
        $args = [
            'ignore_class' => [
                'League\Event\EventDispatcher' => true,
                'Globalis\Chargebee\WordPressIntegration' => true,
                'Globalis\Chargebee\Client' => true,
                'Globalis\Chargebee\Api\AbstractApi' => true,
            ],
            'ignore_hook' => [
                'globalis/chargebee_api_response' => true,
                'globalis/chargebee_api_error' => true,
            ],
        ];

        foreach (get_declared_classes() as $class_name) {
            if (\Globalis\WP\Cubi\str_starts_with($class_name, "Globalis\\Chargebee\\Api\\")) {
                $args['ignore_class'][$class_name] = true;
            }
        }

        return $args;
    }

    public function log_chargebee_response($event)
    {
        $data_qm = [
            'site' => $event->site,
            'method' => $event->method ?? '',
            'endpoint' => $event->endpoint,
            'endpoint_stripped' => $event->endpointStripped,
            'parameters' => isset($event->parameters) ? (array) $event->parameters : [],
            'headers' => isset($event->headers) ? (array) $event->headers : [],
            'status' => $event->response->getStatusCode(),
            'error' => null,
            'time' => isset($event->time) ? (float) $event->time : [],
            'trace' => new \QM_Backtrace($this->qm_backtrace_to_ignore()),
        ];

        $this->data['http'][] = $data_qm;
    }

    public function log_chargebee_error($event)
    {
        $error_body = json_decode($event->response->getBody(), true);
        if (is_array($error_body)) {
            $error_code = $error_body["error_code"] ?? "";
            $error_message = $error_body["error_msg"] ?? "";
        } else {
            $error_code = "";
            $error_message = "";
        }

        $error = "";
        $error .= "Error code: <strong>" . $error_code . "</strong>";
        $error .= "<br>Error message: ";
        $error .= $error_message;

        $data_qm = [
            'site' => $event->site,
            'method' => $event->method ?? '',
            'endpoint' => $event->endpoint,
            'endpoint_stripped' => $event->endpointStripped,
            'parameters' => isset($event->parameters) ? (array) $event->parameters : [],
            'headers' => isset($event->headers) ? (array) $event->headers : [],
            'status' => $event->response->getStatusCode(),
            'error' => $error,
            'time' => isset($event->time) ? (float) $event->time : [],
            'trace' => new \QM_Backtrace($this->qm_backtrace_to_ignore()),
        ];

        $this->data['errors']['warning'][] = microtime();

        $this->data['http'][] = $data_qm;
    }
}
