<?php
/*
Plugin Name: GitHub Activity Summary
Plugin URI: https://cmljnelson.wordpress.com
Description: Displays a summary of which issues and pull requests you've been involved in
Version: 0.0.1
Author: Michael Nelson
Author URI: https://stopspazzing.com
*/

// have shortcode
add_shortcode('gas', 'gas_shortcode');

// parameter being: username, date range
function gas_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'username' => 'mnelson4',
        'access_token' => '',
    ), $atts);
    $events = gas_fetch_activity($atts['username'], $atts['access_token']);
    $issues_by_date = gas_group_activity($events);
    $issues_by_date = array_reverse($issues_by_date, true);
    gas_print_activity_summary($issues_by_date, $atts['username']);
}

function gas_group_activity($events)
{
    $issues_by_date = array();
    foreach ($events as $event) {
        $item = null;
        $repo = '';
        if (isset($event->payload->issue)) {
            $item = $event->payload->issue;
        }
        if (isset($event->payload->pull_request)) {
            $item = $event->payload->pull_request;
        }

        if ($item) {
            $url = isset($item->repository_url) ? $item->repository_url : '';
            $repo = str_replace('https://api.github.com/repos/', '', $url);
            if (empty($repo) && isset($item->head, $item->head->repo)) {
                $repo = $item->head->repo->full_name;
            }
            $repo = str_replace('eventespresso/', 'ee/', $repo);
            $repo = str_replace(
                array(
                    'eventsmart.com-website',
                    'eea-',
                    'event-espresso-core',
                    '-gateway'
                ),
                array(
                    'es',
                    '',
                    'core',
                    ''
                ),
                $repo
            );
            $id = $item->number;
            $day = mb_substr($event->created_at, 0, 10);
            $issues_by_date[$day][$repo][$id] = $id;
        }
    }
    return $issues_by_date;
}

function gas_print_activity_summary($issues_by_date, $username)
{
    ?>
    <style>
        .unselectable {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>
    <h1><?php printf(esc_html__('Activity Summary for %1$s', 'event_espresso'), $username); ?></h1>
    <table>
        <thead>
        <tr>
            <th><?php esc_html_e('Date', 'event_espresso'); ?></th>
            <th><?php esc_html_e('Activity', 'event_espresso'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($issues_by_date as $date => $repos) { ?>
            <tr>
                <td class="unselectable"><?php echo $date; ?></td>
                <td>
                    <?php foreach ($repos as $repo => $issues) {
                        echo $repo . ': ' . implode(', ', $issues) . ', ';
                    } ?>
                </td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    <?php
}

// fetch all activity

function gas_fetch_activity($username, $access_token)
{
    $json = array();
    $i = 0;
    do {
        $i++;
        $more_json = gas_fetch_activity_page($username, $access_token, $i);
        $json = array_merge(
            $json,
            $more_json
        );
    } while (count($more_json) === 30);
    return $json;
}

function gas_fetch_activity_page($username, $access_token, $page)
{
    $response = wp_remote_get(
        'https://api.github.com/users/' . $username . '/events?page=' . $page,
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $access_token)
            )
        )
    );
    if (is_wp_error($response)) {
        throw new Exception($response->get_error_message());
    }
    $body = wp_remote_retrieve_body($response);
    if (is_string($body)) {
        $json = json_decode($body);
        return $json;
    } else {
        throw new Exception('Response body was not json. Look: ' . $body);
    }
}
// compile it by date


