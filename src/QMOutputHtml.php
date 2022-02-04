<?php

namespace Globalis\Chargebee;

class QMOutputHtml extends \QM_Output_Html
{
    public function __construct(\QM_Collector $collector)
    {
        parent::__construct($collector);
        add_filter('qm/output/menus', array( $this, 'admin_menu' ), 90);
        add_filter('qm/output/menu_class', array( $this, 'admin_class' ));
    }

    public static function register(array $output, \QM_Collectors $collectors)
    {
        if ($collector = \QM_Collectors::get('chargebee')) {
            $output['chargebee'] = new self($collector);
        }
        return $output;
    }

    public static function sanitizeAttribute($string)
    {
        $string = str_replace("\\", "-", $string);
        $string = sanitize_html_class($string);
        return $string;
    }

    public function output()
    {

        $data = $this->collector->get_data();

        if (! empty($data['http'])) {
            $endpoints_labels = array_unique(array_column($data['http'], "endpoint_stripped"));
            $endpoints_keys = $endpoints_labels;
            $endpoints = array_combine($endpoints_keys, $endpoints_labels);
            uasort($endpoints, 'strcasecmp');

            $statuses_labels = array_unique(array_column($data['http'], "status"));
            $statuses_keys = array_map('sanitize_key', $statuses_labels);
            $statuses = array_combine($statuses_keys, $statuses_labels);
            uasort($statuses, 'strcasecmp');

            $traces = array_column($data['http'], "trace");
            $trace_callers = array_map(function ($trace) {
                $filtered_trace = $trace->get_display_trace();
                return $filtered_trace[1];
            }, $traces);
            $callers = [];
            foreach ($trace_callers as $caller) {
                if ($caller['function'] == "{closure}") {
                    $caller_id = md5($caller['calling_file']) . '_line_' . $caller['calling_line'];
                    $caller_id = self::sanitizeAttribute($caller_id);
                    $callers[$caller_id] = $caller['display'] . ' at line ' . $caller['calling_line'];
                } else {
                    $caller_id = $caller['id'];
                    $caller_id = self::sanitizeAttribute($caller_id);
                    $callers[$caller_id] = $caller['display'];
                }
            }
            uasort($callers, 'strcasecmp');

            $this->before_tabular_output();

            ?>
            <style type="text/css">
                .qm-hide-endpoint { display: none; }
                .qm-hide-status { display: none; }
                .qm-hide-caller { display: none; }
            </style>
            <?php

            echo '<thead>';
            echo '<tr>';
            echo '<th scope="col" class="qm-sorted-asc qm-sortable-column" role="columnheader" aria-sort="ascending">';
            echo $this->build_sorter('#'); // WPCS: XSS ok;
            echo '</th>';
            echo '<th scope="col">' . esc_html__('Method', 'query-monitor') . '</th>';
            echo '<th scope="col" class="qm-filterable-column">';
            echo $this->build_filter('endpoint', $endpoints, __('Endpoint', 'query-monitor'));
            echo '</th>';
            echo '<th scope="col">' . esc_html__('Parameters', 'query-monitor') . '</th>';
            echo '<th scope="col" class="qm-filterable-column">';
            echo $this->build_filter('status', $statuses, __('Status', 'query-monitor'));
            echo '</th>';
            echo '<th scope="col" class="qm-filterable-column">';
            echo $this->build_filter('caller', $callers, __('Caller', 'query-monitor'));
            echo '</th>';
            if (isset($data['errors']) && !empty($data['errors'])) {
                echo '<th scope="col">' . esc_html__('Error', 'query-monitor') . '</th>';
            }
            echo '<th scope="col" class="qm-num qm-sortable-column" role="columnheader" aria-sort="none">';
            echo $this->build_sorter(__('Time', 'query-monitor')); // WPCS: XSS ok.
            echo '</th>';
            echo '</tr>';
            echo '</thead>';

            echo '<tbody>';
            $i = 0;

            $total_time = (float) 0;

            foreach ($data['http'] as $key => $row) {
                if (isset($row['trace'])) {
                    $stack          = array();
                    $filtered_trace = $row['trace']->get_display_trace();

                    array_shift($filtered_trace);

                    $caller = array_shift($filtered_trace);
                    $caller_name = self::output_filename($caller['display'], $caller['calling_file'], $caller['calling_line']);

                    if ($caller['function'] == "{closure}") {
                        $caller_id = md5($caller['calling_file']) . '_line_' . $caller['calling_line'];
                    } else {
                        $caller_id = $caller['id'];
                    }

                    $caller_id = self::sanitizeAttribute($caller_id);

                    foreach ($filtered_trace as $item) {
                        $stack[] = self::output_filename($item['display'], $item['calling_file'], $item['calling_line']);
                    }
                } else {
                    if (! empty($row['caller'])) {
                        $caller_name = '<code>' . esc_html($row['caller']) . '</code>';
                    } else {
                        $caller_name = '<code>' . esc_html__('Unknown', 'query-monitor') . '</code>';
                    }

                    $stack       = explode(', ', $row['stack']);
                    $stack       = array_reverse($stack);
                    array_shift($stack);
                    $stack       = array_map(function ($item) {
                        return '<code>' . esc_html($item) . '</code>';
                    }, $stack);
                }

                $i++;
                $is_error = false;
                $row_attr = array();
                $css      = '';

                if (!empty($row['error'])) {
                    $css = 'qm-warn';
                }

                $row_attr['data-qm-endpoint'] = $row['endpoint_stripped'];
                $row_attr['data-qm-status'] = sanitize_key($row['status']);
                $row_attr['data-qm-caller'] = $caller_id;
                $row_attr['data-qm-time'] = (float) $row['time'];

                $attr = '';
                foreach ($row_attr as $a => $v) {
                    $attr .= ' ' . $a . '="' . esc_attr($v) . '"';
                }

                printf(
                    '<tr %s class="%s">',
                    $attr,
                    esc_attr($css)
                );
                printf(
                    '<td>%s</td>',
                    esc_html($i)
                );
                printf(
                    '<td>%s</td>',
                    esc_html($row['method'])
                );
                printf(
                    '<td class="qm-url qm-ltr qm-wrap">%s</td>',
                    esc_html($row['endpoint'])
                );

                $parameters_string = "";

                foreach ($row['parameters'] as $key => $value) {
                    $parameters_string .= $key . " => " . $value . "<br>";
                }
                printf(
                    '<td>%s</td>',
                    empty($parameters_string) ? "&mdash;" : $parameters_string
                );

                printf(
                    '<td>%s</td>',
                    $row['error'] ? '<span class="qm-warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span>' . $row['status'] : $row['status']
                );


                echo '<td class="qm-row-caller qm-ltr qm-has-toggle qm-nowrap">';

                echo self::build_toggler(); // WPCS: XSS ok;

                echo '<ol>';
                echo "<li>{$caller_name}</li>"; // WPCS: XSS ok.

                if (! empty($stack)) {
                    echo '<div class="qm-toggled"><li>' . implode('</li><li>', $stack) . '</li></div>'; // WPCS: XSS ok.
                } else {
                    echo '<div class="qm-toggled"></div>';
                }

                echo '</ol>';
                echo '</td>';

                if (isset($data['errors']) && !empty($data['errors'])) {
                    printf(
                        '<td>%s</td>',
                        $row['error'] ?? "&mdash;"
                    );
                }

                $total_time += $row['time'];
                $time =  (float) $row['time'];
                $time = number_format_i18n($time, 4);

                printf(
                    '<td class="qm-num">%s</td>',
                    esc_html($time)
                );
                echo '</tr>';
            }

            echo '</tbody>';
            echo '<tfoot>';

            $total_time = number_format_i18n($total_time, 4);
            $count       = count($data['http']);

            $count_cols = isset($data['errors']) && !empty($data['errors']) ? 7 : 6;

            echo '<tr>';
            printf(
                '<td colspan="' . $count_cols . '">%s</td>',
                sprintf(
                    'Total: %s',
                    '<span class="qm-items-number">' . esc_html(number_format_i18n($count)) . '</span>'
                )
            );
            echo '<td class="qm-num qm-items-time">' . esc_html($total_time) . '</td>';
            echo '</tr>';
            echo '</tfoot>';

            $this->after_tabular_output();
        } else {
            $this->before_non_tabular_output();

            $notice = __('No Chargebee API calls.', 'query-monitor');
            echo $this->build_notice($notice);

            $this->after_non_tabular_output();
        }
    }

    public function admin_class(array $class)
    {

        $data = $this->collector->get_data();

        if (isset($data['errors']['alert'])) {
            $class[] = 'qm-alert';
        }
        if (isset($data['errors']['warning'])) {
            $class[] = 'qm-warning';
        }

        return $class;
    }

    public function admin_menu(array $menu)
    {

        $data = $this->collector->get_data();

        $count = isset($data['http']) ? count($data['http']) : 0;

        $title = ( empty($count) )
            ? __('Chargebee API Calls', 'query-monitor')
            /* translators: %s: Number of calls to the HTTP API */
            : __('Chargebee API Calls (%s)', 'query-monitor');

        $args = array(
            'title' => esc_html(sprintf(
                $title,
                number_format_i18n($count)
            )),
        );

        if (isset($data['errors']['alert'])) {
            $args['meta']['classname'] = 'qm-alert';
        }
        if (isset($data['errors']['warning'])) {
            $args['meta']['classname'] = 'qm-warning';
        }

        $menu[ $this->collector->id() ] = $this->menu($args);

        return $menu;
    }
}
