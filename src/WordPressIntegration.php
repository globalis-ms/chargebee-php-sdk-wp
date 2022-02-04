<?php

namespace Globalis\Chargebee;

use Globalis\Chargebee\Client as Chargebee;
use Globalis\Chargebee\Events\EventChargebeeApiResponseSuccess as EventResponseSuccess;
use Globalis\Chargebee\Events\EventChargebeeApiResponseError as EventResponseError;
use Globalis\WP\Cubi;

class WordPressIntegration
{
    public static function hooks()
    {
        Chargebee::onApiResponseSuccess(function (EventResponseSuccess $event) {
            if (function_exists('do_action')) {
                do_action('globalis/chargebee_api_response', $event);
            }
        });

        Chargebee::onApiResponseError(function (EventResponseError $event) {
            if (function_exists('do_action')) {
                do_action('globalis/chargebee_api_error', $event);
            }
        });

        Cubi\add_filter('qm/collectors', function (array $collectors, \QueryMonitor $qm) {
            require_once __DIR__ . '/QMCollector.php';
            return QMCollector::register($collectors, $qm);
        }, 20, 2);

        Cubi\add_filter('qm/outputter/html', function (array $output, \QM_Collectors $collectors) {
            require_once __DIR__ . '/QMOutputHtml.php';
            return QMOutputHtml::register($output, $collectors);
        }, 120, 2);
    }
}
